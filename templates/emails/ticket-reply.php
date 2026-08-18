<?php
/**
 * @package WP Helpdesk
 */
/** @var array<string,mixed> $vars */
$ticket  = $vars['ticket'] ?? array();
$message = $vars['message'] ?? array();
?>
<div style="font-family: Arial, sans-serif; color: #1d2327;">
	<h2><?php echo esc_html( sprintf( 'New reply for %s', (string) ( $ticket['ticket_no'] ?? '' ) ) ); ?></h2>
	<p><strong><?php esc_html_e( 'Subject:', 'wp-helpdesk' ); ?></strong> <?php echo esc_html( (string) ( $ticket['subject'] ?? '' ) ); ?></p>
	<p><strong><?php esc_html_e( 'Reply:', 'wp-helpdesk' ); ?></strong></p>
	<div><?php echo wp_kses_post( wpautop( (string) ( $message['body'] ?? '' ) ) ); ?></div>
	<p><?php esc_html_e( 'This is an outbound notification. Please log in to continue the conversation.', 'wp-helpdesk' ); ?></p>
</div>
