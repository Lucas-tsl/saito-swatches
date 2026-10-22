<?php
/**
 * Plugin Name:          Saito Swatches
 * Plugin URI:           https://wordpress.org/plugins/saito-swatches/
 * Description:          Replace WooCommerce variation dropdowns with image swatches and show a unit price (e.g. per 100 ml). Everything is editable from the admin, no template override needed.
 * Version:              1.4.0
 * Requires at least:    6.0
 * Requires PHP:         7.4
 * Requires Plugins:     woocommerce
 * Author:               Troteseil Lucas
 * Author URI:           https://profiles.wordpress.org/lucastsl/
 * License:              GPLv2 or later
 * License URI:          https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:          saito-swatches
 * Domain Path:          /languages
 * WC requires at least: 8.0
 * WC tested up to:      11.1
 *
 * @package Saito_Swatches
 */

defined( 'ABSPATH' ) || exit;

define( 'SAITO_SWATCHES_VERSION', '1.4.0' );
define( 'SAITO_SWATCHES_FILE', __FILE__ );
define( 'SAITO_SWATCHES_DIR', plugin_dir_path( __FILE__ ) );
define( 'SAITO_SWATCHES_URL', plugin_dir_url( __FILE__ ) );

require_once SAITO_SWATCHES_DIR . 'includes/class-saito-swatches-settings.php';
require_once SAITO_SWATCHES_DIR . 'includes/class-saito-swatches-terms.php';
require_once SAITO_SWATCHES_DIR . 'includes/class-saito-swatches-product.php';
require_once SAITO_SWATCHES_DIR . 'includes/class-saito-swatches-frontend.php';
require_once SAITO_SWATCHES_DIR . 'includes/class-saito-swatches-admin.php';

/**
 * Declare compatibility with WooCommerce features (the plugin never touches orders or checkout).
 */
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
		}
	}
);

/**
 * Load the bundled translations (translate.wordpress.org language packs take precedence).
 */
add_action(
	'init',
	function () {
		load_plugin_textdomain( 'saito-swatches', false, dirname( plugin_basename( SAITO_SWATCHES_FILE ) ) . '/languages' ); // phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound -- Bundled fr_FR until the language pack exists.
	}
);

/**
 * Boot once all plugins are loaded, only when WooCommerce is active.
 */
add_action(
	'plugins_loaded',
	function () {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', 'saito_swatches_missing_wc_notice' );
			return;
		}

		Saito_Swatches_Terms::init();
		Saito_Swatches_Product::init();
		Saito_Swatches_Frontend::init();

		if ( is_admin() ) {
			Saito_Swatches_Admin::init();
		}
	}
);

/**
 * Version string for an asset: plugin version + file modification time, so browsers and
 * page caches always fetch the new file after an update.
 *
 * @param string $relative Path relative to the plugin folder, e.g. "assets/js/admin.js".
 * @return string
 */
function saito_swatches_asset_version( $relative ) {
	$file = SAITO_SWATCHES_DIR . ltrim( $relative, '/' );
	$time = file_exists( $file ) ? filemtime( $file ) : false;
	return $time ? SAITO_SWATCHES_VERSION . '.' . $time : SAITO_SWATCHES_VERSION;
}

/**
 * Public helper for other plugins (e.g. Saito Navi): image URL configured for an attribute value.
 *
 * @param string $value Term slug, term name or custom attribute value (e.g. "100ml").
 * @param string $size  Image size.
 * @return string URL, or empty string when no image is configured.
 */
function saito_swatches_get_image_url( $value, $size = 'thumbnail' ) {
	$id  = Saito_Swatches_Settings::image_id( $value );
	$url = $id ? wp_get_attachment_image_url( $id, $size ) : '';
	return $url ? $url : '';
}

/**
 * Admin notice shown when WooCommerce is not active.
 */
function saito_swatches_missing_wc_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	echo '<div class="notice notice-warning"><p>';
	echo esc_html__( 'Saito Swatches requires WooCommerce to be installed and active.', 'saito-swatches' );
	echo '</p></div>';
}
