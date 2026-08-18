<?php
/**
 * @package WP Helpdesk
 */

return array(
	'name'        => 'WP Helpdesk',
	'version'     => defined( 'HD_VERSION' ) ? HD_VERSION : '1.0.0',
	'text_domain' => 'wp-helpdesk',
	'basename'    => defined( 'HD_BASENAME' ) ? HD_BASENAME : 'helpdesk/helpdesk.php',
	'path'        => defined( 'HD_PATH' ) ? HD_PATH : __DIR__ . '/../',
	'url'         => defined( 'HD_URL' ) ? HD_URL : '',
);
