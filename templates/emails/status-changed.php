<?php
/**
 * @package WP Helpdesk
 */
/** @var array<string,mixed> $vars */
$ticket     = $vars['ticket'] ?? array();
$old_status = $vars['oldStatus'] ?? '';
$new_status = $vars['newStatus'] ?? '';
?>
<div style="font-family: Arial, sans-serif; color: #1d2327;">
	<h2><?php echo esc_html( sprintf( 'Ticket %s status updated', (string) ( $ticket['ticket_no'] ?? '' ) ) ); ?></h2>
	<p><strong><?php esc_html_e( 'Subject:', 'wp-helpdesk' ); ?></strong> <?php echo esc_html( (string) ( $ticket['subject'] ?? '' ) ); ?></p>
	<p><strong><?php esc_html_e( 'Previous status:', 'wp-helpdesk' ); ?></strong> <?php echo esc_html( $old_status ); ?></p>
	<p><strong><?php esc_html_e( 'New status:', 'wp-helpdesk' ); ?></strong> <?php echo esc_html( $new_status ); ?></p>
	<p><?php esc_html_e( 'This is an outbound notification. Please log in to reply or review the ticket.', 'wp-helpdesk' ); ?></p>
</div>
