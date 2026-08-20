<?php
if (!defined('ABSPATH')) {
    exit;
}

final class NFWC_Plugin {
    private static $instance = null;

    public $api;
    public $admin;
    public $product;
    public $order;

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->maybe_upgrade();
        add_filter('cron_schedules', array($this, 'add_cron_schedules'));
        add_action('nfwc_cron_check_balance', array($this, 'cron_check_balance'));
        add_action('nfwc_cron_cleanup_logs', array($this, 'cron_cleanup_logs'));
        add_filter('plugin_action_links_' . NFWC_BASENAME, array($this, 'plugin_action_links'));
        add_action('admin_init', array($this, 'add_privacy_policy_content'));

        if (!$this->is_woocommerce_active()) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return;
        }

        $this->api = new NFWC_API();
        $this->product = new NFWC_Product($this->api);
        $this->order = new NFWC_Order($this->api);

        if (is_admin()) {
            $this->admin = new NFWC_Admin($this->api, $this->order);
        }
    }

    private function maybe_upgrade() {
        $stored_version = get_option('nfwc_version', '');
        if ($stored_version !== NFWC_VERSION) {
            NFWC_DB::update_settings(NFWC_DB::get_settings());
            update_option('nfwc_version', NFWC_VERSION, false);
        }
        self::ensure_scheduled_events();
    }

    public function is_woocommerce_active() {
        return class_exists('WooCommerce') && function_exists('wc_get_order');
    }

    public function woocommerce_missing_notice() {
        if (!current_user_can('activate_plugins')) {
            return;
        }

        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || 'plugins' !== $screen->id) {
            return;
        }

        echo '<div class="notice notice-error"><p>' . wp_kses_post(__('<strong>Neofollower – SMM Reseller API for WooCommerce</strong> requires WooCommerce to be installed and active.', 'neofollower-smm-reseller-api-for-woocommerce')) . '</p></div>';
    }

    public function plugin_action_links($links) {
        $settings_url = admin_url('admin.php?page=nfwc-bridge');
        array_unshift(
            $links,
            '<a href="' . esc_url($settings_url) . '">' . esc_html__('Settings', 'neofollower-smm-reseller-api-for-woocommerce') . '</a>'
        );
        return $links;
    }

    public function add_privacy_policy_content() {
        if (!function_exists('wp_add_privacy_policy_content')) {
            return;
        }

        $content = '<p>' . esc_html__('When a store administrator configures a Neofollower API key, this plugin can send service requests to the Neofollower API for service synchronization, balance checks, paid order submission, and order-status synchronization.', 'neofollower-smm-reseller-api-for-woocommerce') . '</p>';
        $content .= '<p>' . esc_html__('Depending on the selected service and action, transmitted information can include the API key, service ID, public profile or content URL, username, quantity, custom comments, drip-feed settings, and Neofollower order ID. The plugin stores fulfillment information in WooCommerce order item metadata and custom database tables for order processing, status display, troubleshooting, and logs.', 'neofollower-smm-reseller-api-for-woocommerce') . '</p>';
        $content .= '<p>' . esc_html__('The optional support form sends the administrator-entered message plus basic site, user, WordPress, WooCommerce, PHP, and plugin-version details only after the administrator checks the consent box and submits the form.', 'neofollower-smm-reseller-api-for-woocommerce') . '</p>';
        $content .= '<p>' . wp_kses_post(sprintf(__('Review the Neofollower <a href="%1$s">Privacy Policy</a> and <a href="%2$s">Terms and Conditions</a>.', 'neofollower-smm-reseller-api-for-woocommerce'), esc_url('https://neofollower.com/privacy-policy/'), esc_url('https://neofollower.com/terms-and-conditions/'))) . '</p>';

        wp_add_privacy_policy_content(
            __('Neofollower – SMM Reseller API for WooCommerce', 'neofollower-smm-reseller-api-for-woocommerce'),
            wp_kses_post($content)
        );
    }

    public static function add_cron_schedules_static($schedules) {
        if (!isset($schedules['nfwc_10_minutes'])) {
            $schedules['nfwc_10_minutes'] = array(
                'interval' => 10 * MINUTE_IN_SECONDS,
                'display'  => __('Every 10 minutes', 'neofollower-smm-reseller-api-for-woocommerce'),
            );
        }
        if (!isset($schedules['nfwc_daily'])) {
            $schedules['nfwc_daily'] = array(
                'interval' => DAY_IN_SECONDS,
                'display'  => __('Once daily', 'neofollower-smm-reseller-api-for-woocommerce'),
            );
        }
        return $schedules;
    }

    public function add_cron_schedules($schedules) {
        return self::add_cron_schedules_static($schedules);
    }

    public static function activate() {
        add_filter('cron_schedules', array(__CLASS__, 'add_cron_schedules_static'));
        self::ensure_scheduled_events();
    }

    private static function ensure_scheduled_events() {
        if (!wp_next_scheduled('nfwc_cron_sync_statuses')) {
            wp_schedule_event(time() + 300, 'nfwc_10_minutes', 'nfwc_cron_sync_statuses');
        }
        if (!wp_next_scheduled('nfwc_cron_check_balance')) {
            wp_schedule_event(time() + 600, 'nfwc_10_minutes', 'nfwc_cron_check_balance');
        }
        if (!wp_next_scheduled('nfwc_cron_cleanup_logs')) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'nfwc_daily', 'nfwc_cron_cleanup_logs');
        }
    }

    public static function deactivate() {
        self::unschedule('nfwc_cron_sync_statuses');
        self::unschedule('nfwc_cron_check_balance');
        self::unschedule('nfwc_cron_cleanup_logs');
    }

    private static function unschedule($hook) {
        $timestamp = wp_next_scheduled($hook);
        while ($timestamp) {
            wp_unschedule_event($timestamp, $hook);
            $timestamp = wp_next_scheduled($hook);
        }
    }

    public function cron_cleanup_logs() {
        $settings = NFWC_DB::get_settings();
        $days = isset($settings['log_retention_days']) ? absint($settings['log_retention_days']) : 30;
        if ($days > 0) {
            NFWC_DB::cleanup_logs($days);
        }
    }

    public function cron_check_balance() {
        if (!$this->api instanceof NFWC_API) {
            $this->api = new NFWC_API();
        }
        return self::check_balance_and_maybe_alert($this->api);
    }

    public static function check_balance_and_maybe_alert($api) {
        $settings = NFWC_DB::get_settings();
        $threshold = isset($settings['low_balance_threshold']) && is_numeric($settings['low_balance_threshold']) ? (float) $settings['low_balance_threshold'] : 0;
        if ($threshold <= 0 || 'yes' !== $settings['low_balance_email_enabled']) {
            return array('ok' => true, 'message' => __('Low balance email alert is disabled or threshold is empty.', 'neofollower-smm-reseller-api-for-woocommerce'));
        }

        if (!$api instanceof NFWC_API) {
            $api = new NFWC_API();
        }
        $response = $api->balance();
        if (!$response['ok']) {
            NFWC_DB::log('error', 'balance_check', $response['message'] ?: 'Balance check failed.', $response);
            return array('ok' => false, 'message' => $response['message'] ?: __('Balance check failed.', 'neofollower-smm-reseller-api-for-woocommerce'));
        }

        $balance = isset($response['json']['balance']) && is_numeric($response['json']['balance']) ? (float) $response['json']['balance'] : null;
        $currency = isset($response['json']['currency']) ? sanitize_text_field((string) $response['json']['currency']) : '';
        update_option('nfwc_last_balance', array(
            'balance' => null === $balance ? '' : $balance,
            'currency' => $currency,
            'checked_at' => current_time('mysql'),
            'raw' => $response['json'],
        ), false);

        if (null === $balance) {
            return array('ok' => false, 'message' => __('Could not read balance from API response.', 'neofollower-smm-reseller-api-for-woocommerce'));
        }

        if ($balance > $threshold) {
            delete_transient('nfwc_low_balance_alert_sent');
            update_option('nfwc_low_balance_in_danger', 'no', false);
            return array('ok' => true, 'message' => sprintf(__('Balance is safe: %s %s.', 'neofollower-smm-reseller-api-for-woocommerce'), $balance, $currency));
        }

        update_option('nfwc_low_balance_in_danger', 'yes', false);
        $interval_hours = max(1, absint($settings['low_balance_alert_interval_hours']));
        if (get_transient('nfwc_low_balance_alert_sent')) {
            return array('ok' => true, 'message' => __('Balance is low, but alert was already sent recently.', 'neofollower-smm-reseller-api-for-woocommerce'));
        }

        $recipient = sanitize_email($settings['low_balance_email_recipient']);
        if (!$recipient) {
            $recipient = get_option('admin_email');
        }

        $subject = sprintf(__('Neofollower balance is low: %s %s', 'neofollower-smm-reseller-api-for-woocommerce'), $balance, $currency);
        $message = sprintf(
            "Your Neofollower balance is now %1\$s %2\$s.\n\nThe low balance threshold is %3\$s. Please top up your Neofollower account to avoid failed WooCommerce fulfillment orders.\n\nSite: %4\$s",
            $balance,
            $currency,
            $threshold,
            home_url()
        );
        $sent = wp_mail($recipient, $subject, $message);
        set_transient('nfwc_low_balance_alert_sent', 1, $interval_hours * HOUR_IN_SECONDS);
        NFWC_DB::log($sent ? 'warning' : 'error', 'balance_alert', $sent ? 'Low balance email sent.' : 'Low balance email could not be sent.', array(
            'balance' => $balance,
            'currency' => $currency,
            'threshold' => $threshold,
            'recipient' => $recipient,
        ));

        return array('ok' => (bool) $sent, 'message' => $sent ? __('Low balance alert email sent.', 'neofollower-smm-reseller-api-for-woocommerce') : __('Low balance alert email could not be sent.', 'neofollower-smm-reseller-api-for-woocommerce'));
    }
}
