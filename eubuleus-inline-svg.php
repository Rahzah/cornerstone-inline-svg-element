<?php
/**
 * Plugin Name:       Eubuleus Inline SVG for Cornerstone
 * Description:       Adds a native Cornerstone element that renders SVG attachments safely as inline markup.
 * Version:           1.0.1
 * Requires at least: 6.6
 * Requires PHP:      7.4
 * Requires Plugins:  safe-svg
 * Author:            Eubuleus GmbH
 * License:           GPL-2.0-or-later
 * Text Domain:       eubuleus-inline-svg
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'EUB_INLINE_SVG_VERSION', '1.0.1' );
define( 'EUB_INLINE_SVG_FILE', __FILE__ );
define( 'EUB_INLINE_SVG_PATH', plugin_dir_path( __FILE__ ) );
define( 'EUB_INLINE_SVG_URL', plugin_dir_url( __FILE__ ) );

require_once EUB_INLINE_SVG_PATH . 'includes/class-eub-inline-svg.php';

Eubuleus_Inline_SVG::boot();
