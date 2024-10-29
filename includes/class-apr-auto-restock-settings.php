<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class NCWCAPR_Auto_Restock_Settings {

    public function __construct() {
        add_action('admin_menu', array($this, 'add_settings_page'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('update_option_apr_restock_cron_schedule', array($this, 'update_cron_schedule'), 10, 2);
        add_action('admin_post_apr_restock_sync', array($this, 'handle_sync_now'));
    }

    public function add_settings_page() {
        add_submenu_page(
            'woocommerce',
            esc_html__('Auto Restock Settings', 'auto-product-restock-for-woo'),
            esc_html__('Auto Restock Settings', 'auto-product-restock-for-woo'),
            'manage_options',
            'apr-auto-restock-settings',
            array($this, 'settings_page')
        );
    }

    public function register_settings() {
        register_setting('ncwcapr_restock_settings', 'ncwcapr_restock_display_position');
        register_setting('ncwcapr_restock_settings', 'ncwcapr_restock_cron_schedule');

        add_settings_section(
            'ncwcapr_restock_settings_section',
            esc_html__('Auto Restock Settings', 'auto-product-restock-for-woo'),
            null,
            'apr-auto-restock-settings'
        );

        add_settings_field(
            'ncwcapr_restock_display_position',
            esc_html__('Display Position', 'auto-product-restock-for-woo'),
            array($this, 'display_position_field'),
            'apr-auto-restock-settings',
            'ncwcapr_restock_settings_section'
        );

        add_settings_field(
            'ncwcapr_restock_cron_schedule',
            esc_html__('Cron Schedule', 'auto-product-restock-for-woo'),
            array($this, 'cron_schedule_field'),
            'apr-auto-restock-settings',
            'ncwcapr_restock_settings_section'
        );
    }

    public function display_position_field() {
        $options = get_option('ncwcapr_restock_display_position', 'woocommerce_single_product_summary');
        ?>
        <select name="ncwcapr_restock_display_position">
            <option value="woocommerce_single_product_summary" <?php selected($options, 'woocommerce_single_product_summary'); ?>><?php esc_html_e('After Product Summary', 'auto-product-restock-for-woo'); ?></option>
            <option value="woocommerce_before_single_product_summary" <?php selected($options, 'woocommerce_before_single_product_summary'); ?>><?php esc_html_e('Before Product Summary', 'auto-product-restock-for-woo'); ?></option>
            <option value="woocommerce_product_meta_start" <?php selected($options, 'woocommerce_product_meta_start'); ?>><?php esc_html_e('Before Product Meta', 'auto-product-restock-for-woo'); ?></option>
            <option value="woocommerce_product_meta_end" <?php selected($options, 'woocommerce_product_meta_end'); ?>><?php esc_html_e('After Product Meta', 'auto-product-restock-for-woo'); ?></option>
        </select>
        <?php
    }

    public function cron_schedule_field() {
        $options = get_option('ncwcapr_restock_cron_schedule', 4); // Default to 4 hours
        ?>
        <input type="number" name="ncwcapr_restock_cron_schedule" value="<?php echo esc_attr( $options ); ?>" min="1" />
        <p class="description">
            <?php esc_html_e('Enter the number of hours between each cron job run. Default is 4 hours.', 'auto-product-restock-for-woo'); ?>
            <br>
            <a href="<?php echo esc_url( wp_nonce_url( admin_url('admin-post.php?action=apr_restock_sync'), 'apr_restock_sync_nonce' ) ); ?>"><?php esc_html_e('Sync Now', 'auto-product-restock-for-woo'); ?></a>
        </p>
        <?php
    }


    public function settings_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Auto Restock Settings', 'auto-product-restock-for-woo'); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('ncwcapr_restock_settings');
                do_settings_sections('apr-auto-restock-settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    public function update_cron_schedule($old_value, $value) {
        if ($old_value !== $value) {
            wp_clear_scheduled_hook('ncwcapr_restock_check_dates');
            if (!wp_next_scheduled('ncwcapr_restock_check_dates')) {
                wp_schedule_event(time(), 'ncwcapr_restock_interval', 'ncwcapr_restock_check_dates');
            }
        }
    }

    public function handle_sync_now() {
        if (!current_user_can('manage_options')) {
            return;
        }
        do_action('ncwcapr_restock_check_dates');
        wp_redirect(admin_url('admin.php?page=apr-auto-restock-settings&synced=1'));
        exit;
    }
}
