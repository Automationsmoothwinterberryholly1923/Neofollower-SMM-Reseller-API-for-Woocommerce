<?php
if (!defined('ABSPATH')) {
    exit;
}

class NFWC_Order {
    private $api;

    public function __construct($api) {
        $this->api = $api;
        add_action('woocommerce_order_status_processing', array($this, 'process_order'));
        add_action('woocommerce_order_status_completed', array($this, 'process_order'));
        add_action('woocommerce_payment_complete', array($this, 'process_order'));
        add_action('nfwc_cron_sync_statuses', array($this, 'sync_statuses'));
        add_action('woocommerce_order_item_meta_end', array($this, 'show_customer_status'), 20, 4);
        add_filter('woocommerce_hidden_order_itemmeta', array($this, 'hide_internal_item_meta'));
    }

    public function hide_internal_item_meta($hidden) {
        $hidden[] = '_nfwc_enabled';
        $hidden[] = '_nfwc_product_id';
        $hidden[] = '_nfwc_service_id';
        $hidden[] = '_nfwc_service_type';
        $hidden[] = '_nfwc_link';
        $hidden[] = '_nfwc_api_quantity';
        $hidden[] = '_nfwc_comments';
        $hidden[] = '_nfwc_runs';
        $hidden[] = '_nfwc_interval';
        $hidden[] = '_nfwc_neo_order_id';
        $hidden[] = '_nfwc_status';
        $hidden[] = '_nfwc_submit_lock';
        return $hidden;
    }

    public function process_order($order_id) {
        if (!$order_id) {
            return;
        }
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        foreach ($order->get_items('line_item') as $item_id => $item) {
            if ('yes' !== $item->get_meta('_nfwc_enabled', true)) {
                continue;
            }
            $this->submit_order_item($order, $item_id, $item, false);
        }
    }

