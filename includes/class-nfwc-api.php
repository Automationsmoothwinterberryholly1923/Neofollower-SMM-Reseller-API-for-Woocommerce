<?php
if (!defined('ABSPATH')) {
    exit;
}

class NFWC_API {
    public function endpoint() {
        return apply_filters('nfwc_api_endpoint', NFWC_DEFAULT_API_ENDPOINT);
    }

    public function api_key() {
        $settings = NFWC_DB::get_settings();
        return trim((string) $settings['api_key']);
    }

    public function has_api_key() {
        return '' !== $this->api_key();
    }

    public function request($action, $params = array()) {
        $api_key = $this->api_key();
        if ('' === $api_key) {
            return array(
                'ok' => false,
                'http_code' => 0,
                'json' => null,
                'body' => '',
                'message' => __('Missing Neofollower API key.', 'neofollower-smm-reseller-api-for-woocommerce'),
            );
        }

        $body = array_merge(array(
            'key' => $api_key,
            'action' => sanitize_key($action),
        ), $params);

        $response = wp_remote_post($this->endpoint(), array(
            'timeout' => 30,
            'redirection' => 3,
            'sslverify' => true,
            'body' => $body,
            'headers' => array(
                'Accept' => 'application/json',
            ),
        ));

        if (is_wp_error($response)) {
            NFWC_DB::log('error', 'api', $response->get_error_message(), array('action' => $action));
            return array(
                'ok' => false,
                'http_code' => 0,
                'json' => null,
                'body' => '',
                'message' => $response->get_error_message(),
            );
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $raw_body = (string) wp_remote_retrieve_body($response);
        $json = json_decode($raw_body, true);

        $message = '';
        if (is_array($json)) {
            if (isset($json['error'])) {
                $message = (string) $json['error'];
            } elseif (isset($json['message'])) {
                $message = (string) $json['message'];
            }
        }
        if ('' === $message && $code >= 400) {
            $message = sprintf(__('HTTP error %d.', 'neofollower-smm-reseller-api-for-woocommerce'), $code);
        }
        if ('' === $message && null === $json && '' !== $raw_body) {
            $message = __('Invalid JSON response from API.', 'neofollower-smm-reseller-api-for-woocommerce');
        }

        $ok = $code >= 200 && $code < 300 && is_array($json) && !isset($json['error']);

        return array(
            'ok' => $ok,
            'http_code' => $code,
            'json' => $json,
            'body' => $raw_body,
            'message' => $message,
        );
    }

    public function services() {
        return $this->request('services');
    }

    public function balance() {
        return $this->request('balance');
    }

    public function add_order($payload) {
        return $this->request('add', $payload);
    }

    public function status($neo_order_id) {
        return $this->request('status', array('order' => (string) $neo_order_id));
    }

    public function sync_services() {
        $response = $this->services();
        if (!$response['ok'] || !is_array($response['json'])) {
            NFWC_DB::log('error', 'services_sync', $response['message'] ?: __('Service sync failed.', 'neofollower-smm-reseller-api-for-woocommerce'), $response);
            return array('ok' => false, 'count' => 0, 'message' => $response['message'] ?: __('Service sync failed.', 'neofollower-smm-reseller-api-for-woocommerce'));
        }

        $count = 0;
        foreach ($response['json'] as $service) {
            if (is_array($service) && NFWC_DB::upsert_service($service)) {
                $count++;
            }
        }

        update_option('nfwc_last_services_sync', current_time('mysql'), false);
        NFWC_DB::log('info', 'services_sync', sprintf('Synced %d services.', $count));
        return array('ok' => true, 'count' => $count, 'message' => sprintf(__('Synced %d services.', 'neofollower-smm-reseller-api-for-woocommerce'), $count));
    }
}
