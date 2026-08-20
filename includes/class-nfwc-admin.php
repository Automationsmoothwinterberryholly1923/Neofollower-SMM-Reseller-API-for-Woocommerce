<?php
if (!defined('ABSPATH')) {
    exit;
}

class NFWC_Admin {
    private $api;
    private $order;

    public function __construct($api, $order) {
        $this->api = $api;
        $this->order = $order;

        add_action('admin_menu', array($this, 'add_menu'));
        add_action('admin_post_nfwc_save_settings', array($this, 'handle_save_settings'));
        add_action('admin_post_nfwc_test_connection', array($this, 'handle_test_connection'));
        add_action('admin_post_nfwc_check_balance', array($this, 'handle_check_balance'));
        add_action('admin_post_nfwc_sync_services', array($this, 'handle_sync_services'));
        add_action('admin_post_nfwc_retry_order', array($this, 'handle_retry_order'));
        add_action('admin_post_nfwc_sync_order', array($this, 'handle_sync_order'));
        add_action('admin_post_nfwc_sync_all_statuses', array($this, 'handle_sync_all_statuses'));
        add_action('admin_post_nfwc_clear_logs', array($this, 'handle_clear_logs'));
        add_action('admin_post_nfwc_send_support_request', array($this, 'handle_send_support_request'));
        add_action('admin_notices', array($this, 'admin_notices'));
    }

    public function add_menu() {
        add_submenu_page(
            'woocommerce',
            __('Neofollower Fulfillment', 'neofollower-smm-reseller-api-for-woocommerce'),
            __('Neofollower', 'neofollower-smm-reseller-api-for-woocommerce'),
            'manage_woocommerce',
            'nfwc-bridge',
            array($this, 'render_page')
        );
    }

    public function admin_notices() {
        if (!isset($_GET['page']) || 'nfwc-bridge' !== sanitize_key(wp_unslash($_GET['page']))) {
            return;
        }
        if (!empty($_GET['nfwc_message'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html(wp_unslash($_GET['nfwc_message'])) . '</p></div>';
        }
        if (!empty($_GET['nfwc_error'])) {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html(wp_unslash($_GET['nfwc_error'])) . '</p></div>';
        }
    }

    private function redirect($tab = 'settings', $message = '', $error = '') {
        $url = admin_url('admin.php?page=nfwc-bridge&tab=' . sanitize_key($tab));
        if ($message) {
            $url = add_query_arg('nfwc_message', rawurlencode($message), $url);
        }
        if ($error) {
            $url = add_query_arg('nfwc_error', rawurlencode($error), $url);
        }
        wp_safe_redirect($url);
        exit;
    }

    public function handle_save_settings() {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Permission denied.', 'neofollower-smm-reseller-api-for-woocommerce'));
        }
        check_admin_referer('nfwc_save_settings');
        $settings = NFWC_DB::get_settings();
        $settings['api_key'] = isset($_POST['api_key']) ? sanitize_text_field(wp_unslash($_POST['api_key'])) : '';
        $settings['auto_status_sync'] = isset($_POST['auto_status_sync']) ? 'yes' : 'no';
        $settings['low_balance_email_enabled'] = isset($_POST['low_balance_email_enabled']) ? 'yes' : 'no';
        $settings['low_balance_threshold'] = isset($_POST['low_balance_threshold']) ? sanitize_text_field(wp_unslash($_POST['low_balance_threshold'])) : '';
        $settings['low_balance_email_recipient'] = isset($_POST['low_balance_email_recipient']) ? sanitize_email(wp_unslash($_POST['low_balance_email_recipient'])) : get_option('admin_email');
        $settings['low_balance_alert_interval_hours'] = isset($_POST['low_balance_alert_interval_hours']) ? max(1, absint(wp_unslash($_POST['low_balance_alert_interval_hours']))) : 12;
        $settings['pause_fulfillment_low_balance'] = isset($_POST['pause_fulfillment_low_balance']) ? 'yes' : 'no';
        $settings['failure_email_enabled'] = isset($_POST['failure_email_enabled']) ? 'yes' : 'no';
        $settings['failure_email_recipient'] = isset($_POST['failure_email_recipient']) ? sanitize_email(wp_unslash($_POST['failure_email_recipient'])) : get_option('admin_email');
        $settings['log_retention_days'] = isset($_POST['log_retention_days']) ? max(1, absint(wp_unslash($_POST['log_retention_days']))) : 30;
        $settings['delete_data_on_uninstall'] = isset($_POST['delete_data_on_uninstall']) ? 'yes' : 'no';
        NFWC_DB::update_settings($settings);
        $this->redirect('settings', __('Settings saved.', 'neofollower-smm-reseller-api-for-woocommerce'));
    }

    public function handle_test_connection() {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Permission denied.', 'neofollower-smm-reseller-api-for-woocommerce'));
        }
        check_admin_referer('nfwc_test_connection');
        $response = $this->api->balance();
        if ($response['ok']) {
            $balance = isset($response['json']['balance']) ? $response['json']['balance'] : '';
            $currency = isset($response['json']['currency']) ? $response['json']['currency'] : '';
            update_option('nfwc_last_balance', array('balance' => $balance, 'currency' => $currency, 'checked_at' => current_time('mysql'), 'raw' => $response['json']), false);
            $this->redirect('settings', sprintf(__('Connection successful. Balance: %s %s', 'neofollower-smm-reseller-api-for-woocommerce'), $balance, $currency));
        }
        $this->redirect('settings', '', $response['message'] ?: __('Connection failed.', 'neofollower-smm-reseller-api-for-woocommerce'));
    }

