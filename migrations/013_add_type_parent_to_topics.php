<?php
/**
 * @package WP Helpdesk
 *
 * Migration 013 – Simplified topic model.
 *
 * Adds `type` (root|followup) and `parent_id` directly to the topics table,
 * replacing the reliance on the topic_transitions table for hierarchy.
 *
 * Existing transition data is migrated: topics with at least one active
 * incoming transition become `followup` and receive the first matching parent.
 */

return new class {
	public function up(): void {
		global $wpdb;

		$table = $wpdb->base_prefix . 'hd_topics';

		// Add `type` column if it does not exist yet.
		$cols = $wpdb->get_col( "DESCRIBE {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( ! in_array( 'type', $cols, true ) ) {
			$wpdb->query( "ALTER TABLE {$table} ADD COLUMN type VARCHAR(20) NOT NULL DEFAULT 'root' AFTER description" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		// Add `parent_id` column if it does not exist yet.
		if ( ! in_array( 'parent_id', $cols, true ) ) {
			$wpdb->query( "ALTER TABLE {$table} ADD COLUMN parent_id BIGINT(20) UNSIGNED NULL DEFAULT NULL AFTER type" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		// Migrate existing hierarchy from transitions table.
		$transitions_table = $wpdb->base_prefix . 'hd_topic_transitions';

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$has_transitions = $wpdb->get_var( "SHOW TABLES LIKE '{$transitions_table}'" );
		if ( ! $has_transitions ) {
			return;
		}

		// For every topic that has an active incoming transition, set type=followup
		// and parent_id to the first active from_topic_id.
		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			"SELECT to_topic_id, MIN(from_topic_id) AS parent_id
			 FROM {$transitions_table}
			 WHERE is_active = 1
			 GROUP BY to_topic_id",
			ARRAY_A
		);

		foreach ( $rows ?: array() as $row ) {
			$to_id     = (int) $row['to_topic_id'];
			$parent_id = (int) $row['parent_id'];
			if ( $to_id <= 0 || $parent_id <= 0 ) {
				continue;
			}

			$wpdb->update(
				$table,
				array(
					'type'      => 'followup',
					'parent_id' => $parent_id,
				),
				array( 'id' => $to_id )
			);
		}
	}
};
