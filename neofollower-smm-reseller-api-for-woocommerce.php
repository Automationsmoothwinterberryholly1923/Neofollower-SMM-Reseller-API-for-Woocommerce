<?php
/**
 * Plugin Name: Neofollower – SMM Reseller API for WooCommerce
 * Plugin URI: https://neofollower.com/help-center/resellers-affiliates/how-to-sell-neofollower-services-with-wordpress-and-woocommerce/
 * Description: Turn WooCommerce into an SMM reseller storefront by connecting products to the Neofollower SMM panel API and automating paid order fulfillment.
 * Version: 1.2.3
 * Author: Neofollower
 * Author URI: https://neofollower.com/
 * Text Domain: neofollower-smm-reseller-api-for-woocommerce
 * Requires at least: 6.2
 * Tested up to: 7.0
 * Requires Plugins: woocommerce
 * Requires PHP: 7.4
 * WC requires at least: 6.0
 * WC tested up to: 10.9
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('ABSPATH')) {
    exit;
}

define('NFWC_VERSION', '1.2.3');
define('NFWC_FILE', __FILE__);
define('NFWC_BASENAME', plugin_basename(__FILE__));
define('NFWC_DIR', plugin_dir_path(__FILE__));
define('NFWC_URL', plugin_dir_url(__FILE__));

define('NFWC_DEFAULT_API_ENDPOINT', 'https://panel.neofollower.com/api/v1');

autoload_nfwc_classes();

function autoload_nfwc_classes() {
    require_once NFWC_DIR . 'includes/class-nfwc-db.php';
    require_once NFWC_DIR . 'includes/class-nfwc-api.php';
    require_once NFWC_DIR . 'includes/class-nfwc-admin.php';
    require_once NFWC_DIR . 'includes/class-nfwc-product.php';
    require_once NFWC_DIR . 'includes/class-nfwc-order.php';
    require_once NFWC_DIR . 'includes/class-nfwc-plugin.php';
}

add_filter('cron_schedules', array('NFWC_Plugin', 'add_cron_schedules_static'));

add_action('before_woocommerce_init', function () {
    if (class_exists('\\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
    }
});

register_activation_hook(__FILE__, array('NFWC_DB', 'activate'));
register_activation_hook(__FILE__, array('NFWC_Plugin', 'activate'));
register_deactivation_hook(__FILE__, array('NFWC_Plugin', 'deactivate'));

add_action('plugins_loaded', function () {
    NFWC_Plugin::instance();
});
