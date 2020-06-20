<?php
/**
 * The bootstrap file for PHPUnit tests for the WooCommerce Deposits plugin.
 * Starts up WP_Mock and requires the files needed for testing.
 */

define( 'TEST_PLUGIN_DIR', dirname( dirname( dirname( __FILE__ ) ) ) . '/' );

// First we need to load the composer autoloader so we can use WP Mock
require_once TEST_PLUGIN_DIR . '/vendor/autoload.php';

// Now call the bootstrap method of WP Mock.
WP_Mock::setUsePatchwork( true );
WP_Mock::bootstrap();
\WP_Mock::userFunction( 'plugin_basename', array(
	'return' => 'mine'
) );
function plugin_dir_path( $file ) {
	return trailingslashit( dirname( $file ) );
}
function trailingslashit( $string ) {
	return untrailingslashit( $string ) . '/';
}
function untrailingslashit( $string ) {
	return rtrim( $string, '/\\' );
}

define( 'WC_VERSION', '3.5.0' );
define( 'WC_ABSPATH', TEST_PLUGIN_DIR . 'vendor/woocommerce/woocommerce/' );
define( 'WC_PLUGIN_FILE', WC_ABSPATH . 'woocommerce.com' );

require_once WC_ABSPATH . 'includes/traits/trait-wc-item-totals.php';
require_once WC_ABSPATH . 'includes/abstracts/abstract-wc-data.php';
require_once WC_ABSPATH . 'includes/abstracts/abstract-wc-product.php';
require_once WC_ABSPATH . 'includes/abstracts/abstract-wc-order.php';
require_once WC_ABSPATH . 'includes/class-wc-autoloader.php';
require_once WC_ABSPATH . 'includes/wc-formatting-functions.php';

require __DIR__ . '/../../includes/class-wc-deposits-cart-manager.php';
require __DIR__ . '/../../includes/class-wc-deposits-plan.php';
require __DIR__ . '/../../includes/class-wc-deposits-scheduled-order-manager.php';
require __DIR__ . '/../../includes/class-wc-deposits-my-account.php';