    public function handle_check_balance() {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Permission denied.', 'neofollower-smm-reseller-api-for-woocommerce'));
        }
        check_admin_referer('nfwc_check_balance');
        $result = NFWC_Plugin::check_balance_and_maybe_alert($this->api);
        $this->redirect('settings', $result['ok'] ? $result['message'] : '', $result['ok'] ? '' : $result['message']);
    }

    public function handle_sync_services() {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Permission denied.', 'neofollower-smm-reseller-api-for-woocommerce'));
        }
        check_admin_referer('nfwc_sync_services');
        $result = $this->api->sync_services();
        $this->redirect('services', $result['ok'] ? $result['message'] : '', $result['ok'] ? '' : $result['message']);
    }

    public function handle_retry_order() {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Permission denied.', 'neofollower-smm-reseller-api-for-woocommerce'));
        }
        check_admin_referer('nfwc_retry_order');
        $row_id = isset($_GET['row_id']) ? absint(wp_unslash($_GET['row_id'])) : 0;
        $result = $this->order->retry_order_row($row_id);
        $this->redirect('orders', $result['ok'] ? $result['message'] : '', $result['ok'] ? '' : $result['message']);
    }

    public function handle_sync_order() {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Permission denied.', 'neofollower-smm-reseller-api-for-woocommerce'));
        }
        check_admin_referer('nfwc_sync_order');
        $row_id = isset($_GET['row_id']) ? absint(wp_unslash($_GET['row_id'])) : 0;
        $result = $this->order->sync_one_row($row_id);
        $this->redirect('orders', $result['ok'] ? $result['message'] : '', $result['ok'] ? '' : $result['message']);
    }

    public function handle_sync_all_statuses() {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Permission denied.', 'neofollower-smm-reseller-api-for-woocommerce'));
        }
        check_admin_referer('nfwc_sync_all_statuses');
        $this->order->sync_statuses(100);
        $this->redirect('orders', __('Status sync finished.', 'neofollower-smm-reseller-api-for-woocommerce'));
    }

    public function handle_clear_logs() {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Permission denied.', 'neofollower-smm-reseller-api-for-woocommerce'));
        }
        check_admin_referer('nfwc_clear_logs');
        NFWC_DB::clear_logs();
        $this->redirect('logs', __('Logs cleared.', 'neofollower-smm-reseller-api-for-woocommerce'));
    }



    public function handle_send_support_request() {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Permission denied.', 'neofollower-smm-reseller-api-for-woocommerce'));
        }
        check_admin_referer('nfwc_send_support_request');

        if (!isset($_POST['nfwc_support_consent'])) {
            $this->redirect('support', '', __('Please confirm that you agree to send the listed diagnostic details to Neofollower support.', 'neofollower-smm-reseller-api-for-woocommerce'));
        }

        $message = isset($_POST['nfwc_support_message']) ? sanitize_textarea_field(wp_unslash($_POST['nfwc_support_message'])) : '';
        if ('' === trim($message)) {
            $this->redirect('support', '', __('Please enter your message before sending.', 'neofollower-smm-reseller-api-for-woocommerce'));
        }

        $site_name = sanitize_text_field(wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES));
        $site_url = home_url();
        $host = wp_parse_url($site_url, PHP_URL_HOST);
        $admin_email = sanitize_email(get_option('admin_email'));
        if (!$admin_email && $host) {
            $admin_email = 'wordpress@' . preg_replace('/^www\./', '', sanitize_text_field($host));
        }

        $current_user = wp_get_current_user();
        $sender_name = $current_user && $current_user->exists() ? sanitize_text_field($current_user->display_name) : $site_name;
        $sender_email = $current_user && is_email($current_user->user_email) ? $current_user->user_email : $admin_email;

        $subject = sprintf('[Neofollower Plugin Support] %s', $host ?: $site_name);
        $body = "A reseller sent a support / bug report from the Neofollower SMM Reseller API for WooCommerce plugin.\n\n";
        $body .= "Message:\n" . $message . "\n\n";
        $body .= "--- Website Details ---\n";
        $body .= "Site name: " . $site_name . "\n";
        $body .= "Site URL: " . $site_url . "\n";
        $body .= "Admin email: " . ($admin_email ?: 'Not available') . "\n";
        $body .= "Sender user: " . ($sender_name ?: 'Not available') . "\n";
        $body .= "Sender email: " . ($sender_email ?: 'Not available') . "\n";
        $body .= "Plugin version: " . NFWC_VERSION . "\n";
        $body .= "WordPress version: " . get_bloginfo('version') . "\n";
        $body .= "WooCommerce version: " . (defined('WC_VERSION') ? WC_VERSION : 'Not active') . "\n";
        $body .= "PHP version: " . PHP_VERSION . "\n";
        $body .= "Date: " . current_time('mysql') . "\n";

        $headers = array('Content-Type: text/plain; charset=UTF-8');
        if ($admin_email) {
            $headers[] = 'From: ' . $site_name . ' <' . $admin_email . '>';
        }
        if ($sender_email) {
            $headers[] = 'Reply-To: ' . $sender_name . ' <' . sanitize_email($sender_email) . '>';
        }

        $sent = wp_mail('info@neofollower.com', $subject, $body, $headers);
        NFWC_DB::log($sent ? 'info' : 'error', 'support_request', $sent ? 'Support request email sent.' : 'Support request email failed.', array(
            'recipient' => 'info@neofollower.com',
            'subject' => $subject,
            'site_url' => $site_url,
            'sender_email' => $sender_email,
        ));

        if ($sent) {
            $this->redirect('support', __('Your message was sent to Neofollower support.', 'neofollower-smm-reseller-api-for-woocommerce'));
        }
        $this->redirect('support', '', __('The message could not be sent. Please check your website email configuration and try again.', 'neofollower-smm-reseller-api-for-woocommerce'));
    }

    public function render_page() {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }
        $tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'settings';
        $tabs = array(
            'settings' => __('Settings', 'neofollower-smm-reseller-api-for-woocommerce'),
            'services' => __('Services', 'neofollower-smm-reseller-api-for-woocommerce'),
            'orders' => __('Orders', 'neofollower-smm-reseller-api-for-woocommerce'),
            'logs' => __('Logs', 'neofollower-smm-reseller-api-for-woocommerce'),
            'support' => __('Support / Report Bug', 'neofollower-smm-reseller-api-for-woocommerce'),
        );

        echo '<div class="wrap nfwc-admin">';
        echo '<h1>' . esc_html__('Neofollower – SMM Reseller API for WooCommerce', 'neofollower-smm-reseller-api-for-woocommerce') . '</h1>';
        echo '<nav class="nav-tab-wrapper">';
        foreach ($tabs as $key => $label) {
            $class = $tab === $key ? ' nav-tab-active' : '';
            echo '<a class="nav-tab' . esc_attr($class) . '" href="' . esc_url(admin_url('admin.php?page=nfwc-bridge&tab=' . $key)) . '">' . esc_html($label) . '</a>';
        }
        echo '</nav>';

        if ('services' === $tab) {
            $this->render_services_tab();
        } elseif ('orders' === $tab) {
            $this->render_orders_tab();
        } elseif ('logs' === $tab) {
            $this->render_logs_tab();
        } elseif ('support' === $tab) {
            $this->render_support_tab();
        } else {
            $this->render_settings_tab();
        }
        echo '</div>';
    }

    private function render_settings_tab() {
        $settings = NFWC_DB::get_settings();
        $last_balance = get_option('nfwc_last_balance', array());
        echo '<div class="nfwc-grid">';
        echo '<div class="nfwc-card nfwc-card-main">';
        echo '<h2>' . esc_html__('API & Automation Settings', 'neofollower-smm-reseller-api-for-woocommerce') . '</h2>';
        echo '<p><strong>' . esc_html__('Endpoint:', 'neofollower-smm-reseller-api-for-woocommerce') . '</strong> <code>' . esc_html($this->api->endpoint()) . '</code></p>';
        if (!empty($last_balance)) {
            echo '<p><strong>' . esc_html__('Last balance:', 'neofollower-smm-reseller-api-for-woocommerce') . '</strong> ' . esc_html($last_balance['balance'] ?? '') . ' ' . esc_html($last_balance['currency'] ?? '') . ' <span class="nfwc-muted">(' . esc_html($last_balance['checked_at'] ?? '') . ')</span></p>';
        }
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('nfwc_save_settings');
        echo '<input type="hidden" name="action" value="nfwc_save_settings" />';
        echo '<table class="form-table"><tbody>';
        echo '<tr><th scope="row"><label for="api_key">' . esc_html__('Neofollower API key', 'neofollower-smm-reseller-api-for-woocommerce') . '</label></th><td><input type="password" name="api_key" id="api_key" value="' . esc_attr($settings['api_key']) . '" class="regular-text" autocomplete="off" /><p class="description">' . wp_kses_post(sprintf(__('Saving an API key authorizes this plugin to communicate with the Neofollower API. Review the <a href="%1$s" target="_blank" rel="noopener noreferrer">Terms</a> and <a href="%2$s" target="_blank" rel="noopener noreferrer">Privacy Policy</a>.', 'neofollower-smm-reseller-api-for-woocommerce'), esc_url('https://neofollower.com/terms-and-conditions/'), esc_url('https://neofollower.com/privacy-policy/'))) . '</p></td></tr>';
        echo '<tr><th scope="row">' . esc_html__('Auto status sync', 'neofollower-smm-reseller-api-for-woocommerce') . '</th><td><label><input type="checkbox" name="auto_status_sync" value="yes" ' . checked('yes', $settings['auto_status_sync'], false) . ' /> ' . esc_html__('Sync active Neofollower orders every 10 minutes.', 'neofollower-smm-reseller-api-for-woocommerce') . '</label></td></tr>';
        echo '<tr><th scope="row">' . esc_html__('Low balance email alert', 'neofollower-smm-reseller-api-for-woocommerce') . '</th><td><label><input type="checkbox" name="low_balance_email_enabled" value="yes" ' . checked('yes', $settings['low_balance_email_enabled'], false) . ' /> ' . esc_html__('Email me when Neofollower balance is at or below the threshold.', 'neofollower-smm-reseller-api-for-woocommerce') . '</label></td></tr>';
        echo '<tr><th scope="row"><label for="low_balance_threshold">' . esc_html__('Low balance threshold', 'neofollower-smm-reseller-api-for-woocommerce') . '</label></th><td><input type="number" min="0" step="0.0001" name="low_balance_threshold" id="low_balance_threshold" value="' . esc_attr($settings['low_balance_threshold']) . '" class="regular-text" placeholder="10" /><p class="description">' . esc_html__('When balance is equal to or below this amount, the plugin sends an alert and marks balance as dangerous.', 'neofollower-smm-reseller-api-for-woocommerce') . '</p></td></tr>';
        echo '<tr><th scope="row"><label for="low_balance_email_recipient">' . esc_html__('Low balance recipient', 'neofollower-smm-reseller-api-for-woocommerce') . '</label></th><td><input type="email" name="low_balance_email_recipient" id="low_balance_email_recipient" value="' . esc_attr($settings['low_balance_email_recipient']) . '" class="regular-text" /></td></tr>';
        echo '<tr><th scope="row"><label for="low_balance_alert_interval_hours">' . esc_html__('Repeat alert interval', 'neofollower-smm-reseller-api-for-woocommerce') . '</label></th><td><input type="number" min="1" step="1" name="low_balance_alert_interval_hours" id="low_balance_alert_interval_hours" value="' . esc_attr($settings['low_balance_alert_interval_hours']) . '" class="small-text" /> ' . esc_html__('hours', 'neofollower-smm-reseller-api-for-woocommerce') . '</td></tr>';
        echo '<tr><th scope="row">' . esc_html__('Pause fulfillment on low balance', 'neofollower-smm-reseller-api-for-woocommerce') . '</th><td><label><input type="checkbox" name="pause_fulfillment_low_balance" value="yes" ' . checked('yes', $settings['pause_fulfillment_low_balance'], false) . ' /> ' . esc_html__('Do not submit paid WooCommerce orders to Neofollower while balance is in danger zone.', 'neofollower-smm-reseller-api-for-woocommerce') . '</label><p class="description">' . esc_html__('Default recommendation: keep this OFF unless the reseller understands orders may fail until balance is topped up.', 'neofollower-smm-reseller-api-for-woocommerce') . '</p></td></tr>';
        echo '<tr><th scope="row">' . esc_html__('Order failure email', 'neofollower-smm-reseller-api-for-woocommerce') . '</th><td><label><input type="checkbox" name="failure_email_enabled" value="yes" ' . checked('yes', $settings['failure_email_enabled'], false) . ' /> ' . esc_html__('Email me when an order fails after payment.', 'neofollower-smm-reseller-api-for-woocommerce') . '</label></td></tr>';
        echo '<tr><th scope="row"><label for="failure_email_recipient">' . esc_html__('Failure email recipient', 'neofollower-smm-reseller-api-for-woocommerce') . '</label></th><td><input type="email" name="failure_email_recipient" id="failure_email_recipient" value="' . esc_attr($settings['failure_email_recipient']) . '" class="regular-text" /></td></tr>';
        echo '<tr><th scope="row"><label for="log_retention_days">' . esc_html__('Log retention', 'neofollower-smm-reseller-api-for-woocommerce') . '</label></th><td><input type="number" min="1" step="1" name="log_retention_days" id="log_retention_days" value="' . esc_attr($settings['log_retention_days']) . '" class="small-text" /> ' . esc_html__('days', 'neofollower-smm-reseller-api-for-woocommerce') . '</td></tr>';
        echo '<tr><th scope="row">' . esc_html__('Delete data on uninstall', 'neofollower-smm-reseller-api-for-woocommerce') . '</th><td><label><input type="checkbox" name="delete_data_on_uninstall" value="yes" ' . checked('yes', $settings['delete_data_on_uninstall'], false) . ' /> ' . esc_html__('Permanently remove plugin settings, synced services, fulfillment records, logs, and private Neofollower metadata when the plugin is deleted.', 'neofollower-smm-reseller-api-for-woocommerce') . '</label><p class="description">' . esc_html__('Leave this off if you may reinstall the plugin and want to keep its data.', 'neofollower-smm-reseller-api-for-woocommerce') . '</p></td></tr>';
        echo '</tbody></table>';
        submit_button(__('Save settings', 'neofollower-smm-reseller-api-for-woocommerce'));
        echo '</form>';
        echo '<p>';
        echo '<a class="button button-secondary" href="' . esc_url(wp_nonce_url(admin_url('admin-post.php?action=nfwc_test_connection'), 'nfwc_test_connection')) . '">' . esc_html__('Test connection / balance', 'neofollower-smm-reseller-api-for-woocommerce') . '</a> ';
        echo '<a class="button button-secondary" href="' . esc_url(wp_nonce_url(admin_url('admin-post.php?action=nfwc_check_balance'), 'nfwc_check_balance')) . '">' . esc_html__('Run balance alert check', 'neofollower-smm-reseller-api-for-woocommerce') . '</a> ';
        echo '<a class="button button-secondary" href="' . esc_url(wp_nonce_url(admin_url('admin-post.php?action=nfwc_sync_services'), 'nfwc_sync_services')) . '">' . esc_html__('Sync services now', 'neofollower-smm-reseller-api-for-woocommerce') . '</a>';
        echo '</p>';
        echo '</div>';
        $this->render_health_card();
        echo '</div>';
    }

    private function render_health_card() {
        $settings = NFWC_DB::get_settings();
        $checks = array();
        $checks[] = array('label' => __('API key configured', 'neofollower-smm-reseller-api-for-woocommerce'), 'ok' => '' !== trim((string) $settings['api_key']));
        $checks[] = array('label' => __('Status sync cron scheduled', 'neofollower-smm-reseller-api-for-woocommerce'), 'ok' => (bool) wp_next_scheduled('nfwc_cron_sync_statuses'));
        $checks[] = array('label' => __('Balance alert cron scheduled', 'neofollower-smm-reseller-api-for-woocommerce'), 'ok' => (bool) wp_next_scheduled('nfwc_cron_check_balance'));
        $checks[] = array('label' => __('Services synced', 'neofollower-smm-reseller-api-for-woocommerce'), 'ok' => NFWC_DB::service_count() > 0, 'note' => sprintf(__('%d services', 'neofollower-smm-reseller-api-for-woocommerce'), NFWC_DB::service_count()));
        $issue_count = NFWC_DB::product_issue_count();
        $checks[] = array('label' => __('Enabled products have service IDs', 'neofollower-smm-reseller-api-for-woocommerce'), 'ok' => 0 === $issue_count, 'note' => $issue_count ? sprintf(__('%d issue(s)', 'neofollower-smm-reseller-api-for-woocommerce'), $issue_count) : __('OK', 'neofollower-smm-reseller-api-for-woocommerce'));
        $checks[] = array('label' => __('Low balance threshold configured', 'neofollower-smm-reseller-api-for-woocommerce'), 'ok' => empty($settings['low_balance_threshold']) || is_numeric($settings['low_balance_threshold']));

        echo '<div class="nfwc-card nfwc-health-card">';
        echo '<h2>' . esc_html__('Publish Readiness', 'neofollower-smm-reseller-api-for-woocommerce') . '</h2>';
        echo '<ul class="nfwc-health-list">';
        foreach ($checks as $check) {
            $ok = !empty($check['ok']);
            echo '<li class="' . esc_attr($ok ? 'is-ok' : 'is-bad') . '"><span>' . esc_html($ok ? '✓' : '!') . '</span><strong>' . esc_html($check['label']) . '</strong>';
            if (!empty($check['note'])) {
                echo '<em>' . esc_html($check['note']) . '</em>';
            }
            echo '</li>';
        }
        echo '</ul>';
        echo '</div>';
    }

    private function render_services_tab() {
        global $wpdb;
        $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
        $page_num = max(1, isset($_GET['paged']) ? absint(wp_unslash($_GET['paged'])) : 1);
        $per_page = 50;
        $offset = ($page_num - 1) * $per_page;

        $table = NFWC_DB::services_table();

        if ($search) {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $count = (int) $wpdb->get_var(
                $wpdb->prepare(
                    'SELECT COUNT(*) FROM %i WHERE service_id LIKE %s OR name LIKE %s OR category LIKE %s OR type LIKE %s',
                    $table,
                    $like,
                    $like,
                    $like,
                    $like
                )
            );
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    'SELECT * FROM %i WHERE service_id LIKE %s OR name LIKE %s OR category LIKE %s OR type LIKE %s ORDER BY name ASC LIMIT %d OFFSET %d',
                    $table,
                    $like,
                    $like,
                    $like,
                    $like,
                    $per_page,
                    $offset
                )
            );
        } else {
            $count = (int) $wpdb->get_var(
                $wpdb->prepare('SELECT COUNT(*) FROM %i', $table)
            );
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    'SELECT * FROM %i ORDER BY name ASC LIMIT %d OFFSET %d',
                    $table,
                    $per_page,
                    $offset
                )
            );
        }
        $last_sync = get_option('nfwc_last_services_sync', '');

        echo '<div class="nfwc-card">';
        echo '<h2>' . esc_html__('Synced Services', 'neofollower-smm-reseller-api-for-woocommerce') . '</h2>';
        echo '<p><strong>' . esc_html__('Last sync:', 'neofollower-smm-reseller-api-for-woocommerce') . '</strong> ' . esc_html($last_sync ?: __('Never', 'neofollower-smm-reseller-api-for-woocommerce')) . '</p>';
        echo '<p><a class="button button-primary" href="' . esc_url(wp_nonce_url(admin_url('admin-post.php?action=nfwc_sync_services'), 'nfwc_sync_services')) . '">' . esc_html__('Sync services now', 'neofollower-smm-reseller-api-for-woocommerce') . '</a></p>';
        echo '<form method="get"><input type="hidden" name="page" value="nfwc-bridge" /><input type="hidden" name="tab" value="services" /><p class="search-box"><input type="search" name="s" value="' . esc_attr($search) . '" placeholder="' . esc_attr__('Search services', 'neofollower-smm-reseller-api-for-woocommerce') . '" /> <input type="submit" class="button" value="' . esc_attr__('Search', 'neofollower-smm-reseller-api-for-woocommerce') . '" /></p></form>';
        echo '</div>';

        echo '<table class="widefat striped"><thead><tr><th>' . esc_html__('Service ID', 'neofollower-smm-reseller-api-for-woocommerce') . '</th><th>' . esc_html__('Name', 'neofollower-smm-reseller-api-for-woocommerce') . '</th><th>' . esc_html__('Type', 'neofollower-smm-reseller-api-for-woocommerce') . '</th><th>' . esc_html__('Category', 'neofollower-smm-reseller-api-for-woocommerce') . '</th><th>' . esc_html__('Rate', 'neofollower-smm-reseller-api-for-woocommerce') . '</th><th>' . esc_html__('Min', 'neofollower-smm-reseller-api-for-woocommerce') . '</th><th>' . esc_html__('Max', 'neofollower-smm-reseller-api-for-woocommerce') . '</th><th>' . esc_html__('Updated', 'neofollower-smm-reseller-api-for-woocommerce') . '</th></tr></thead><tbody>';
        if (!$rows) {
            echo '<tr><td colspan="8">' . esc_html__('No services found. Sync services first.', 'neofollower-smm-reseller-api-for-woocommerce') . '</td></tr>';
        }
        foreach ($rows as $row) {
            echo '<tr><td><code>' . esc_html($row->service_id) . '</code></td><td>' . esc_html($row->name) . '</td><td>' . esc_html($row->type) . '</td><td>' . esc_html($row->category) . '</td><td>' . esc_html($row->rate) . '</td><td>' . esc_html($row->min_qty) . '</td><td>' . esc_html($row->max_qty) . '</td><td>' . esc_html($row->updated_at) . '</td></tr>';
        }
        echo '</tbody></table>';

        $total_pages = max(1, (int) ceil($count / $per_page));
        if ($total_pages > 1) {
            echo '<p class="tablenav-pages">';
            $start = max(1, $page_num - 5);
            $end = min($total_pages, $page_num + 5);
            for ($i = $start; $i <= $end; $i++) {
                if ($i === $page_num) {
                    echo '<span class="button button-primary">' . esc_html($i) . '</span> ';
                } else {
                    echo '<a class="button" href="' . esc_url(add_query_arg(array('page' => 'nfwc-bridge', 'tab' => 'services', 'paged' => $i, 's' => $search), admin_url('admin.php'))) . '">' . esc_html($i) . '</a> ';
                }
            }
            echo '</p>';
        }
    }

    private function render_orders_tab() {
        global $wpdb;
        $status = isset($_GET['status_filter']) ? sanitize_text_field(wp_unslash($_GET['status_filter'])) : '';
        $table = NFWC_DB::orders_table();
        if ($status) {
            $rows = $wpdb->get_results(
                $wpdb->prepare('SELECT * FROM %i WHERE status = %s ORDER BY id DESC LIMIT 200', $table, $status)
            );
        } else {
            $rows = $wpdb->get_results(
                $wpdb->prepare('SELECT * FROM %i ORDER BY id DESC LIMIT 200', $table)
            );
        }

        echo '<div class="nfwc-card">';
        echo '<h2>' . esc_html__('Fulfillment Orders', 'neofollower-smm-reseller-api-for-woocommerce') . '</h2>';
        echo '<p><a class="button button-secondary" href="' . esc_url(wp_nonce_url(admin_url('admin-post.php?action=nfwc_sync_all_statuses'), 'nfwc_sync_all_statuses')) . '">' . esc_html__('Sync active statuses now', 'neofollower-smm-reseller-api-for-woocommerce') . '</a></p>';
        echo '</div>';

        echo '<table class="widefat striped"><thead><tr><th>' . esc_html__('Woo Order', 'neofollower-smm-reseller-api-for-woocommerce') . '</th><th>' . esc_html__('Item', 'neofollower-smm-reseller-api-for-woocommerce') . '</th><th>' . esc_html__('Service', 'neofollower-smm-reseller-api-for-woocommerce') . '</th><th>' . esc_html__('Neofollower Order', 'neofollower-smm-reseller-api-for-woocommerce') . '</th><th>' . esc_html__('Type', 'neofollower-smm-reseller-api-for-woocommerce') . '</th><th>' . esc_html__('Qty', 'neofollower-smm-reseller-api-for-woocommerce') . '</th><th>' . esc_html__('Status', 'neofollower-smm-reseller-api-for-woocommerce') . '</th><th>' . esc_html__('API', 'neofollower-smm-reseller-api-for-woocommerce') . '</th><th class="column-actions">' . esc_html__('Actions', 'neofollower-smm-reseller-api-for-woocommerce') . '</th></tr></thead><tbody>';
        if (!$rows) {
            echo '<tr><td colspan="9">' . esc_html__('No fulfillment orders yet.', 'neofollower-smm-reseller-api-for-woocommerce') . '</td></tr>';
        }
        foreach ($rows as $row) {
            $order_url = admin_url('post.php?post=' . absint($row->woo_order_id) . '&action=edit');
            $woo_order = wc_get_order($row->woo_order_id);
            if ($woo_order && function_exists('wc_get_order_admin_edit_url')) {
                $order_url = wc_get_order_admin_edit_url($woo_order);
            }
            $sync_url = wp_nonce_url(admin_url('admin-post.php?action=nfwc_sync_order&row_id=' . absint($row->id)), 'nfwc_sync_order');
            $retry_url = wp_nonce_url(admin_url('admin-post.php?action=nfwc_retry_order&row_id=' . absint($row->id)), 'nfwc_retry_order');
            echo '<tr>';
            echo '<td><a href="' . esc_url($order_url) . '">#' . esc_html($row->woo_order_id) . '</a></td>';
            echo '<td>#' . esc_html($row->woo_order_item_id) . '</td>';
            echo '<td><code>' . esc_html($row->service_id) . '</code><br><small>' . esc_html(wp_trim_words($row->link, 8, '...')) . '</small></td>';
            echo '<td>' . ($row->neo_order_id ? '<code>' . esc_html($row->neo_order_id) . '</code>' : '—') . '</td>';
            echo '<td>' . esc_html($row->service_type) . '</td>';
            echo '<td>' . esc_html($row->quantity) . '</td>';
            echo '<td><span class="nfwc-badge ' . esc_attr(strtolower($row->status)) . '">' . esc_html($row->status) . '</span><br><small>' . esc_html($row->last_sync_at ?: '') . '</small></td>';
            echo '<td>' . esc_html($row->api_http_code) . '<br><small>' . esc_html(wp_trim_words($row->api_message, 12, '...')) . '</small></td>';
            echo '<td class="column-actions"><a class="button button-small" href="' . esc_url($sync_url) . '">' . esc_html__('Sync', 'neofollower-smm-reseller-api-for-woocommerce') . '</a> <a class="button button-small" href="' . esc_url($retry_url) . '">' . esc_html__('Retry', 'neofollower-smm-reseller-api-for-woocommerce') . '</a></td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    }

    private function render_logs_tab() {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare('SELECT * FROM %i ORDER BY id DESC LIMIT 200', NFWC_DB::logs_table()));
        echo '<div class="nfwc-card"><h2>' . esc_html__('Debug Logs', 'neofollower-smm-reseller-api-for-woocommerce') . '</h2><p class="nfwc-muted">' . esc_html__('Latest 200 plugin logs. Old logs are cleaned automatically based on the retention setting.', 'neofollower-smm-reseller-api-for-woocommerce') . '</p><p><a class="button button-secondary" href="' . esc_url(wp_nonce_url(admin_url('admin-post.php?action=nfwc_clear_logs'), 'nfwc_clear_logs')) . '">' . esc_html__('Clear logs', 'neofollower-smm-reseller-api-for-woocommerce') . '</a></p></div>';
        echo '<table class="widefat striped"><thead><tr><th>' . esc_html__('Date', 'neofollower-smm-reseller-api-for-woocommerce') . '</th><th>' . esc_html__('Level', 'neofollower-smm-reseller-api-for-woocommerce') . '</th><th>' . esc_html__('Context', 'neofollower-smm-reseller-api-for-woocommerce') . '</th><th>' . esc_html__('Message', 'neofollower-smm-reseller-api-for-woocommerce') . '</th><th>' . esc_html__('Data', 'neofollower-smm-reseller-api-for-woocommerce') . '</th></tr></thead><tbody>';
        if (!$rows) {
            echo '<tr><td colspan="5">' . esc_html__('No logs yet.', 'neofollower-smm-reseller-api-for-woocommerce') . '</td></tr>';
        }
        foreach ($rows as $row) {
            echo '<tr><td>' . esc_html($row->created_at) . '</td><td>' . esc_html($row->level) . '</td><td>' . esc_html($row->context) . '</td><td>' . esc_html($row->message) . '</td><td><textarea readonly rows="2" class="nfwc-log-data">' . esc_textarea($row->data) . '</textarea></td></tr>';
        }
        echo '</tbody></table>';
    }


    private function render_support_tab() {
        $site_url = home_url();
        $host = wp_parse_url($site_url, PHP_URL_HOST);
        $admin_email = sanitize_email(get_option('admin_email'));
        $from_email = $admin_email ?: ($host ? 'wordpress@' . preg_replace('/^www\./', '', sanitize_text_field($host)) : '');

        echo '<div class="nfwc-grid">';
        echo '<div class="nfwc-card nfwc-card-main nfwc-support-card">';
        echo '<h2>' . esc_html__('Support / Report Bug', 'neofollower-smm-reseller-api-for-woocommerce') . '</h2>';
        echo '<p class="nfwc-muted">' . esc_html__('Send a support request or bug report directly to Neofollower. Your message will be emailed to info@neofollower.com together with basic website and plugin details so support can understand the issue faster.', 'neofollower-smm-reseller-api-for-woocommerce') . '</p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('nfwc_send_support_request');
        echo '<input type="hidden" name="action" value="nfwc_send_support_request" />';
        echo '<table class="form-table"><tbody>';
        echo '<tr><th scope="row"><label for="nfwc_support_message">' . esc_html__('Message', 'neofollower-smm-reseller-api-for-woocommerce') . '</label></th><td><textarea name="nfwc_support_message" id="nfwc_support_message" rows="10" class="large-text" required placeholder="' . esc_attr__('Explain the issue, what you expected, and what happened...', 'neofollower-smm-reseller-api-for-woocommerce') . '"></textarea><p class="description">' . esc_html__('Include product name, WooCommerce order number, screenshots link, or steps to reproduce if relevant.', 'neofollower-smm-reseller-api-for-woocommerce') . '</p></td></tr>';
        echo '<tr><th scope="row">' . esc_html__('Consent', 'neofollower-smm-reseller-api-for-woocommerce') . '</th><td><label><input type="checkbox" name="nfwc_support_consent" value="yes" required /> ' . esc_html__('I agree to send my message and the listed website, user, WordPress, WooCommerce, PHP, and plugin details to Neofollower support.', 'neofollower-smm-reseller-api-for-woocommerce') . '</label></td></tr>';
        echo '</tbody></table>';
        submit_button(__('Send to Neofollower Support', 'neofollower-smm-reseller-api-for-woocommerce'), 'primary', 'submit', true, array('id' => 'nfwc-support-submit'));
        echo '</form>';
        echo '</div>';

        echo '<div class="nfwc-card nfwc-support-info">';
        echo '<h2>' . esc_html__('Email Details', 'neofollower-smm-reseller-api-for-woocommerce') . '</h2>';
        echo '<ul class="nfwc-support-details">';
        echo '<li><strong>' . esc_html__('To:', 'neofollower-smm-reseller-api-for-woocommerce') . '</strong> <code>info@neofollower.com</code></li>';
        echo '<li><strong>' . esc_html__('Subject:', 'neofollower-smm-reseller-api-for-woocommerce') . '</strong> <code>[Neofollower Plugin Support] ' . esc_html($host ?: get_bloginfo('name')) . '</code></li>';
        echo '<li><strong>' . esc_html__('From:', 'neofollower-smm-reseller-api-for-woocommerce') . '</strong> <code>' . esc_html($from_email ?: __('WordPress default sender', 'neofollower-smm-reseller-api-for-woocommerce')) . '</code></li>';
        echo '<li><strong>' . esc_html__('Reply-To:', 'neofollower-smm-reseller-api-for-woocommerce') . '</strong> <code>' . esc_html($admin_email ?: __('Admin user email if available', 'neofollower-smm-reseller-api-for-woocommerce')) . '</code></li>';
        echo '</ul>';
        echo '<p class="nfwc-muted">' . esc_html__('The message is sent using this website\'s WordPress email system. If emails do not arrive, configure SMTP on the reseller website.', 'neofollower-smm-reseller-api-for-woocommerce') . '</p>';
        echo '</div>';
        echo '</div>';
    }
}
