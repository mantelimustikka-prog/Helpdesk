<?php
/**
 * @package WP Helpdesk
 */
/** @var array<string,mixed> $vars */
$ticket  = $vars['ticket'] ?? array();
$message = $vars['message'] ?? array();
$ticket_no   = (string) ( $ticket['ticket_no'] ?? '' );
$guest_token = (string) ( $ticket['guest_token'] ?? '' );
$ticket_link = (string) ( $ticket['ticket_link'] ?? '' );
if ( '' === $ticket_link ) {
	$ticket_link = '' !== $guest_token && '' !== $ticket_no
		? home_url( '/helpdesk/ticket/' . rawurlencode( $ticket_no ) . '/' . rawurlencode( $guest_token ) . '/' )
		: '';
}
?>
<div style="font-family: Arial, sans-serif; color: #1d2327;">
	<h2><?php echo esc_html( sprintf( 'New reply for %s', (string) ( $ticket['ticket_no'] ?? '' ) ) ); ?></h2>
	<p><strong><?php esc_html_e( 'Subject:', 'wp-helpdesk' ); ?></strong> <?php echo esc_html( (string) ( $ticket['subject'] ?? '' ) ); ?></p>
	<p><strong><?php esc_html_e( 'Reply:', 'wp-helpdesk' ); ?></strong></p>
	<div><?php echo wp_kses_post( wpautop( (string) ( $message['body'] ?? '' ) ) ); ?></div>
	<?php if ( '' !== $ticket_link ) : ?>
		<p>
			<a href="<?php echo esc_url( $ticket_link ); ?>" style="display:inline-block;padding:10px 20px;background:#2271b1;color:#fff;text-decoration:none;border-radius:4px;">
				<?php esc_html_e( 'View and continue this ticket', 'wp-helpdesk' ); ?>
			</a>
		</p>
		<p style="font-size:0.85em;color:#646970;"><?php echo esc_html( $ticket_link ); ?></p>
	<?php else : ?>
		<p><?php esc_html_e( 'This is an outbound notification. Please log in to continue the conversation.', 'wp-helpdesk' ); ?></p>
	<?php endif; ?>
</div>
