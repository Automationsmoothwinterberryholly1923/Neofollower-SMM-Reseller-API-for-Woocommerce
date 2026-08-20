<?php
if (!defined('ABSPATH')) {
    exit;
}

class NFWC_DB {
    public static function services_table() {
        global $wpdb;
        return $wpdb->prefix . 'nfwc_services';
    }

    public static function orders_table() {
        global $wpdb;
        return $wpdb->prefix . 'nfwc_orders';
    }

    public static function logs_table() {
        global $wpdb;
        return $wpdb->prefix . 'nfwc_logs';
    }

    public static function default_settings() {
        return array(
            'api_key' => '',
            'auto_status_sync' => 'yes',
            'low_balance_threshold' => '',
            'low_balance_email_enabled' => 'yes',
            'low_balance_email_recipient' => get_option('admin_email'),
            'low_balance_alert_interval_hours' => 12,
            'pause_fulfillment_low_balance' => 'no',
            'failure_email_enabled' => 'yes',
            'failure_email_recipient' => get_option('admin_email'),
            'log_retention_days' => 30,
            'delete_data_on_uninstall' => 'no',
        );
    }

    public static function activate() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();
        $services = self::services_table();
        $orders = self::orders_table();
        $logs = self::logs_table();

        $sql_services = "CREATE TABLE {$services} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            service_id VARCHAR(64) NOT NULL,
            name TEXT NOT NULL,
            type VARCHAR(80) DEFAULT '' NOT NULL,
            category TEXT NULL,
            rate DECIMAL(20,8) NULL,
            min_qty BIGINT(20) NULL,
            max_qty BIGINT(20) NULL,
            refill VARCHAR(20) DEFAULT '' NOT NULL,
            cancel VARCHAR(20) DEFAULT '' NOT NULL,
            raw LONGTEXT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY service_id (service_id),
            KEY type (type),
            KEY updated_at (updated_at)
        ) {$charset_collate};";

        $sql_orders = "CREATE TABLE {$orders} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            woo_order_id BIGINT(20) UNSIGNED NOT NULL,
            woo_order_item_id BIGINT(20) UNSIGNED NOT NULL,
            product_id BIGINT(20) UNSIGNED NOT NULL,
            service_id VARCHAR(64) NOT NULL,
            neo_order_id VARCHAR(128) DEFAULT '' NOT NULL,
            service_type VARCHAR(80) DEFAULT '' NOT NULL,
            link TEXT NULL,
            quantity BIGINT(20) NULL,
            comments LONGTEXT NULL,
            runs BIGINT(20) NULL,
            interval_minutes BIGINT(20) NULL,
            status VARCHAR(80) DEFAULT 'pending' NOT NULL,
            api_http_code INT NULL,
            api_message TEXT NULL,
            raw_response LONGTEXT NULL,
            last_sync_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY woo_order_item_id (woo_order_item_id),
            KEY woo_order_id (woo_order_id),
            KEY neo_order_id (neo_order_id),
            KEY status (status),
            KEY updated_at (updated_at)
        ) {$charset_collate};";

        $sql_logs = "CREATE TABLE {$logs} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            level VARCHAR(20) DEFAULT 'info' NOT NULL,
            context VARCHAR(100) DEFAULT '' NOT NULL,
            message TEXT NOT NULL,
            data LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY level (level),
            KEY context (context),
            KEY created_at (created_at)
        ) {$charset_collate};";

        dbDelta($sql_services);
        dbDelta($sql_orders);
        dbDelta($sql_logs);

        add_option('nfwc_version', NFWC_VERSION);
        add_option('nfwc_settings', self::default_settings(), '', false);
        self::update_settings(self::get_settings());
    }

    public static function log($level, $context, $message, $data = null) {
        global $wpdb;
        $wpdb->insert(
            self::logs_table(),
            array(
                'level' => sanitize_key($level),
                'context' => sanitize_text_field($context),
                'message' => wp_strip_all_tags((string) $message),
                'data' => null === $data ? null : wp_json_encode($data),
                'created_at' => current_time('mysql'),
            ),
            array('%s', '%s', '%s', '%s', '%s')
        );
    }

    public static function get_settings() {
        $settings = get_option('nfwc_settings', array());
        return wp_parse_args(is_array($settings) ? $settings : array(), self::default_settings());
    }

    public static function update_settings($settings) {
        $current = self::get_settings();
        $settings = wp_parse_args(is_array($settings) ? $settings : array(), $current);
        update_option('nfwc_settings', $settings, false);
    }

    public static function upsert_service($service) {
        global $wpdb;
        $service_id = isset($service['service']) ? (string) $service['service'] : '';
        if ('' === $service_id) {
            return false;
        }

        $data = array(
            'service_id' => sanitize_text_field($service_id),
            'name' => isset($service['name']) ? wp_strip_all_tags((string) $service['name']) : '',
            'type' => isset($service['type']) ? sanitize_text_field((string) $service['type']) : '',
            'category' => isset($service['category']) ? wp_strip_all_tags((string) $service['category']) : '',
            'rate' => isset($service['rate']) && is_numeric($service['rate']) ? (float) $service['rate'] : null,
            'min_qty' => isset($service['min']) && is_numeric($service['min']) ? (int) $service['min'] : null,
            'max_qty' => isset($service['max']) && is_numeric($service['max']) ? (int) $service['max'] : null,
            'refill' => isset($service['refill']) ? sanitize_text_field((string) $service['refill']) : '',
            'cancel' => isset($service['cancel']) ? sanitize_text_field((string) $service['cancel']) : '',
            'raw' => wp_json_encode($service),
            'updated_at' => current_time('mysql'),
        );

        $existing_id = $wpdb->get_var($wpdb->prepare('SELECT id FROM %i WHERE service_id = %s', self::services_table(), $data['service_id']));
        if ($existing_id) {
            return false !== $wpdb->update(self::services_table(), $data, array('id' => (int) $existing_id));
        }
        return false !== $wpdb->insert(self::services_table(), $data);
    }

    public static function get_service($service_id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare('SELECT * FROM %i WHERE service_id = %s', self::services_table(), (string) $service_id));
    }

    public static function search_services($term = '', $limit = 20) {
        global $wpdb;
        $limit = max(1, min(50, absint($limit)));
        $table = self::services_table();
        $term = trim((string) $term);
        if ('' === $term) {
            return $wpdb->get_results($wpdb->prepare('SELECT service_id, name, type, category, rate, min_qty, max_qty, refill, cancel FROM %i ORDER BY name ASC LIMIT %d', $table, $limit));
        }
        $like = '%' . $wpdb->esc_like($term) . '%';
        return $wpdb->get_results($wpdb->prepare(
            'SELECT service_id, name, type, category, rate, min_qty, max_qty, refill, cancel FROM %i WHERE service_id LIKE %s OR name LIKE %s OR category LIKE %s OR type LIKE %s ORDER BY name ASC LIMIT %d',
            $table,
            $like,
            $like,
            $like,
            $like,
            $limit
        ));
    }

    public static function service_count() {
        global $wpdb;
        return (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i', self::services_table()));
    }

    public static function order_count($status = '') {
        global $wpdb;
        if ('' !== $status) {
            return (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i WHERE status = %s', self::orders_table(), $status));
        }
        return (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i', self::orders_table()));
    }

    public static function cleanup_logs($days = 30) {
        global $wpdb;
        $days = max(1, absint($days));
        return $wpdb->query($wpdb->prepare('DELETE FROM %i WHERE created_at < DATE_SUB(%s, INTERVAL %d DAY)', self::logs_table(), current_time('mysql'), $days));
    }

    public static function clear_logs() {
        global $wpdb;
        return $wpdb->query($wpdb->prepare('DELETE FROM %i', self::logs_table()));
    }

    public static function product_issue_count() {
        global $wpdb;
        $postmeta = $wpdb->postmeta;
        $posts = $wpdb->posts;
        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(DISTINCT p.ID)
                 FROM %i p
                 INNER JOIN %i enabled ON enabled.post_id = p.ID AND enabled.meta_key = '_nfwc_enabled' AND enabled.meta_value = 'yes'
                 LEFT JOIN %i service ON service.post_id = p.ID AND service.meta_key = '_nfwc_service_id'
                 WHERE p.post_type IN ('product','product_variation')
                 AND (service.meta_value IS NULL OR service.meta_value = '')",
                $posts,
                $postmeta,
                $postmeta
            )
        );
    }
}