    public function submit_order_item($order, $item_id, $item, $force_retry = false) {
        if (!$order instanceof WC_Order) {
            $order = wc_get_order($order);
        }
        if (!$order) {
            return array('ok' => false, 'message' => __('Invalid WooCommerce order.', 'neofollower-smm-reseller-api-for-woocommerce'));
        }

        $existing_neo_id = (string) $item->get_meta('_nfwc_neo_order_id', true);
        if (!$force_retry && '' !== $existing_neo_id) {
            return array('ok' => true, 'message' => __('Already submitted.', 'neofollower-smm-reseller-api-for-woocommerce'), 'neo_order_id' => $existing_neo_id);
        }

        if (!$this->acquire_submission_lock($item_id)) {
            return array('ok' => false, 'message' => __('Submission already in progress.', 'neofollower-smm-reseller-api-for-woocommerce'));
        }

        $item->update_meta_data('_nfwc_submit_lock', 'yes');
        $item->save();

        $service_id = sanitize_text_field((string) $item->get_meta('_nfwc_service_id', true));
        $service_type = NFWC_Product::normalize_service_type((string) $item->get_meta('_nfwc_service_type', true));
        $link = sanitize_text_field((string) $item->get_meta('_nfwc_link', true));
        $api_quantity = absint($item->get_meta('_nfwc_api_quantity', true));
        $comments = (string) $item->get_meta('_nfwc_comments', true);
        $runs = absint($item->get_meta('_nfwc_runs', true));
        $interval = absint($item->get_meta('_nfwc_interval', true));
        $product_id = absint($item->get_meta('_nfwc_product_id', true)) ?: $item->get_product_id();

        if (!$service_id || !$link) {
            return $this->fail_item_submission($order, $item_id, $item, $product_id, $service_id, $service_type, $link, $api_quantity, $comments, $runs, $interval, __('Missing service ID or link.', 'neofollower-smm-reseller-api-for-woocommerce'), 0, null);
        }

        $settings = NFWC_DB::get_settings();
        if ('yes' === $settings['pause_fulfillment_low_balance']) {
            NFWC_Plugin::check_balance_and_maybe_alert($this->api);
            if ('yes' === get_option('nfwc_low_balance_in_danger', 'no')) {
                return $this->fail_item_submission($order, $item_id, $item, $product_id, $service_id, $service_type, $link, $api_quantity, $comments, $runs, $interval, __('Fulfillment paused because Neofollower balance is below the low balance threshold.', 'neofollower-smm-reseller-api-for-woocommerce'), 0, null);
            }
        }

        $payload = array(
            'service' => $service_id,
            'link' => $link,
        );

        switch ($service_type) {
            case 'custom_comments':
                $payload['comments'] = $comments;
                if ($api_quantity > 0) {
                    $payload['quantity'] = $api_quantity;
                }
                break;

            case 'package':
                break;

            case 'drip_feed':
                $payload['quantity'] = max(1, $api_quantity);
                $payload['runs'] = max(2, $runs);
                $payload['interval'] = max(1, $interval);
                break;

            case 'default':
            default:
                $payload['quantity'] = max(1, $api_quantity);
                break;
        }

        $response = $this->api->add_order($payload);
        $neo_order_id = '';
        $status = 'failed';
        $message = $response['message'];
        if ($response['ok'] && isset($response['json']['order'])) {
            $neo_order_id = sanitize_text_field((string) $response['json']['order']);
            $status = 'submitted';
            $message = __('Submitted to Neofollower.', 'neofollower-smm-reseller-api-for-woocommerce');
            $item->update_meta_data('_nfwc_neo_order_id', $neo_order_id);
            $item->update_meta_data('_nfwc_status', 'Pending');
            $order->add_order_note(sprintf('Neofollower order submitted for item #%d. Neofollower order ID: %s', $item_id, $neo_order_id));
        } else {
            $item->update_meta_data('_nfwc_status', 'Failed');
            $order->add_order_note(sprintf('Neofollower order failed for item #%d: %s', $item_id, $message ?: 'Unknown API error'));
            $this->maybe_notify_failure($order, $item_id, $service_id, $message ?: __('Unknown API error.', 'neofollower-smm-reseller-api-for-woocommerce'));
        }

        $item->delete_meta_data('_nfwc_submit_lock');
        $item->save();
        $this->release_submission_lock($item_id);
        $order->save();

        $this->upsert_log_row(
            $order,
            $item_id,
            $item,
            $product_id,
            $service_id,
            $neo_order_id,
            $service_type,
            $link,
            $api_quantity,
            $comments,
            $runs,
            $interval,
            $status,
            $response['http_code'],
            $message,
            $response['json'] ?: $response['body']
        );

        NFWC_DB::log($response['ok'] ? 'info' : 'error', 'order_submit', $message ?: 'Order submit response.', array(
            'woo_order_id' => $order->get_id(),
            'item_id' => $item_id,
            'neo_order_id' => $neo_order_id,
            'response' => $response,
        ));

        return array('ok' => 'failed' !== $status, 'message' => $message, 'neo_order_id' => $neo_order_id);
    }

    private function acquire_submission_lock($item_id) {
        $item_id = absint($item_id);
        if (!$item_id) {
            return false;
        }

        $key = 'nfwc_submit_lock_' . $item_id;
        if (add_option($key, time(), '', false)) {
            return true;
        }

        $locked_at = absint(get_option($key, 0));
        if ($locked_at && (time() - $locked_at) > 10 * MINUTE_IN_SECONDS) {
            delete_option($key);
            return add_option($key, time(), '', false);
        }

        return false;
    }

    private function release_submission_lock($item_id) {
        delete_option('nfwc_submit_lock_' . absint($item_id));
    }

