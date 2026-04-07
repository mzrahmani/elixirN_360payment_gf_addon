<?php
/**
 * Plugin Name: ElixirNotions 360Payments Gravity Forms add-on
 * Description: Gravity Forms feed add-on for 360payments hosted iframe flows with webhook-based finalization.
 * Version: 1.0.1
 * Author: Elixirnotions
 * Author URI: https://elixirnotions.com
 * Text Domain: elixirn-gf-360payment
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ELIXIRN_360P_VERSION', '1.0.1' );
define( 'ELIXIRN_360P_PLUGIN_FILE', __FILE__ );
define( 'ELIXIRN_360P_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ELIXIRN_360P_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'ELIXIRN_360P_REST_NAMESPACE', 'elixirN_360p/v1' );

require_once ELIXIRN_360P_PLUGIN_DIR . 'includes/class-elixirn-360p-utils.php';
require_once ELIXIRN_360P_PLUGIN_DIR . 'includes/class-elixirn-360p-logger.php';
require_once ELIXIRN_360P_PLUGIN_DIR . 'includes/class-elixirn-360p-payment-api.php';

/**
 * Show missing dependency notice.
 */
function elixirN_360p_dependency_notice() {
	add_action(
		'admin_notices',
		static function() {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'ElixirN Gravity Forms Force Payment requires Gravity Forms with the Feed Add-On framework enabled.', 'elixirn-gf-360payment' ) . '</p></div>';
		}
	);
}

/**
 * Bootstrap add-on.
 */
function elixirN_360p_bootstrap() {
	if ( ! class_exists( 'GFForms' ) ) {
		elixirN_360p_dependency_notice();
		return;
	}

	require_once ELIXIRN_360P_PLUGIN_DIR . 'includes/class-elixirn-360p-shortcode.php';
	require_once ELIXIRN_360P_PLUGIN_DIR . 'includes/class-elixirn-360p-admin.php';
	require_once ELIXIRN_360P_PLUGIN_DIR . 'includes/class-elixirn-360p-feed-addon.php';

	if ( ! class_exists( 'GFAddOn' ) || ! class_exists( 'GFFeedAddOn' ) || ! class_exists( 'ElixirN_360p_Feed_AddOn' ) ) {
		elixirN_360p_dependency_notice();
		return;
	}

	GFAddOn::register( 'ElixirN_360p_Feed_AddOn' );
	ElixirN_360p_Shortcode::get_instance();
	ElixirN_360p_Admin::get_instance();
}
add_action( 'gform_loaded', 'elixirN_360p_bootstrap', 5 );

/**
 * Warn admins when Gravity Forms is not active and gform_loaded never fires.
 */
function elixirN_360p_check_gravity_forms_loaded() {
	if ( ! class_exists( 'GFForms' ) && ! did_action( 'gform_loaded' ) ) {
		elixirN_360p_dependency_notice();
	}
}
add_action( 'plugins_loaded', 'elixirN_360p_check_gravity_forms_loaded', 20 );

/**
 * Convenience getter.
 *
 * @return ElixirN_360p_Feed_AddOn|null
 */
function elixirN_360p_feed_addon() {
	if ( ! class_exists( 'ElixirN_360p_Feed_AddOn' ) ) {
		return null;
	}

	return ElixirN_360p_Feed_AddOn::get_instance();
}
