<?php
/**
 * @package WP Helpdesk
 */

namespace WPHelpdesk\Interfaces\Frontend;

use WPHelpdesk\Support\Constants;

class FormDefinitionFactory {
	/**
	 * Return form definitions for supported frontend flows.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function getDefinitions(): array {
		return array(
			'guest'  => $this->buildDefinition( 'guest' ),
			'member' => $this->buildDefinition( 'member' ),
		);
	}

	/**
	 * Build a declarative form definition.
	 *
	 * @param string $form_type Form type.
	 * @return array<string, mixed>
	 */
	protected function buildDefinition( string $form_type ): array {
		$topic_required = 1 === (int) get_site_option( Constants::OPTION_GENERAL_REQUIRE_TOPIC, 1 );
		$detail_fields  = array( 'requester_phone', 'subject', 'message' );

		if ( 'guest' === $form_type ) {
			$detail_fields = array_merge( array( 'requester_name', 'requester_email' ), $detail_fields );
		}

		return array(
			'steps' => array(
				array(
					'index'         => 0,
					'id'            => 'topic',
					'title'         => 'Topic',
					'fields'        => array( 'topic_id' ),
					'dynamic_visibility' => 'always',
					'next_step_map' => array(
						'continue' => 1,
					),
				),
				array(
					'index'         => 1,
					'id'            => 'details',
					'title'         => 'Details',
					'fields'        => $detail_fields,
					'dynamic_visibility' => 'always',
					'next_step_map' => array(
						'submit' => 2,
					),
				),
				array(
					'index'         => 2,
					'id'            => 'done',
					'title'         => 'Done',
					'fields'        => array(),
					'dynamic_visibility' => 'always',
				),
			),
			'fields' => array(
				'topic_id' => array(
					'type'       => 'topic_selector',
					'required'   => $topic_required,
					'visibility' => 'always',
					'topic_selector_behavior' => array(
						'supports_branching'       => true,
						'branch_container_role'    => 'topic-branch',
						'conditional_next_step_map' => array(
							'complete_path' => 1,
						),
					),
				),
				'requester_name'  => array( 'type' => 'text', 'required' => true, 'visibility' => 'step:details' ),
				'requester_email' => array( 'type' => 'email', 'required' => true, 'visibility' => 'step:details' ),
				'requester_phone' => array( 'type' => 'tel', 'required' => true, 'visibility' => 'step:details' ),
				'subject'         => array( 'type' => 'text', 'required' => true, 'visibility' => 'step:details' ),
				'message'         => array( 'type' => 'textarea', 'required' => true, 'visibility' => 'step:details' ),
			),
			'next_step_map' => array(
				'0' => array( 'continue' => 1 ),
				'1' => array( 'submit' => 2 ),
			),
		);
	}
}