    private function fail_item_submission($order, $item_id, $item, $product_id, $service_id, $service_type, $link, $api_quantity, $comments, $runs, $interval, $message, $http_code, $raw_response) {
        $item->update_meta_data('_nfwc_status', 'Failed');
        $item->delete_meta_data('_nfwc_submit_lock');
        $item->save();
        $this->release_submission_lock($item_id);
        $order->add_order_note(sprintf('Neofollower order failed for item #%d: %s', $item_id, $message));
        $order->save();
        $this->upsert_log_row($order, $item_id, $item, $product_id, $service_id, '', $service_type, $link, $api_quantity, $comments, $runs, $interval, 'failed', $http_code, $message, $raw_response);
        $this->maybe_notify_failure($order, $item_id, $service_id, $message);
        NFWC_DB::log('error', 'order_submit', $message, array('woo_order_id' => $order->get_id(), 'item_id' => $item_id, 'service_id' => $service_id));
        return array('ok' => false, 'message' => $message);
    }

    private function upsert_log_row($order, $item_id, $item, $product_id, $service_id, $neo_order_id, $service_type, $link, $quantity, $comments, $runs, $interval, $status, $http_code, $message, $raw_response) {
        global $wpdb;
        $table = NFWC_DB::orders_table();
        $data = array(
            'woo_order_id' => $order->get_id(),
            'woo_order_item_id' => absint($item_id),
            'product_id' => absint($product_id),
            'service_id' => sanitize_text_field((string) $service_id),
            'neo_order_id' => sanitize_text_field((string) $neo_order_id),
            'service_type' => sanitize_key((string) $service_type),
            'link' => sanitize_text_field((string) $link),
            'quantity' => $quantity ? absint($quantity) : null,
            'comments' => $comments,
            'runs' => $runs ? absint($runs) : null,
            'interval_minutes' => $interval ? absint($interval) : null,
            'status' => sanitize_text_field((string) $status),
            'api_http_code' => null === $http_code ? null : absint($http_code),
            'api_message' => wp_strip_all_tags((string) $message),
            'raw_response' => null === $raw_response ? null : wp_json_encode($raw_response),
            'updated_at' => current_time('mysql'),
        );

        $existing_id = $wpdb->get_var($wpdb->prepare('SELECT id FROM %i WHERE woo_order_item_id = %d', $table, absint($item_id)));
        if ($existing_id) {
            $wpdb->update($table, $data, array('id' => absint($existing_id)));
        } else {
            $data['created_at'] = current_time('mysql');
            $wpdb->insert($table, $data);
        }
    }

    public function retry_order_row($row_id) {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM %i WHERE id = %d', NFWC_DB::orders_table(), absint($row_id)));
        if (!$row) {
            return array('ok' => false, 'message' => __('Order log row not found.', 'neofollower-smm-reseller-api-for-woocommerce'));
        }
        $order = wc_get_order($row->woo_order_id);
        if (!$order) {
            return array('ok' => false, 'message' => __('WooCommerce order not found.', 'neofollower-smm-reseller-api-for-woocommerce'));
        }
        $item = $order->get_item($row->woo_order_item_id);
        if (!$item) {
            return array('ok' => false, 'message' => __('WooCommerce order item not found.', 'neofollower-smm-reseller-api-for-woocommerce'));
        }
        $item->delete_meta_data('_nfwc_neo_order_id');
        $item->delete_meta_data('_nfwc_submit_lock');
        $item->save();
        $this->release_submission_lock($row->woo_order_item_id);
        return $this->submit_order_item($order, $row->woo_order_item_id, $item, true);
    }

    public function sync_one_row($row_id) {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM %i WHERE id = %d', NFWC_DB::orders_table(), absint($row_id)));
        if (!$row || !$row->neo_order_id) {
            return array('ok' => false, 'message' => __('Missing Neofollower order ID.', 'neofollower-smm-reseller-api-for-woocommerce'));
        }
        return $this->sync_row($row);
    }

