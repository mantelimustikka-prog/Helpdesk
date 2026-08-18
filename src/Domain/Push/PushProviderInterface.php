<?php
/**
 * @package WP Helpdesk
 */

namespace WPHelpdesk\Domain\Push;

interface PushProviderInterface {
	public function send( array $deviceTokens, string $title, string $body, array $data = array() ): bool;
}
