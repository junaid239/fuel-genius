<?php
/**
 * Plugin Activation Handler
 * Creates database tables on plugin activation
 */
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}
/**
 * Create plugin database tables
 * Creates wp_fuel_genius_vehicles and wp_fuel_genius_fuel_logs tables
 */
function fuel_genius_create_tables() {
    global $wpdb;
    
    $charset_collate = $wpdb->get_charset_collate();
    
    // Table name with WordPress prefix
    $vehicles_table = $wpdb->prefix . 'fuel_genius_vehicles';
    $logs_table = $wpdb->prefix . 'fuel_genius_fuel_logs';
    
    // SQL for vehicles table
    $vehicles_sql = "CREATE TABLE IF NOT EXISTS $vehicles_table (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT(20) UNSIGNED NOT NULL,
        make VARCHAR(100) NOT NULL,
        model VARCHAR(100) NOT NULL,
        year INT(4) NOT NULL,
        fuel_type VARCHAR(50) NOT NULL,
        tank_capacity DECIMAL(10,2) DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        deleted_at DATETIME DEFAULT NULL,
        PRIMARY KEY (id),
        KEY user_id (user_id),
        KEY created_at (created_at),
        KEY deleted_at (deleted_at)
    ) $charset_collate;";
    
    // SQL for fuel logs table
    $logs_sql = "CREATE TABLE IF NOT EXISTS $logs_table (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        vehicle_id BIGINT(20) UNSIGNED NOT NULL,
        user_id BIGINT(20) UNSIGNED NOT NULL,
        log_date DATE NOT NULL,
        odometer_reading INT(10) UNSIGNED NOT NULL,
        fuel_quantity DECIMAL(10,2) NOT NULL,
        price_per_unit DECIMAL(10,2) NOT NULL,
        total_cost DECIMAL(10,2) NOT NULL,
        filled_to_full_tank TINYINT(1) DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        deleted_at DATETIME DEFAULT NULL,
        PRIMARY KEY (id),
        KEY vehicle_id (vehicle_id),
        KEY user_id (user_id),
        KEY log_date (log_date),
        KEY created_at (created_at),
        KEY deleted_at (deleted_at)
    ) $charset_collate;";
    
    // Include WordPress upgrade functions
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    
    // Execute table creation
    dbDelta($vehicles_sql);
    dbDelta($logs_sql);
    
    // Check if filled_to_full_tank column exists, if not add it
    $column_exists = $wpdb->get_results($wpdb->prepare(
        "SHOW COLUMNS FROM $logs_table LIKE %s",
        'filled_to_full_tank'
    ));
    
    if (empty($column_exists)) {
        $wpdb->query("ALTER TABLE $logs_table ADD COLUMN filled_to_full_tank TINYINT(1) DEFAULT 0 AFTER total_cost");
    }
    
    // Check if deleted_at column exists in vehicles table, if not add it
    $vehicles_deleted_at = $wpdb->get_results($wpdb->prepare(
        "SHOW COLUMNS FROM $vehicles_table LIKE %s",
        'deleted_at'
    ));
    
    if (empty($vehicles_deleted_at)) {
        $wpdb->query("ALTER TABLE $vehicles_table ADD COLUMN deleted_at DATETIME DEFAULT NULL AFTER created_at");
        $wpdb->query("ALTER TABLE $vehicles_table ADD KEY deleted_at (deleted_at)");
    }
    
    // Check if deleted_at column exists in logs table, if not add it
    $logs_deleted_at = $wpdb->get_results($wpdb->prepare(
        "SHOW COLUMNS FROM $logs_table LIKE %s",
        'deleted_at'
    ));
    
    if (empty($logs_deleted_at)) {
        $wpdb->query("ALTER TABLE $logs_table ADD COLUMN deleted_at DATETIME DEFAULT NULL AFTER created_at");
        $wpdb->query("ALTER TABLE $logs_table ADD KEY deleted_at (deleted_at)");
    }
    
    // Store plugin version
    update_option('fuel_genius_version', FUEL_GENIUS_VERSION);
    update_option('fuel_genius_installed', current_time('mysql'));
}