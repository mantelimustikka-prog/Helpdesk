<?php
/**
 * @package WP Helpdesk
 */
/** @var array<string,mixed> $vars */
$ticket = $vars['ticket'] ?? array();
?>
<div style="font-family: Arial, sans-serif; color: #1d2327;">
	<h2><?php echo esc_html( sprintf( 'Ticket %s created', (string) ( $ticket['ticket_no'] ?? '' ) ) ); ?></h2>
	<p><strong><?php esc_html_e( 'Subject:', 'wp-helpdesk' ); ?></strong> <?php echo esc_html( (string) ( $ticket['subject'] ?? '' ) ); ?></p>
	<p><strong><?php esc_html_e( 'Requester:', 'wp-helpdesk' ); ?></strong> <?php echo esc_html( (string) ( $ticket['requester_name'] ?? '' ) ); ?></p>
	<p><?php esc_html_e( 'Your support request has been received by WP Helpdesk.', 'wp-helpdesk' ); ?></p>
	<p><?php esc_html_e( 'This is an outbound notification. Please log in to reply.', 'wp-helpdesk' ); ?></p>
</div>
