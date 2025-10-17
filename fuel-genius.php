<?php
/**
 * Plugin Name: Fuel Genius
 * Plugin URI: https://thejunaid.in/fuel-genius
 * Description: A comprehensive fuel tracking SaaS solution for WordPress users to manage vehicles and analyze fuel efficiency and costs
 * Version: 1.2.0
 * Author: Junaid Ahmed
 * Author URI: https://thejunaid.in
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: fuel-genius
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('FUEL_GENIUS_VERSION', '1.2.0');
define('FUEL_GENIUS_DB_VERSION', '1.1');
define('FUEL_GENIUS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('FUEL_GENIUS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('FUEL_GENIUS_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Currency and unit constants
define('FUEL_GENIUS_CURRENCY', '₹');
define('FUEL_GENIUS_DISTANCE_UNIT', 'km');
define('FUEL_GENIUS_FUEL_UNIT', 'L');

/**
 * Plugin activation hook
 * Creates database tables and sets up initial configuration
 */
function fuel_genius_activate() {
    require_once FUEL_GENIUS_PLUGIN_DIR . 'includes/activation.php';
    fuel_genius_create_tables();

    // Set database version
    update_option('fuel_genius_db_version', FUEL_GENIUS_DB_VERSION);
}
register_activation_hook(__FILE__, 'fuel_genius_activate');

/**
 * Check and update database schema on plugin load
 * This ensures existing installations get the new columns
 */
function fuel_genius_check_and_update_db() {
    $current_db_version = get_option('fuel_genius_db_version', '0');

    // If database needs update
    if (version_compare($current_db_version, FUEL_GENIUS_DB_VERSION, '<')) {
        global $wpdb;

        $vehicles_table = $wpdb->prefix . 'fuel_genius_vehicles';
        $logs_table = $wpdb->prefix . 'fuel_genius_fuel_logs';

        // Check if tables exist first
        $vehicles_exists = $wpdb->get_var("SHOW TABLES LIKE '$vehicles_table'") === $vehicles_table;
        $logs_exists = $wpdb->get_var("SHOW TABLES LIKE '$logs_table'") === $logs_table;

        if ($vehicles_exists) {
            // Check and add deleted_at to vehicles table
            $column_check = $wpdb->get_results("SHOW COLUMNS FROM $vehicles_table LIKE 'deleted_at'");
            if (empty($column_check)) {
                $wpdb->query("ALTER TABLE $vehicles_table ADD COLUMN deleted_at DATETIME DEFAULT NULL AFTER created_at");
                $wpdb->query("ALTER TABLE $vehicles_table ADD KEY deleted_at (deleted_at)");
            }
        }

        if ($logs_exists) {
            // Check and add deleted_at to logs table
            $column_check = $wpdb->get_results("SHOW COLUMNS FROM $logs_table LIKE 'deleted_at'");
            if (empty($column_check)) {
                $wpdb->query("ALTER TABLE $logs_table ADD COLUMN deleted_at DATETIME DEFAULT NULL AFTER created_at");
                $wpdb->query("ALTER TABLE $logs_table ADD KEY deleted_at (deleted_at)");
            }
        }

        // Update database version
        update_option('fuel_genius_db_version', FUEL_GENIUS_DB_VERSION);
    }
}
add_action('plugins_loaded', 'fuel_genius_check_and_update_db');

/**
 * Plugin deactivation hook
 */
function fuel_genius_deactivate() {
    // Cleanup code can be added here if needed
}
register_deactivation_hook(__FILE__, 'fuel_genius_deactivate');

/**
 * Enqueue frontend styles and scripts
 * Only loads on pages containing the shortcode
 */
function fuel_genius_enqueue_scripts() {
    global $post;

    // Check if the shortcode is present in the current post
    if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'fuel_genius_dashboard')) {
        // Enqueue CSS
        wp_enqueue_style(
            'fuel-genius-style',
            FUEL_GENIUS_PLUGIN_URL . 'assets/css/style.css',
            array(),
            FUEL_GENIUS_VERSION
        );

        // Enqueue login CSS
        wp_enqueue_style(
            'fuel-genius-login-style',
            FUEL_GENIUS_PLUGIN_URL . 'assets/css/login.css',
            array(),
            FUEL_GENIUS_VERSION
        );

        // Enqueue jsPDF for PDF export
        wp_enqueue_script(
            'jspdf',
            'https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js',
            array(),
            '2.5.1',
            true
        );

        // Enqueue SheetJS for Excel export
        wp_enqueue_script(
            'xlsx',
            'https://cdn.sheetjs.com/xlsx-0.20.0/package/dist/xlsx.full.min.js',
            array(),
            '0.20.0',
            true
        );

        // Enqueue main JavaScript
        wp_enqueue_script(
            'fuel-genius-main',
            FUEL_GENIUS_PLUGIN_URL . 'assets/js/main.js',
            array('jquery'),
            FUEL_GENIUS_VERSION,
            true
        );

        // Enqueue login JavaScript
        wp_enqueue_script(
            'fuel-genius-login',
            FUEL_GENIUS_PLUGIN_URL . 'assets/js/login.js',
            array('jquery'),
            FUEL_GENIUS_VERSION,
            true
        );

        // Localize script with AJAX URL and nonce
        wp_localize_script('fuel-genius-main', 'fuelGeniusAjax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('fuel_genius_nonce'),
            'currency' => FUEL_GENIUS_CURRENCY,
            'distanceUnit' => FUEL_GENIUS_DISTANCE_UNIT,
            'fuelUnit' => FUEL_GENIUS_FUEL_UNIT
        ));

        // Localize login script
        wp_localize_script('fuel-genius-login', 'fuelGeniusLogin', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('fuel_genius_login_nonce')
        ));
    }
}
add_action('wp_enqueue_scripts', 'fuel_genius_enqueue_scripts');

/**
 * Include required files
 */
require_once FUEL_GENIUS_PLUGIN_DIR . 'includes/shortcodes.php';
require_once FUEL_GENIUS_PLUGIN_DIR . 'includes/ajax-handlers.php';
require_once FUEL_GENIUS_PLUGIN_DIR . 'includes/calculations.php';
require_once FUEL_GENIUS_PLUGIN_DIR . 'includes/email-notifications.php';
require_once FUEL_GENIUS_PLUGIN_DIR . 'includes/login-handler.php';

/**
 * Load plugin textdomain for translations
 */
function fuel_genius_load_textdomain() {
    load_plugin_textdomain('fuel-genius', false, dirname(FUEL_GENIUS_PLUGIN_BASENAME) . '/languages');
}
add_action('plugins_loaded', 'fuel_genius_load_textdomain');