    public function sync_statuses($limit = 50) {
        global $wpdb;
        $settings = NFWC_DB::get_settings();
        if ('yes' !== $settings['auto_status_sync']) {
            return;
        }

        $final_statuses = array('completed', 'canceled', 'cancelled', 'partial', 'refunded', 'failed');
        $placeholders = implode(',', array_fill(0, count($final_statuses), '%s'));
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM %i WHERE neo_order_id != '' AND LOWER(status) NOT IN ({$placeholders}) ORDER BY updated_at ASC LIMIT %d",
                array_merge(array(NFWC_DB::orders_table()), $final_statuses, array(absint($limit)))
            )
        );
        foreach ($rows as $row) {
            $this->sync_row($row);
        }
    }

    private function sync_row($row) {
        global $wpdb;
        $response = $this->api->status($row->neo_order_id);
        if (!$response['ok'] || !is_array($response['json'])) {
            $wpdb->update(NFWC_DB::orders_table(), array(
                'api_http_code' => $response['http_code'],
                'api_message' => $response['message'],
                'raw_response' => wp_json_encode($response['json'] ?: $response['body']),
                'last_sync_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ), array('id' => absint($row->id)));
            NFWC_DB::log('error', 'status_sync', $response['message'] ?: 'Status sync failed.', array('row_id' => $row->id, 'response' => $response));
            return array('ok' => false, 'message' => $response['message']);
        }

        $status = isset($response['json']['status']) ? sanitize_text_field((string) $response['json']['status']) : 'Unknown';
        $wpdb->update(NFWC_DB::orders_table(), array(
            'status' => $status,
            'api_http_code' => $response['http_code'],
            'api_message' => '',
            'raw_response' => wp_json_encode($response['json']),
            'last_sync_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ), array('id' => absint($row->id)));

        $order = wc_get_order($row->woo_order_id);
        if ($order) {
            $item = $order->get_item($row->woo_order_item_id);
            if ($item) {
                $item->update_meta_data('_nfwc_status', $status);
                $item->save();
            }
        }

        return array('ok' => true, 'message' => __('Status synced.', 'neofollower-smm-reseller-api-for-woocommerce'), 'status' => $status);
    }

    private function maybe_notify_failure($order, $item_id, $service_id, $message) {
        $settings = NFWC_DB::get_settings();
        if ('yes' !== $settings['failure_email_enabled']) {
            return false;
        }
        $key = 'nfwc_failure_email_' . absint($order->get_id()) . '_' . absint($item_id);
        if (get_transient($key)) {
            return false;
        }
        $recipient = sanitize_email($settings['failure_email_recipient']);
        if (!$recipient) {
            $recipient = get_option('admin_email');
        }
        $order_url = '';
        if (function_exists('wc_get_order_admin_edit_url')) {
            $order_url = wc_get_order_admin_edit_url($order);
        }
        $subject = sprintf(__('Neofollower fulfillment failed for order #%d', 'neofollower-smm-reseller-api-for-woocommerce'), $order->get_id());
        $body = sprintf(
            "A Neofollower fulfillment order failed.\n\nWooCommerce order: #%1\$d\nOrder item: #%2\$d\nNeofollower service ID: %3\$s\nError: %4\$s\n\nReview order: %5\$s\n\nSite: %6\$s",
            $order->get_id(),
            $item_id,
            $service_id ?: '—',
            wp_strip_all_tags((string) $message),
            $order_url,
            home_url()
        );
        $sent = wp_mail($recipient, $subject, $body);
        set_transient($key, 1, 6 * HOUR_IN_SECONDS);
        return $sent;
    }

    public function show_customer_status($item_id, $item, $order, $plain_text) {
        if (!is_a($item, 'WC_Order_Item_Product')) {
            return;
        }
        if ('yes' !== $item->get_meta('_nfwc_enabled', true)) {
            return;
        }
        $status = $item->get_meta('_nfwc_status', true);
        if (!$status) {
            $status = __('Received', 'neofollower-smm-reseller-api-for-woocommerce');
        }
        if ($plain_text) {
            echo "\n" . esc_html__('Service status:', 'neofollower-smm-reseller-api-for-woocommerce') . ' ' . esc_html($status) . "\n";
            return;
        }
        echo '<div class="nfwc-customer-status"><strong>' . esc_html__('Service status:', 'neofollower-smm-reseller-api-for-woocommerce') . '</strong> ' . esc_html($status) . '</div>';
    }
}
