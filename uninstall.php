<?php
/**
 * Uninstall cleanup for Neofollower – SMM Reseller API for WooCommerce.
 *
 * @package Neofollower_SMM_Reseller_API
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

// Scheduled events and temporary locks are never useful after deletion.
$hooks = array(
    'nfwc_cron_sync_statuses',
    'nfwc_cron_check_balance',
    'nfwc_cron_cleanup_logs',
);
foreach ($hooks as $hook) {
    $timestamp = wp_next_scheduled($hook);
    while ($timestamp) {
        wp_unschedule_event($timestamp, $hook);
        $timestamp = wp_next_scheduled($hook);
    }
}

$like_lock = $wpdb->esc_like('nfwc_submit_lock_') . '%';
$wpdb->query($wpdb->prepare('DELETE FROM %i WHERE option_name LIKE %s', $wpdb->options, $like_lock));

$settings = get_option('nfwc_settings', array());
if (!is_array($settings) || 'yes' !== (isset($settings['delete_data_on_uninstall']) ? $settings['delete_data_on_uninstall'] : 'no')) {
    return;
}

$tables = array(
    $wpdb->prefix . 'nfwc_services',
    $wpdb->prefix . 'nfwc_orders',
    $wpdb->prefix . 'nfwc_logs',
);
foreach ($tables as $table) {
    $wpdb->query($wpdb->prepare('DROP TABLE IF EXISTS %i', $table));
}

$options = array(
    'nfwc_version',
    'nfwc_settings',
    'nfwc_last_balance',
    'nfwc_last_services_sync',
    'nfwc_low_balance_in_danger',
);
foreach ($options as $option) {
    delete_option($option);
}

delete_transient('nfwc_low_balance_alert_sent');

$meta_like = $wpdb->esc_like('_nfwc_') . '%';
$wpdb->query($wpdb->prepare('DELETE FROM %i WHERE meta_key LIKE %s', $wpdb->postmeta, $meta_like));

$order_itemmeta_table = $wpdb->prefix . 'woocommerce_order_itemmeta';
$wpdb->query($wpdb->prepare('DELETE FROM %i WHERE meta_key LIKE %s', $order_itemmeta_table, $meta_like));

$transient_like = $wpdb->esc_like('_transient_nfwc_') . '%';
$transient_timeout_like = $wpdb->esc_like('_transient_timeout_nfwc_') . '%';
$wpdb->query(
    $wpdb->prepare(
        'DELETE FROM %i WHERE option_name LIKE %s OR option_name LIKE %s',
        $wpdb->options,
        $transient_like,
        $transient_timeout_like
    )
);
