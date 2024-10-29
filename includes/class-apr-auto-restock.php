<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class NCWCAPR_Auto_Restock {

    public function __construct() {
        // Hook to add custom product field
        add_action('woocommerce_product_options_inventory_product_data', array($this, 'add_restock_date_field'));
        add_action('woocommerce_process_product_meta', array($this, 'save_restock_date_field'));

        // Enqueue date picker scripts and styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_date_picker'));

        // Hook to schedule the event
        add_action('wp', array($this, 'schedule_restock_event'));

        // Hook to handle the scheduled event
        add_action('ncwcapr_restock_check_dates', array($this, 'update_stock_status'));

        // Hook to display restock date on the product page
        $display_position = get_option('ncwcapr_restock_display_position', 'woocommerce_single_product_summary');
        add_action($display_position, array($this, 'display_restock_date'), 20);

        // Add custom cron interval
        add_filter('cron_schedules', array($this, 'add_cron_intervals'));
    }

    public function enqueue_date_picker() {
        wp_enqueue_script('jquery-ui-datepicker');
        wp_enqueue_style('jquery-ui-css', plugin_dir_url(__FILE__) . '../css/jquery-ui.css', array(), '1.12.1'); // Updated to local path and added version
        wp_add_inline_script('jquery-ui-datepicker', 'jQuery(document).ready(function($) {
            $("#_restock_date").datepicker({ dateFormat: "yy-mm-dd" });

            // Show/hide restock quantity based on "Track stock quantity for this product" checkbox
            function toggleRestockQuantity() {
                if ($("input[name=\'_manage_stock\']").is(":checked")) {
                    $("#_restock_quantity_field").show();
                } else {
                    $("#_restock_quantity_field").hide();
                }
            }

            toggleRestockQuantity();
            $("input[name=\'_manage_stock\']").change(toggleRestockQuantity);
        });');

        // Add custom CSS to initially hide the restock quantity field
        wp_add_inline_style('jquery-ui-css', '#_restock_quantity_field { display: none; }');
    }

    public function add_restock_date_field() {
        wp_nonce_field('save_restock_date', 'ncwcapr_restock_nonce'); // Added nonce field

        woocommerce_wp_text_input(array(
            'id' => '_restock_date',
            'label' => esc_html__('Restock Date', 'auto-product-restock-for-woo'),
            'desc_tip' => 'true',
            'description' => esc_html__('Enter the expected restock date (YYYY-MM-DD).', 'auto-product-restock-for-woo'),
            'class' => 'wc-autostock-date'
        ));
        woocommerce_wp_text_input(array(
            'id' => '_restock_quantity',
            'label' => esc_html__('Restock Quantity', 'auto-product-restock-for-woo'),
            'desc_tip' => 'true',
            'description' => esc_html__('Enter the expected restock quantity.', 'auto-product-restock-for-woo'),
            'type' => 'number',
            'custom_attributes' => array(
                'min' => '0'
            ),
            'wrapper_class' => 'hide_if_no_stock_management' // Add this class
        ));
    }

    public function save_restock_date_field($post_id) {
        if ( isset( $_POST['ncwcapr_restock_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ncwcapr_restock_nonce'] ) ), 'save_restock_date' ) ) {
            $restock_date = isset( $_POST['_restock_date'] ) ? sanitize_text_field( wp_unslash( $_POST['_restock_date'] ) ) : '';
            $restock_quantity = isset($_POST['_restock_quantity']) ? intval(sanitize_text_field(wp_unslash($_POST['_restock_quantity']))) : 0;

            if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $restock_date ) ) {
                update_post_meta($post_id, '_restock_date', $restock_date);
            }
            update_post_meta($post_id, '_restock_quantity', $restock_quantity);
        }
    }

    public function schedule_restock_event() {
        if (!wp_next_scheduled('ncwcapr_restock_check_dates')) {
            wp_schedule_event(time(), 'ncwcapr_restock_interval', 'ncwcapr_restock_check_dates');
        }
    }

    public function update_stock_status() {
        $args = array(
            'post_type' => 'product',
            'post_status' => 'publish',
            'meta_query' => array(
                array(
                    'key' => '_restock_date',
                    'value' => gmdate('Y-m-d'), // Changed to gmdate()
                    'compare' => '<=',
                    'type' => 'DATE'
                ),
            ),
        );

        $query = new WP_Query($args);
        error_log('APR Auto Restock: Checking for products to restock. Found ' . $query->found_posts . ' products.');

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $product_id = get_the_ID();
                $product = wc_get_product($product_id);
                $restock_quantity = get_post_meta($product_id, '_restock_quantity', true);

                error_log('APR Auto Restock: Processing product ID ' . $product_id . ' with restock quantity ' . $restock_quantity);

                if ($product->get_manage_stock()) {
                    if ($restock_quantity > 0) {
                        $product->set_stock_quantity($restock_quantity);
                        wc_update_product_stock_status($product_id, 'instock');
                        $product->save();  // Ensure the product is saved after updating the stock
                        error_log('APR Auto Restock: Stock quantity updated for product ID ' . $product_id);
                    }
                } else {
                    update_post_meta($product_id, '_stock_status', 'instock');
                    error_log('APR Auto Restock: Stock status updated for product ID ' . $product_id);
                }
            }
            wp_reset_postdata();
        }
    }

    public function display_restock_date() {
        global $product;

        if (!$product->is_in_stock()) {
            $restock_date = get_post_meta($product->get_id(), '_restock_date', true);
            if ($restock_date) {
                $formatted_date = date_i18n(get_option('date_format'), strtotime($restock_date));
                // Translators: %s is the expected restock date
                echo '<p class="restock-date">' . sprintf(esc_html__('Expected back in stock: %s', 'auto-product-restock-for-woo'), esc_html($formatted_date)) . '</p>';
            }
        }
    }

    public function add_cron_intervals($schedules) {
        $interval = get_option('ncwcapr_restock_cron_schedule', 4) * 3600;
        $schedules['ncwcapr_restock_interval'] = array(
            'interval' => $interval,
            'display' => esc_html__('Auto Restock Interval', 'auto-product-restock-for-woo')
        );
        return $schedules;
    }
}
