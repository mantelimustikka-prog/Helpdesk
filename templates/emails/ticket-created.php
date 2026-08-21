<?php
/**
 * @package WP Helpdesk
 */
/** @var array<string,mixed> $vars */
$ticket      = $vars['ticket'] ?? array();
$ticket_no   = (string) ( $ticket['ticket_no'] ?? '' );
$ticket_link = (string) ( $ticket['ticket_link'] ?? '' );
?>
<div style="font-family: Arial, sans-serif; color: #1d2327;">
	<h2><?php echo esc_html( sprintf( 'Ticket %s created', $ticket_no ) ); ?></h2>
	<p><strong><?php esc_html_e( 'Subject:', 'wp-helpdesk' ); ?></strong> <?php echo esc_html( (string) ( $ticket['subject'] ?? '' ) ); ?></p>
	<p><strong><?php esc_html_e( 'Requester:', 'wp-helpdesk' ); ?></strong> <?php echo esc_html( (string) ( $ticket['requester_name'] ?? '' ) ); ?></p>
	<p><?php esc_html_e( 'Your support request has been received by WP Helpdesk.', 'wp-helpdesk' ); ?></p>
	<?php if ( '' !== $ticket_link ) : ?>
		<p>
			<?php esc_html_e( 'You can view your ticket, read updates, and add a reply at any time using the link below:', 'wp-helpdesk' ); ?>
		</p>
		<p>
			<a href="<?php echo esc_url( $ticket_link ); ?>" style="display:inline-block;padding:10px 20px;background:#2271b1;color:#fff;text-decoration:none;border-radius:4px;">
				<?php esc_html_e( 'View your ticket', 'wp-helpdesk' ); ?>
			</a>
		</p>
		<p style="font-size:0.85em;color:#646970;"><?php echo esc_html( $ticket_link ); ?></p>
	<?php else : ?>
		<p><?php esc_html_e( 'Please log in to view and reply to this ticket.', 'wp-helpdesk' ); ?></p>
	<?php endif; ?>
</div>
