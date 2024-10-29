<?php
/*
Plugin Name: Auto Product Restock for Woo
Description: Automatically updates product stock status on a specified date.
Version: 1.0.5
Author: Xristos Avgeros
Author URI: https://xristosavgeros.com/
Text Domain: auto-product-restock-for-woo
Domain Path: /languages
Requires at least: 5.0
Tested up to: 6.6
Requires PHP: 7.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Load plugin text domain
add_action('plugins_loaded', 'ncwcapr_restock_load_textdomain');
function ncwcapr_restock_load_textdomain() {
    load_plugin_textdomain('auto-product-restock-for-woo', false, dirname(plugin_basename(__FILE__)) . '/languages');
}

// Add settings link on plugin page
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'ncwcapr_restock_add_settings_link');
function ncwcapr_restock_add_settings_link($links) {
    $settings_link = '<a href="admin.php?page=apr-auto-restock-settings">' . esc_html__('Settings', 'auto-product-restock-for-woo') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}

// Include our main plugin class
include_once plugin_dir_path(__FILE__) . 'includes/class-apr-auto-restock.php';

// Include our settings class
include_once plugin_dir_path(__FILE__) . 'includes/class-apr-auto-restock-settings.php';

// Initialize the plugin
add_action('plugins_loaded', 'ncwcapr_restock_init');
function ncwcapr_restock_init() {
    new NCWCAPR_Auto_Restock();
    new NCWCAPR_Auto_Restock_Settings();
}

// Clear scheduled event on plugin deactivation
register_deactivation_hook(__FILE__, 'ncwcapr_restock_clear_event');
function ncwcapr_restock_clear_event() {
    wp_clear_scheduled_hook('ncwcapr_restock_check_dates');
}
