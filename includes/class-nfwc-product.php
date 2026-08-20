<?php
if (!defined('ABSPATH')) {
    exit;
}

class NFWC_Product {
    private $api;

    public function __construct($api) {
        $this->api = $api;
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        add_action('wp_ajax_nfwc_search_services', array($this, 'ajax_search_services'));
        add_action('wp_ajax_nfwc_service_details', array($this, 'ajax_service_details'));
        add_action('admin_notices', array($this, 'product_admin_notices'));

        add_action('woocommerce_product_options_general_product_data', array($this, 'render_product_options'));
        add_action('woocommerce_process_product_meta', array($this, 'save_product_options'));
        add_action('woocommerce_before_add_to_cart_button', array($this, 'render_frontend_fields'));
        add_filter('woocommerce_add_to_cart_validation', array($this, 'validate_add_to_cart'), 10, 3);
        add_filter('woocommerce_add_cart_item_data', array($this, 'add_cart_item_data'), 10, 3);
        add_filter('woocommerce_get_item_data', array($this, 'display_cart_item_data'), 10, 2);
        add_action('woocommerce_checkout_create_order_line_item', array($this, 'add_order_item_meta'), 10, 4);
        add_filter('woocommerce_is_sold_individually', array($this, 'force_single_woocommerce_quantity'), 10, 2);
        add_filter('woocommerce_add_to_cart_quantity', array($this, 'force_add_to_cart_quantity'), 10, 2);
        add_action('woocommerce_before_calculate_totals', array($this, 'apply_dynamic_cart_price'), 20, 1);
    }

    public function enqueue_admin_assets($hook) {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        $is_product_screen = $screen && in_array($screen->id, array('product', 'edit-product'), true);
        $is_nfwc_page = isset($_GET['page']) && 'nfwc-bridge' === sanitize_key(wp_unslash($_GET['page']));
        if (!$is_product_screen && !$is_nfwc_page) {
            return;
        }
        wp_enqueue_style('nfwc-admin', NFWC_URL . 'assets/css/nfwc-admin.css', array(), NFWC_VERSION);
        wp_enqueue_script('nfwc-admin', NFWC_URL . 'assets/js/nfwc-admin.js', array('jquery'), NFWC_VERSION, true);
        wp_localize_script('nfwc-admin', 'NFWCAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('nfwc_admin_ajax'),
            'searchPlaceholder' => __('Search by service ID, name, category, or type...', 'neofollower-smm-reseller-api-for-woocommerce'),
            'noResults' => __('No services found. Sync services first or try another search.', 'neofollower-smm-reseller-api-for-woocommerce'),
            'searching' => __('Searching...', 'neofollower-smm-reseller-api-for-woocommerce'),
        ));
    }

    public function enqueue_frontend_assets() {
        if (is_product()) {
            wp_enqueue_style('nfwc-frontend', NFWC_URL . 'assets/css/nfwc-frontend.css', array(), NFWC_VERSION);
            wp_enqueue_script('nfwc-frontend', NFWC_URL . 'assets/js/nfwc-frontend.js', array('jquery'), NFWC_VERSION, true);
            wp_localize_script('nfwc-frontend', 'NFWCFrontend', array(
                'commentSingular' => __('comment', 'neofollower-smm-reseller-api-for-woocommerce'),
                'commentPlural' => __('comments', 'neofollower-smm-reseller-api-for-woocommerce'),
                'priceFormat' => get_woocommerce_price_format(),
                'currencySymbol' => get_woocommerce_currency_symbol(),
                'decimalSeparator' => wc_get_price_decimal_separator(),
                'thousandSeparator' => wc_get_price_thousand_separator(),
                'decimals' => wc_get_price_decimals(),
            ));
        }
    }

    public static function is_enabled($product_id) {
        return 'yes' === get_post_meta($product_id, '_nfwc_enabled', true);
    }

    public static function resolve_product_id($product_id) {
        $product_id = absint($product_id);
        $product = wc_get_product($product_id);
        if ($product && $product->is_type('variation')) {
            $parent_id = $product->get_parent_id();
            if ($parent_id && self::is_enabled($parent_id)) {
                return $parent_id;
            }
        }
        return $product_id;
    }

    public static function get_config($product_id) {
        $product_id = self::resolve_product_id($product_id);
        $service_type = get_post_meta($product_id, '_nfwc_service_type', true) ?: 'default';
        $quantity_mode = get_post_meta($product_id, '_nfwc_quantity_mode', true) ?: 'fixed';
        if ('wc_qty' === $quantity_mode) {
            $quantity_mode = 'customer';
        }
        if ('custom_comments' === $service_type) {
            $quantity_mode = 'comments_lines';
        }
        if ('package' === $service_type && 'customer' === $quantity_mode) {
            $quantity_mode = 'fixed';
        }
        return array(
            'enabled' => get_post_meta($product_id, '_nfwc_enabled', true),
            'service_id' => get_post_meta($product_id, '_nfwc_service_id', true),
            'service_type' => $service_type,
            'quantity_mode' => $quantity_mode,
            'fixed_quantity' => max(1, absint(get_post_meta($product_id, '_nfwc_fixed_quantity', true) ?: 1)),
            'min_quantity' => max(1, absint(get_post_meta($product_id, '_nfwc_min_quantity', true) ?: 1)),
            'max_quantity' => absint(get_post_meta($product_id, '_nfwc_max_quantity', true) ?: 0),
            'link_label' => get_post_meta($product_id, '_nfwc_link_label', true) ?: __('Profile/Post Link', 'neofollower-smm-reseller-api-for-woocommerce'),
            'link_placeholder' => get_post_meta($product_id, '_nfwc_link_placeholder', true) ?: '',
            'customer_note' => get_post_meta($product_id, '_nfwc_customer_note', true) ?: '',
        );
    }

    public static function normalize_service_type($service_type) {
        $service_type = sanitize_key((string) $service_type);
        $allowed = array('default', 'custom_comments', 'package', 'drip_feed');
        return in_array($service_type, $allowed, true) ? $service_type : 'default';
    }

    public static function guess_service_type_from_api_type($api_type) {
        $type = strtolower((string) $api_type);
        if (false !== strpos($type, 'custom') && false !== strpos($type, 'comment')) {
            return 'custom_comments';
        }
        if (false !== strpos($type, 'package')) {
            return 'package';
        }
        if (false !== strpos($type, 'drip')) {
            return 'drip_feed';
        }
        return 'default';
    }

    public function render_product_options() {
        $product_id = get_the_ID();
        $config = self::get_config($product_id);
        $current_service = $config['service_id'] ? NFWC_DB::get_service($config['service_id']) : null;
        $service_count = NFWC_DB::service_count();

        echo '<div class="options_group show_if_simple show_if_variable nfwc-product-panel" data-nfwc-panel="1">';
        echo '<h2>' . esc_html__('Neofollower Fulfillment', 'neofollower-smm-reseller-api-for-woocommerce') . '</h2>';
        echo '<p class="nfwc-panel-intro">' . esc_html__('Connect this WooCommerce product to one Neofollower service. Fields below change automatically based on the selected service type.', 'neofollower-smm-reseller-api-for-woocommerce') . '</p>';

        wp_nonce_field('nfwc_save_product_options', 'nfwc_product_options_nonce');

        woocommerce_wp_checkbox(array(
            'id' => '_nfwc_enabled',
            'label' => __('Enable fulfillment', 'neofollower-smm-reseller-api-for-woocommerce'),
            'description' => __('Submit paid orders for this product to Neofollower.', 'neofollower-smm-reseller-api-for-woocommerce'),
        ));

        echo '<div class="nfwc-conditional-wrap">';
        echo '<p class="form-field nfwc_service_search_field">';
        echo '<label for="_nfwc_service_search">' . esc_html__('Neofollower service', 'neofollower-smm-reseller-api-for-woocommerce') . '</label>';
        echo '<span class="nfwc-service-picker">';
        echo '<input type="hidden" id="_nfwc_service_id" name="_nfwc_service_id" value="' . esc_attr($config['service_id']) . '" />';
        echo '<input type="text" id="_nfwc_service_search" class="nfwc-service-search" value="' . esc_attr($current_service ? $current_service->service_id . ' — ' . $current_service->name : '') . '" placeholder="' . esc_attr__('Search service...', 'neofollower-smm-reseller-api-for-woocommerce') . '" autocomplete="off" />';
        echo '<button type="button" class="button nfwc-clear-service">' . esc_html__('Clear', 'neofollower-smm-reseller-api-for-woocommerce') . '</button>';
        echo '<span class="description">' . esc_html__('Search all synced services. This avoids loading thousands of services in a dropdown.', 'neofollower-smm-reseller-api-for-woocommerce') . '</span>';
        echo '<span class="nfwc-service-results" aria-live="polite"></span>';
        echo '</span>';
        echo '</p>';

        echo '<p class="form-field nfwc_manual_service_field">';
        echo '<label for="_nfwc_manual_service_id">' . esc_html__('Manual service ID', 'neofollower-smm-reseller-api-for-woocommerce') . '</label>';
        echo '<input type="text" id="_nfwc_manual_service_id" name="_nfwc_manual_service_id" value="" placeholder="' . esc_attr__('Optional override', 'neofollower-smm-reseller-api-for-woocommerce') . '" />';
        echo '<span class="description">' . esc_html__('Use only if the synced search does not contain the service yet.', 'neofollower-smm-reseller-api-for-woocommerce') . '</span>';
        echo '</p>';

        echo '<div class="nfwc-selected-service-card" data-empty="' . esc_attr($current_service ? '0' : '1') . '">';
        if ($current_service) {
            $this->render_service_card($current_service);
        } else {
            echo '<p>' . esc_html(sprintf(__('No service selected. Synced services available: %d', 'neofollower-smm-reseller-api-for-woocommerce'), $service_count)) . '</p>';
        }
        echo '</div>';

        woocommerce_wp_select(array(
            'id' => '_nfwc_service_type',
            'label' => __('Fulfillment type', 'neofollower-smm-reseller-api-for-woocommerce'),
            'value' => $config['service_type'],
            'options' => array(
                'default' => __('Default quantity service', 'neofollower-smm-reseller-api-for-woocommerce'),
                'custom_comments' => __('Custom comments', 'neofollower-smm-reseller-api-for-woocommerce'),
                'package' => __('Package / no quantity', 'neofollower-smm-reseller-api-for-woocommerce'),
                'drip_feed' => __('Drip-feed', 'neofollower-smm-reseller-api-for-woocommerce'),
            ),
            'description' => __('Choose how the API payload should be built. Custom comments automatically uses number of comment lines.', 'neofollower-smm-reseller-api-for-woocommerce'),
            'desc_tip' => true,
        ));

        woocommerce_wp_select(array(
            'id' => '_nfwc_quantity_mode',
            'label' => __('Quantity mode', 'neofollower-smm-reseller-api-for-woocommerce'),
            'value' => $config['quantity_mode'],
            'wrapper_class' => 'nfwc-field nfwc-field-quantity-mode',
            'options' => array(
                'fixed' => __('Fixed API quantity per WooCommerce item', 'neofollower-smm-reseller-api-for-woocommerce'),
                'customer' => __('Customer enters quantity', 'neofollower-smm-reseller-api-for-woocommerce'),
                'comments_lines' => __('Use number of comment lines', 'neofollower-smm-reseller-api-for-woocommerce'),
            ),
        ));

        woocommerce_wp_text_input(array(
            'id' => '_nfwc_fixed_quantity',
            'label' => __('Fixed API quantity', 'neofollower-smm-reseller-api-for-woocommerce'),
            'value' => $config['fixed_quantity'],
            'type' => 'number',
            'wrapper_class' => 'nfwc-field nfwc-field-fixed-quantity',
            'custom_attributes' => array('min' => '1', 'step' => '1'),
            'description' => __('Example: set 1000 for a product named “Instagram Followers 1000”.', 'neofollower-smm-reseller-api-for-woocommerce'),
            'desc_tip' => true,
        ));

        woocommerce_wp_text_input(array(
            'id' => '_nfwc_min_quantity',
            'label' => __('Minimum customer quantity', 'neofollower-smm-reseller-api-for-woocommerce'),
            'value' => $config['min_quantity'],
            'type' => 'number',
            'wrapper_class' => 'nfwc-field nfwc-field-customer-quantity',
            'custom_attributes' => array('min' => '1', 'step' => '1'),
        ));

        woocommerce_wp_text_input(array(
            'id' => '_nfwc_max_quantity',
            'label' => __('Maximum customer quantity', 'neofollower-smm-reseller-api-for-woocommerce'),
            'value' => $config['max_quantity'],
            'type' => 'number',
            'wrapper_class' => 'nfwc-field nfwc-field-customer-quantity',
            'custom_attributes' => array('min' => '0', 'step' => '1'),
            'description' => __('Use 0 for no custom max.', 'neofollower-smm-reseller-api-for-woocommerce'),
            'desc_tip' => true,
        ));

        woocommerce_wp_text_input(array(
            'id' => '_nfwc_link_label',
            'label' => __('Link field label', 'neofollower-smm-reseller-api-for-woocommerce'),
            'value' => $config['link_label'],
            'placeholder' => __('Profile/Post Link', 'neofollower-smm-reseller-api-for-woocommerce'),
            'wrapper_class' => 'nfwc-field nfwc-field-link',
        ));

        woocommerce_wp_text_input(array(
            'id' => '_nfwc_link_placeholder',
            'label' => __('Link field placeholder', 'neofollower-smm-reseller-api-for-woocommerce'),
            'value' => $config['link_placeholder'],
            'placeholder' => __('https://...', 'neofollower-smm-reseller-api-for-woocommerce'),
            'wrapper_class' => 'nfwc-field nfwc-field-link',
        ));

        woocommerce_wp_textarea_input(array(
            'id' => '_nfwc_customer_note',
            'label' => __('Customer instructions', 'neofollower-smm-reseller-api-for-woocommerce'),
            'value' => $config['customer_note'],
            'description' => __('Optional note shown above the product fields.', 'neofollower-smm-reseller-api-for-woocommerce'),
            'desc_tip' => true,
            'wrapper_class' => 'nfwc-field nfwc-field-link',
        ));

        echo '</div>';
        echo '</div>';
    }

    private function render_service_card($service) {
        echo '<div class="nfwc-service-card-inner">';
        echo '<strong>' . esc_html($service->service_id . ' — ' . $service->name) . '</strong>';
        echo '<ul>';
        echo '<li><span>' . esc_html__('Type:', 'neofollower-smm-reseller-api-for-woocommerce') . '</span> ' . esc_html($service->type ?: '—') . '</li>';
        echo '<li><span>' . esc_html__('Category:', 'neofollower-smm-reseller-api-for-woocommerce') . '</span> ' . esc_html($service->category ?: '—') . '</li>';
        echo '<li><span>' . esc_html__('Rate:', 'neofollower-smm-reseller-api-for-woocommerce') . '</span> ' . esc_html($service->rate ?: '—') . '</li>';
        echo '<li><span>' . esc_html__('Min / Max:', 'neofollower-smm-reseller-api-for-woocommerce') . '</span> ' . esc_html(($service->min_qty ?: '—') . ' / ' . ($service->max_qty ?: '—')) . '</li>';
        echo '<li><span>' . esc_html__('Refill / Cancel:', 'neofollower-smm-reseller-api-for-woocommerce') . '</span> ' . esc_html(($service->refill ?: '—') . ' / ' . ($service->cancel ?: '—')) . '</li>';
        echo '</ul>';
        echo '<button type="button" class="button nfwc-apply-service-limits" data-type="' . esc_attr(self::guess_service_type_from_api_type($service->type)) . '" data-min="' . esc_attr(absint($service->min_qty)) . '" data-max="' . esc_attr(absint($service->max_qty)) . '">' . esc_html__('Apply type and min/max', 'neofollower-smm-reseller-api-for-woocommerce') . '</button>';
        echo '</div>';
    }

    public function save_product_options($post_id) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        if (
            !isset($_POST['nfwc_product_options_nonce']) ||
            !wp_verify_nonce(
                sanitize_text_field(wp_unslash($_POST['nfwc_product_options_nonce'])),
                'nfwc_save_product_options'
            )
        ) {
            return;
        }

        $enabled = isset($_POST['_nfwc_enabled']) ? 'yes' : 'no';
        $manual_service_id = isset($_POST['_nfwc_manual_service_id']) ? sanitize_text_field(wp_unslash($_POST['_nfwc_manual_service_id'])) : '';
        $service_id = $manual_service_id ?: (isset($_POST['_nfwc_service_id']) ? sanitize_text_field(wp_unslash($_POST['_nfwc_service_id'])) : '');

        if ('yes' === $enabled && '' === $service_id) {
            $enabled = 'no';
            set_transient('nfwc_product_notice_' . get_current_user_id(), __('Neofollower fulfillment was disabled because no service ID was selected.', 'neofollower-smm-reseller-api-for-woocommerce'), 60);
        }

        update_post_meta($post_id, '_nfwc_enabled', $enabled);
        update_post_meta($post_id, '_nfwc_service_id', $service_id);

        $service_type = isset($_POST['_nfwc_service_type']) ? self::normalize_service_type(wp_unslash($_POST['_nfwc_service_type'])) : 'default';
        $quantity_mode = isset($_POST['_nfwc_quantity_mode']) ? sanitize_key(wp_unslash($_POST['_nfwc_quantity_mode'])) : 'fixed';
        if ('wc_qty' === $quantity_mode) {
            $quantity_mode = 'customer';
        }
        $allowed_quantity_modes = array('fixed', 'customer', 'comments_lines');
        if (!in_array($quantity_mode, $allowed_quantity_modes, true)) {
            $quantity_mode = 'fixed';
        }
        if ('custom_comments' === $service_type) {
            $quantity_mode = 'comments_lines';
        }
        if ('package' === $service_type && in_array($quantity_mode, array('customer', 'comments_lines'), true)) {
            $quantity_mode = 'fixed';
        }
        update_post_meta($post_id, '_nfwc_service_type', $service_type);
        update_post_meta($post_id, '_nfwc_quantity_mode', $quantity_mode);

        $numeric_fields = array('_nfwc_fixed_quantity', '_nfwc_min_quantity', '_nfwc_max_quantity');
        foreach ($numeric_fields as $field) {
            $value = isset($_POST[$field]) ? absint(wp_unslash($_POST[$field])) : 0;
            if (in_array($field, array('_nfwc_fixed_quantity', '_nfwc_min_quantity'), true)) {
                $value = max(1, $value);
            }
            update_post_meta($post_id, $field, $value);
        }

        $text_fields = array('_nfwc_link_label', '_nfwc_link_placeholder');
        foreach ($text_fields as $field) {
            if (isset($_POST[$field])) {
                update_post_meta($post_id, $field, sanitize_text_field(wp_unslash($_POST[$field])));
            }
        }
        if (isset($_POST['_nfwc_customer_note'])) {
            update_post_meta($post_id, '_nfwc_customer_note', sanitize_textarea_field(wp_unslash($_POST['_nfwc_customer_note'])));
        }
    }

    public function product_admin_notices() {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || !in_array($screen->id, array('product', 'edit-product'), true)) {
            return;
        }

        $notice = get_transient('nfwc_product_notice_' . get_current_user_id());
        if (!$notice) {
            return;
        }
        delete_transient('nfwc_product_notice_' . get_current_user_id());
        echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html($notice) . '</p></div>';
    }

    public function render_frontend_fields() {
        global $product;
        if (!$product instanceof WC_Product) {
            return;
        }
        $product_id = self::resolve_product_id($product->get_id());
        if (!self::is_enabled($product_id)) {
            return;
        }

        $config = self::get_config($product_id);
        $service = $config['service_id'] ? NFWC_DB::get_service($config['service_id']) : null;
        $show_quantity = 'customer' === $config['quantity_mode'] && 'package' !== $config['service_type'] && 'custom_comments' !== $config['service_type'];

        $display_price = wc_get_price_to_display($product);
        $price_mode = $this->get_frontend_price_mode($config);

        echo '<div class="nfwc-product-fields" data-nfwc-price-mode="' . esc_attr($price_mode) . '" data-nfwc-unit-price="' . esc_attr(wc_format_decimal($display_price, wc_get_price_decimals())) . '" data-nfwc-fixed-quantity="' . esc_attr($config['fixed_quantity']) . '">';
        wp_nonce_field('nfwc_add_to_cart_' . $product_id, 'nfwc_add_to_cart_nonce');
        echo '<div class="nfwc-product-fields__header"><strong>' . esc_html__('Service Details', 'neofollower-smm-reseller-api-for-woocommerce') . '</strong><span>' . esc_html__('Enter the target information before adding this service to cart.', 'neofollower-smm-reseller-api-for-woocommerce') . '</span></div>';
        if (!empty($config['customer_note'])) {
            echo '<div class="nfwc-customer-note">' . esc_html($config['customer_note']) . '</div>';
        }

        echo '<p class="form-row form-row-wide nfwc-field-row">';
        echo '<label for="nfwc_link">' . esc_html($config['link_label']) . ' <span class="required">*</span></label>';
        echo '<input type="text" id="nfwc_link" name="nfwc_link" value="" placeholder="' . esc_attr($config['link_placeholder'] ?: 'https://...') . '" required />';
        echo '<small>' . esc_html__('Make sure the profile, post, page, or target link is public and correct.', 'neofollower-smm-reseller-api-for-woocommerce') . '</small>';
        echo '</p>';

        if ($show_quantity) {
            echo '<p class="form-row form-row-wide nfwc-field-row">';
            echo '<label for="nfwc_quantity">' . esc_html__('Quantity', 'neofollower-smm-reseller-api-for-woocommerce') . ' <span class="required">*</span></label>';
            echo '<input type="number" id="nfwc_quantity" name="nfwc_quantity" value="' . esc_attr($config['min_quantity']) . '" min="' . esc_attr($config['min_quantity']) . '" ' . ($config['max_quantity'] ? 'max="' . esc_attr($config['max_quantity']) . '"' : '') . ' step="1" required />';
            if ($config['max_quantity']) {
                echo '<small>' . esc_html(sprintf(__('Allowed range: %1$d to %2$d.', 'neofollower-smm-reseller-api-for-woocommerce'), $config['min_quantity'], $config['max_quantity'])) . '</small>';
            } elseif ($service && ($service->min_qty || $service->max_qty)) {
                echo '<small>' . esc_html(sprintf(__('Service API range: %1$s to %2$s.', 'neofollower-smm-reseller-api-for-woocommerce'), $service->min_qty ?: '—', $service->max_qty ?: '—')) . '</small>';
            }
            echo '</p>';
        }

        if ('custom_comments' === $config['service_type']) {
            echo '<p class="form-row form-row-wide nfwc-field-row">';
            echo '<label for="nfwc_comments">' . esc_html__('Comments', 'neofollower-smm-reseller-api-for-woocommerce') . ' <span class="required">*</span></label>';
            echo '<textarea id="nfwc_comments" name="nfwc_comments" rows="6" placeholder="' . esc_attr__('One comment per line', 'neofollower-smm-reseller-api-for-woocommerce') . '" required data-nfwc-comment-counter="1"></textarea>';
            echo '<small class="nfwc-comment-count">' . esc_html__('0 comments', 'neofollower-smm-reseller-api-for-woocommerce') . '</small>';
            echo '</p>';
        }

        if ('drip_feed' === $config['service_type']) {
            echo '<div class="nfwc-drip-grid">';
            echo '<p class="form-row nfwc-field-row">';
            echo '<label for="nfwc_runs">' . esc_html__('Runs', 'neofollower-smm-reseller-api-for-woocommerce') . ' <span class="required">*</span></label>';
            echo '<input type="number" id="nfwc_runs" name="nfwc_runs" value="2" min="2" step="1" required />';
            echo '</p>';
            echo '<p class="form-row nfwc-field-row">';
            echo '<label for="nfwc_interval">' . esc_html__('Interval minutes', 'neofollower-smm-reseller-api-for-woocommerce') . ' <span class="required">*</span></label>';
            echo '<input type="number" id="nfwc_interval" name="nfwc_interval" value="60" min="1" step="1" required />';
            echo '</p>';
            echo '</div>';
            echo '<small class="nfwc-help-text">' . esc_html__('Recommended interval: 30–60+ minutes for safer delivery.', 'neofollower-smm-reseller-api-for-woocommerce') . '</small>';
        }

        if ('fixed' !== $price_mode) {
            echo '<div class="nfwc-price-calculator" aria-live="polite">';
            echo '<span>' . esc_html__('Total', 'neofollower-smm-reseller-api-for-woocommerce') . '</span>';
            echo '<strong class="nfwc-price-calculator__amount">' . wp_kses_post(wc_price($display_price)) . '</strong>';
            echo '</div>';
        }

        echo '</div>';
    }

    public function validate_add_to_cart($passed, $product_id, $quantity) {
        $product_id = self::resolve_product_id($product_id);
        if (!self::is_enabled($product_id)) {
            return $passed;
        }
        $nonce = isset($_POST['nfwc_add_to_cart_nonce']) ? sanitize_text_field(wp_unslash($_POST['nfwc_add_to_cart_nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'nfwc_add_to_cart_' . $product_id)) {
            wc_add_notice(__('Security check failed. Please refresh the product page and try again.', 'neofollower-smm-reseller-api-for-woocommerce'), 'error');
            return false;
        }

        $config = self::get_config($product_id);
        if ('' === trim((string) $config['service_id'])) {
            wc_add_notice(__('This service is not configured correctly. Please contact support.', 'neofollower-smm-reseller-api-for-woocommerce'), 'error');
            return false;
        }

        $link = isset($_POST['nfwc_link']) ? trim(wp_unslash($_POST['nfwc_link'])) : '';
        if ('' === $link) {
            wc_add_notice(__('Please enter the required link/username.', 'neofollower-smm-reseller-api-for-woocommerce'), 'error');
            return false;
        }

        if ('custom_comments' === $config['service_type']) {
            $comments = isset($_POST['nfwc_comments']) ? trim(wp_unslash($_POST['nfwc_comments'])) : '';
            if ('' === $comments) {
                wc_add_notice(__('Please enter comments, one per line.', 'neofollower-smm-reseller-api-for-woocommerce'), 'error');
                return false;
            }
            $line_count = $this->count_comment_lines($comments);
            if ($line_count < 1) {
                wc_add_notice(__('Please enter at least one valid comment line.', 'neofollower-smm-reseller-api-for-woocommerce'), 'error');
                return false;
            }
            if ($config['min_quantity'] && $line_count < $config['min_quantity']) {
                wc_add_notice(sprintf(__('Please enter at least %d comment lines.', 'neofollower-smm-reseller-api-for-woocommerce'), $config['min_quantity']), 'error');
                return false;
            }
            if ($config['max_quantity'] && $line_count > $config['max_quantity']) {
                wc_add_notice(sprintf(__('Please enter no more than %d comment lines.', 'neofollower-smm-reseller-api-for-woocommerce'), $config['max_quantity']), 'error');
                return false;
            }
        }

        if ('customer' === $config['quantity_mode'] && 'custom_comments' !== $config['service_type'] && 'package' !== $config['service_type']) {
            $api_qty = isset($_POST['nfwc_quantity']) ? absint(wp_unslash($_POST['nfwc_quantity'])) : 0;
            if ($api_qty < max(1, $config['min_quantity'])) {
                wc_add_notice(__('Quantity is below the minimum allowed.', 'neofollower-smm-reseller-api-for-woocommerce'), 'error');
                return false;
            }
            if ($config['max_quantity'] && $api_qty > $config['max_quantity']) {
                wc_add_notice(__('Quantity is above the maximum allowed.', 'neofollower-smm-reseller-api-for-woocommerce'), 'error');
                return false;
            }
        }

        if ('drip_feed' === $config['service_type']) {
            $runs = isset($_POST['nfwc_runs']) ? absint(wp_unslash($_POST['nfwc_runs'])) : 0;
            $interval = isset($_POST['nfwc_interval']) ? absint(wp_unslash($_POST['nfwc_interval'])) : 0;
            if ($runs < 2 || $interval < 1) {
                wc_add_notice(__('Please enter valid drip-feed runs and interval.', 'neofollower-smm-reseller-api-for-woocommerce'), 'error');
                return false;
            }
        }

        return $passed;
    }

    public function add_cart_item_data($cart_item_data, $product_id, $variation_id) {
        $resolved_id = self::resolve_product_id($variation_id ?: $product_id);
        if (!self::is_enabled($resolved_id)) {
            return $cart_item_data;
        }

        $cart_product = wc_get_product($variation_id ?: $product_id);
        $cart_item_data['nfwc'] = array(
            'link' => isset($_POST['nfwc_link']) ? sanitize_text_field(wp_unslash($_POST['nfwc_link'])) : '',
            'comments' => isset($_POST['nfwc_comments']) ? sanitize_textarea_field(wp_unslash($_POST['nfwc_comments'])) : '',
            'quantity' => isset($_POST['nfwc_quantity']) ? absint(wp_unslash($_POST['nfwc_quantity'])) : 0,
            'runs' => isset($_POST['nfwc_runs']) ? absint(wp_unslash($_POST['nfwc_runs'])) : 0,
            'interval' => isset($_POST['nfwc_interval']) ? absint(wp_unslash($_POST['nfwc_interval'])) : 0,
            'base_price' => $cart_product ? (float) $cart_product->get_price('edit') : 0,
            'unique_key' => md5(microtime(true) . wp_rand()),
        );
        return $cart_item_data;
    }

    public function force_single_woocommerce_quantity($sold_individually, $product) {
        if ($product instanceof WC_Product) {
            $product_id = self::resolve_product_id($product->get_id());
            if (self::is_enabled($product_id)) {
                return true;
            }
        }
        return $sold_individually;
    }

    public function force_add_to_cart_quantity($quantity, $product_id) {
        $product_id = self::resolve_product_id($product_id);
        if (self::is_enabled($product_id)) {
            return 1;
        }
        return $quantity;
    }

    public function apply_dynamic_cart_price($cart) {
        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }
        if (!$cart || !is_object($cart)) {
            return;
        }
        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            if (empty($cart_item['nfwc']) || empty($cart_item['data']) || !$cart_item['data'] instanceof WC_Product) {
                continue;
            }
            $product_id = self::resolve_product_id($cart_item['variation_id'] ?: $cart_item['product_id']);
            if (!self::is_enabled($product_id)) {
                continue;
            }
            $config = self::get_config($product_id);
            $base_price = isset($cart_item['nfwc']['base_price']) ? (float) $cart_item['nfwc']['base_price'] : (float) $cart_item['data']->get_price('edit');
            $multiplier = $this->calculate_price_multiplier($config, $cart_item['nfwc']);
            $cart_item['data']->set_price(max(0, $base_price * $multiplier));
        }
    }

    public function display_cart_item_data($item_data, $cart_item) {
        if (empty($cart_item['nfwc'])) {
            return $item_data;
        }
        $data = $cart_item['nfwc'];
        if (!empty($data['link'])) {
            $item_data[] = array('key' => __('Link', 'neofollower-smm-reseller-api-for-woocommerce'), 'value' => esc_html($data['link']));
        }
        if (!empty($data['quantity'])) {
            $item_data[] = array('key' => __('Service quantity', 'neofollower-smm-reseller-api-for-woocommerce'), 'value' => absint($data['quantity']));
        }
        if (!empty($data['comments'])) {
            $item_data[] = array('key' => __('Comments', 'neofollower-smm-reseller-api-for-woocommerce'), 'value' => nl2br(esc_html($data['comments'])));
        }
        if (!empty($data['runs'])) {
            $item_data[] = array('key' => __('Runs', 'neofollower-smm-reseller-api-for-woocommerce'), 'value' => absint($data['runs']));
        }
        if (!empty($data['interval'])) {
            $item_data[] = array('key' => __('Interval', 'neofollower-smm-reseller-api-for-woocommerce'), 'value' => absint($data['interval']) . ' ' . __('minutes', 'neofollower-smm-reseller-api-for-woocommerce'));
        }
        return $item_data;
    }

    public function add_order_item_meta($item, $cart_item_key, $values, $order) {
        if (empty($values['nfwc'])) {
            return;
        }
        $data = $values['nfwc'];
        $product_id = $item->get_variation_id() ?: $item->get_product_id();
        $resolved_id = self::resolve_product_id($product_id);
        $config = self::get_config($resolved_id);

        $api_quantity = $this->calculate_api_quantity($config, $data, $item->get_quantity());

        $item->add_meta_data('_nfwc_enabled', 'yes', true);
        $item->add_meta_data('_nfwc_product_id', $resolved_id, true);
        $item->add_meta_data('_nfwc_service_id', $config['service_id'], true);
        $item->add_meta_data('_nfwc_service_type', $config['service_type'], true);
        $item->add_meta_data('_nfwc_link', $data['link'], true);
        $item->add_meta_data('_nfwc_api_quantity', $api_quantity, true);
        $item->add_meta_data('_nfwc_comments', $data['comments'], true);
        $item->add_meta_data('_nfwc_runs', absint($data['runs']), true);
        $item->add_meta_data('_nfwc_interval', absint($data['interval']), true);

        $item->add_meta_data(__('Link', 'neofollower-smm-reseller-api-for-woocommerce'), $data['link'], true);
        if ('package' !== $config['service_type']) {
            $item->add_meta_data(__('Service quantity', 'neofollower-smm-reseller-api-for-woocommerce'), $api_quantity, true);
        }
        if (!empty($data['comments'])) {
            $item->add_meta_data(__('Comments', 'neofollower-smm-reseller-api-for-woocommerce'), $data['comments'], true);
        }
    }

    private function calculate_api_quantity($config, $data, $line_quantity) {
        if ('package' === $config['service_type']) {
            return null;
        }
        if ('customer' === $config['quantity_mode']) {
            return max(1, absint($data['quantity']));
        }
        if ('comments_lines' === $config['quantity_mode'] || 'custom_comments' === $config['service_type']) {
            return max(1, $this->count_comment_lines((string) $data['comments']));
        }
        return max(1, absint($config['fixed_quantity']));
    }

    private function get_frontend_price_mode($config) {
        if ('custom_comments' === $config['service_type'] || 'comments_lines' === $config['quantity_mode']) {
            return 'comments';
        }
        if ('drip_feed' === $config['service_type']) {
            return 'drip';
        }
        if ('customer' === $config['quantity_mode'] && 'package' !== $config['service_type']) {
            return 'quantity';
        }
        return 'fixed';
    }

    private function calculate_price_multiplier($config, $data) {
        $price_mode = $this->get_frontend_price_mode($config);
        switch ($price_mode) {
            case 'quantity':
                return max(1, absint(isset($data['quantity']) ? $data['quantity'] : 0));
            case 'comments':
                return max(1, $this->count_comment_lines((string) (isset($data['comments']) ? $data['comments'] : '')));
            case 'drip':
                $runs = max(2, absint(isset($data['runs']) ? $data['runs'] : 0));
                $qty = ('customer' === $config['quantity_mode']) ? max(1, absint(isset($data['quantity']) ? $data['quantity'] : 0)) : 1;
                return max(1, $qty * $runs);
            case 'fixed':
            default:
                return 1;
        }
    }

    private function count_comment_lines($comments) {
        $lines = preg_split('/\r\n|\r|\n/', (string) $comments);
        $count = 0;
        foreach ($lines as $line) {
            if ('' !== trim($line)) {
                $count++;
            }
        }
        return $count;
    }

    public function ajax_search_services() {
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'neofollower-smm-reseller-api-for-woocommerce')), 403);
        }
        check_ajax_referer('nfwc_admin_ajax', 'nonce');
        $term = isset($_GET['term']) ? sanitize_text_field(wp_unslash($_GET['term'])) : '';
        $rows = NFWC_DB::search_services($term, 25);
        $results = array();
        foreach ($rows as $row) {
            $results[] = $this->format_service_for_ajax($row);
        }
        wp_send_json_success(array('results' => $results));
    }

    public function ajax_service_details() {
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'neofollower-smm-reseller-api-for-woocommerce')), 403);
        }
        check_ajax_referer('nfwc_admin_ajax', 'nonce');
        $service_id = isset($_GET['service_id']) ? sanitize_text_field(wp_unslash($_GET['service_id'])) : '';
        $service = $service_id ? NFWC_DB::get_service($service_id) : null;
        if (!$service) {
            wp_send_json_error(array('message' => __('Service not found.', 'neofollower-smm-reseller-api-for-woocommerce')), 404);
        }
        wp_send_json_success(array('service' => $this->format_service_for_ajax($service)));
    }

    private function format_service_for_ajax($row) {
        return array(
            'service_id' => (string) $row->service_id,
            'name' => (string) $row->name,
            'type' => (string) $row->type,
            'category' => (string) $row->category,
            'rate' => (string) $row->rate,
            'min_qty' => (int) $row->min_qty,
            'max_qty' => (int) $row->max_qty,
            'refill' => (string) $row->refill,
            'cancel' => (string) $row->cancel,
            'guessed_type' => self::guess_service_type_from_api_type($row->type),
            'label' => trim((string) $row->service_id . ' — ' . (string) $row->name),
        );
    }
}
