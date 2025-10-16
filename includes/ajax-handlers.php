<?php
/**
 * AJAX Handlers
 * Handles all AJAX requests from the frontend
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get all vehicles for current user (excluding deleted)
 */
function fuel_genius_ajax_get_vehicles() {
    check_ajax_referer('fuel_genius_nonce', 'nonce');
    
    $user_id = get_current_user_id();
    
    global $wpdb;
    $table = $wpdb->prefix . 'fuel_genius_vehicles';
    
    $vehicles = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table WHERE user_id = %d AND deleted_at IS NULL ORDER BY created_at DESC",
        $user_id
    ), ARRAY_A);
    
    wp_send_json_success(array('vehicles' => $vehicles));
}
add_action('wp_ajax_fuel_genius_get_vehicles', 'fuel_genius_ajax_get_vehicles');

/**
 * Add a new vehicle
 */
function fuel_genius_ajax_add_vehicle() {
    check_ajax_referer('fuel_genius_nonce', 'nonce');
    
    $user_id = get_current_user_id();
    
    $make = sanitize_text_field($_POST['make']);
    $model = sanitize_text_field($_POST['model']);
    $year = intval($_POST['year']);
    $fuel_type = sanitize_text_field($_POST['fuel_type']);
    $tank_capacity = isset($_POST['tank_capacity']) ? floatval($_POST['tank_capacity']) : 0;
    
    if (empty($make) || empty($model) || $year < 1900) {
        wp_send_json_error(array('message' => __('Please fill all required fields', 'fuel-genius')));
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'fuel_genius_vehicles';
    
    $result = $wpdb->insert($table, array(
        'user_id' => $user_id,
        'make' => $make,
        'model' => $model,
        'year' => $year,
        'fuel_type' => $fuel_type,
        'tank_capacity' => $tank_capacity,
        'created_at' => current_time('mysql')
    ));
    
    if ($result) {
        wp_send_json_success(array('message' => __('Vehicle added successfully!', 'fuel-genius')));
    } else {
        wp_send_json_error(array('message' => __('Failed to add vehicle', 'fuel-genius')));
    }
}
add_action('wp_ajax_fuel_genius_add_vehicle', 'fuel_genius_ajax_add_vehicle');

/**
 * Soft delete a vehicle
 */
function fuel_genius_ajax_delete_vehicle() {
    check_ajax_referer('fuel_genius_nonce', 'nonce');
    
    $user_id = get_current_user_id();
    $vehicle_id = intval($_POST['vehicle_id']);
    
    global $wpdb;
    $vehicles_table = $wpdb->prefix . 'fuel_genius_vehicles';
    $logs_table = $wpdb->prefix . 'fuel_genius_fuel_logs';
    
    // Verify ownership
    $vehicle = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $vehicles_table WHERE id = %d AND user_id = %d AND deleted_at IS NULL",
        $vehicle_id, $user_id
    ));
    
    if (!$vehicle) {
        wp_send_json_error(array('message' => __('Vehicle not found', 'fuel-genius')));
    }
    
    $current_time = current_time('mysql');
    
    // Soft delete all logs for this vehicle
    $wpdb->update(
        $logs_table,
        array('deleted_at' => $current_time),
        array('vehicle_id' => $vehicle_id, 'user_id' => $user_id, 'deleted_at' => null)
    );
    
    // Soft delete vehicle
    $result = $wpdb->update(
        $vehicles_table,
        array('deleted_at' => $current_time),
        array('id' => $vehicle_id, 'user_id' => $user_id)
    );
    
    if ($result !== false) {
        wp_send_json_success(array(
            'message' => __('Vehicle moved to trash', 'fuel-genius'),
            'vehicle_id' => $vehicle_id
        ));
    } else {
        wp_send_json_error(array('message' => __('Failed to delete vehicle', 'fuel-genius')));
    }
}
add_action('wp_ajax_fuel_genius_delete_vehicle', 'fuel_genius_ajax_delete_vehicle');

/**
 * Get trashed vehicles
 */
function fuel_genius_ajax_get_trashed_vehicles() {
    check_ajax_referer('fuel_genius_nonce', 'nonce');
    
    $user_id = get_current_user_id();
    
    global $wpdb;
    $table = $wpdb->prefix . 'fuel_genius_vehicles';
    
    $vehicles = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table 
        WHERE user_id = %d 
        AND deleted_at IS NOT NULL 
        AND deleted_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
        ORDER BY deleted_at DESC",
        $user_id
    ), ARRAY_A);
    
    wp_send_json_success(array('vehicles' => $vehicles));
}
add_action('wp_ajax_fuel_genius_get_trashed_vehicles', 'fuel_genius_ajax_get_trashed_vehicles');

/**
 * Restore a vehicle from trash
 */
function fuel_genius_ajax_restore_vehicle() {
    check_ajax_referer('fuel_genius_nonce', 'nonce');
    
    $user_id = get_current_user_id();
    $vehicle_id = intval($_POST['vehicle_id']);
    
    global $wpdb;
    $vehicles_table = $wpdb->prefix . 'fuel_genius_vehicles';
    $logs_table = $wpdb->prefix . 'fuel_genius_fuel_logs';
    
    // Verify ownership
    $vehicle = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $vehicles_table WHERE id = %d AND user_id = %d AND deleted_at IS NOT NULL",
        $vehicle_id, $user_id
    ));
    
    if (!$vehicle) {
        wp_send_json_error(array('message' => __('Vehicle not found in trash', 'fuel-genius')));
    }
    
    // Restore vehicle
    $result = $wpdb->update(
        $vehicles_table,
        array('deleted_at' => null),
        array('id' => $vehicle_id, 'user_id' => $user_id)
    );
    
    // Restore all logs for this vehicle
    $wpdb->update(
        $logs_table,
        array('deleted_at' => null),
        array('vehicle_id' => $vehicle_id, 'user_id' => $user_id)
    );
    
    if ($result !== false) {
        wp_send_json_success(array('message' => __('Vehicle restored successfully!', 'fuel-genius')));
    } else {
        wp_send_json_error(array('message' => __('Failed to restore vehicle', 'fuel-genius')));
    }
}
add_action('wp_ajax_fuel_genius_restore_vehicle', 'fuel_genius_ajax_restore_vehicle');

/**
 * Permanently delete a vehicle
 */
function fuel_genius_ajax_permanent_delete_vehicle() {
    check_ajax_referer('fuel_genius_nonce', 'nonce');
    
    $user_id = get_current_user_id();
    $vehicle_id = intval($_POST['vehicle_id']);
    
    global $wpdb;
    $vehicles_table = $wpdb->prefix . 'fuel_genius_vehicles';
    $logs_table = $wpdb->prefix . 'fuel_genius_fuel_logs';
    
    // Verify ownership and that it's in trash
    $vehicle = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $vehicles_table WHERE id = %d AND user_id = %d AND deleted_at IS NOT NULL",
        $vehicle_id, $user_id
    ));
    
    if (!$vehicle) {
        wp_send_json_error(array('message' => __('Vehicle not found in trash', 'fuel-genius')));
    }
    
    // Permanently delete all logs for this vehicle
    $wpdb->delete($logs_table, array('vehicle_id' => $vehicle_id, 'user_id' => $user_id));
    
    // Permanently delete vehicle
    $result = $wpdb->delete($vehicles_table, array('id' => $vehicle_id, 'user_id' => $user_id));
    
    if ($result) {
        wp_send_json_success(array('message' => __('Vehicle permanently deleted!', 'fuel-genius')));
    } else {
        wp_send_json_error(array('message' => __('Failed to permanently delete vehicle', 'fuel-genius')));
    }
}
add_action('wp_ajax_fuel_genius_permanent_delete_vehicle', 'fuel_genius_ajax_permanent_delete_vehicle');

/**
 * Add fuel log
 */
function fuel_genius_ajax_add_fuel_log() {
    check_ajax_referer('fuel_genius_nonce', 'nonce');
    
    $user_id = get_current_user_id();
    
    $vehicle_id = intval($_POST['vehicle_id']);
    $log_date = sanitize_text_field($_POST['log_date']);
    $odometer_reading = floatval($_POST['odometer_reading']);
    $fuel_quantity = floatval($_POST['fuel_quantity']);
    $price_per_unit = floatval($_POST['price_per_unit']);
    $total_cost = floatval($_POST['total_cost']);
    $filled_to_full_tank = isset($_POST['filled_to_full_tank']) ? intval($_POST['filled_to_full_tank']) : 0;
    
    if ($vehicle_id <= 0 || empty($log_date) || $odometer_reading <= 0 || $fuel_quantity <= 0) {
        wp_send_json_error(array('message' => __('Please fill all required fields', 'fuel-genius')));
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'fuel_genius_fuel_logs';
    
    $result = $wpdb->insert($table, array(
        'user_id' => $user_id,
        'vehicle_id' => $vehicle_id,
        'log_date' => $log_date,
        'odometer_reading' => $odometer_reading,
        'fuel_quantity' => $fuel_quantity,
        'price_per_unit' => $price_per_unit,
        'total_cost' => $total_cost,
        'filled_to_full_tank' => $filled_to_full_tank,
        'created_at' => current_time('mysql')
    ));
    
    if ($result) {
        $log_id = $wpdb->insert_id;
        
        // Trigger email notification
        $log_data = array(
            'log_date' => $log_date,
            'odometer_reading' => $odometer_reading,
            'fuel_quantity' => $fuel_quantity,
            'price_per_unit' => $price_per_unit,
            'total_cost' => $total_cost,
            'filled_to_full_tank' => $filled_to_full_tank
        );
        
        do_action('fuel_genius_after_add_log', $log_id, $user_id, $vehicle_id, $log_data);
        
        wp_send_json_success(array('message' => __('Fuel log added successfully!', 'fuel-genius')));
    } else {
        wp_send_json_error(array('message' => __('Failed to add fuel log', 'fuel-genius')));
    }
}
add_action('wp_ajax_fuel_genius_add_fuel_log', 'fuel_genius_ajax_add_fuel_log');

/**
 * Get recent fuel logs (excluding deleted)
 */
function fuel_genius_ajax_get_recent_logs() {
    check_ajax_referer('fuel_genius_nonce', 'nonce');
    
    $user_id = get_current_user_id();
    $vehicle_id = intval($_POST['vehicle_id']);
    
    global $wpdb;
    $table = $wpdb->prefix . 'fuel_genius_fuel_logs';
    
    $logs = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table 
        WHERE user_id = %d AND vehicle_id = %d AND deleted_at IS NULL
        ORDER BY log_date DESC, odometer_reading DESC 
        LIMIT 5",
        $user_id, $vehicle_id
    ), ARRAY_A);
    
    // Calculate efficiency for each log
    foreach ($logs as &$log) {
        $log['efficiency'] = fuel_genius_calculate_efficiency($vehicle_id, $log['id']);
    }
    
    wp_send_json_success(array('logs' => $logs));
}
add_action('wp_ajax_fuel_genius_get_recent_logs', 'fuel_genius_ajax_get_recent_logs');

/**
 * Soft delete fuel log
 */
function fuel_genius_ajax_delete_fuel_log() {
    check_ajax_referer('fuel_genius_nonce', 'nonce');
    
    $user_id = get_current_user_id();
    $log_id = intval($_POST['log_id']);
    
    global $wpdb;
    $table = $wpdb->prefix . 'fuel_genius_fuel_logs';
    
    // Get log data before deletion
    $log = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table WHERE id = %d AND user_id = %d AND deleted_at IS NULL",
        $log_id, $user_id
    ), ARRAY_A);
    
    if (!$log) {
        wp_send_json_error(array('message' => __('Log not found', 'fuel-genius')));
    }
    
    $result = $wpdb->update(
        $table,
        array('deleted_at' => current_time('mysql')),
        array('id' => $log_id, 'user_id' => $user_id)
    );
    
    if ($result !== false) {
        wp_send_json_success(array(
            'message' => __('Fuel log moved to trash', 'fuel-genius'),
            'log_id' => $log_id
        ));
    } else {
        wp_send_json_error(array('message' => __('Failed to delete fuel log', 'fuel-genius')));
    }
}
add_action('wp_ajax_fuel_genius_delete_fuel_log', 'fuel_genius_ajax_delete_fuel_log');

/**
 * Get trashed fuel logs
 */
function fuel_genius_ajax_get_trashed_logs() {
    check_ajax_referer('fuel_genius_nonce', 'nonce');
    
    $user_id = get_current_user_id();
    
    global $wpdb;
    $logs_table = $wpdb->prefix . 'fuel_genius_fuel_logs';
    $vehicles_table = $wpdb->prefix . 'fuel_genius_vehicles';
    
    $logs = $wpdb->get_results($wpdb->prepare(
        "SELECT l.*, v.make, v.model, v.year 
        FROM $logs_table l
        LEFT JOIN $vehicles_table v ON l.vehicle_id = v.id
        WHERE l.user_id = %d 
        AND l.deleted_at IS NOT NULL 
        AND l.deleted_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
        ORDER BY l.deleted_at DESC",
        $user_id
    ), ARRAY_A);
    
    wp_send_json_success(array('logs' => $logs));
}
add_action('wp_ajax_fuel_genius_get_trashed_logs', 'fuel_genius_ajax_get_trashed_logs');

/**
 * Restore a fuel log from trash
 */
function fuel_genius_ajax_restore_log() {
    check_ajax_referer('fuel_genius_nonce', 'nonce');
    
    $user_id = get_current_user_id();
    $log_id = intval($_POST['log_id']);
    
    global $wpdb;
    $table = $wpdb->prefix . 'fuel_genius_fuel_logs';
    
    $result = $wpdb->update(
        $table,
        array('deleted_at' => null),
        array('id' => $log_id, 'user_id' => $user_id)
    );
    
    if ($result !== false) {
        wp_send_json_success(array('message' => __('Fuel log restored successfully!', 'fuel-genius')));
    } else {
        wp_send_json_error(array('message' => __('Failed to restore fuel log', 'fuel-genius')));
    }
}
add_action('wp_ajax_fuel_genius_restore_log', 'fuel_genius_ajax_restore_log');

/**
 * Permanently delete a fuel log
 */
function fuel_genius_ajax_permanent_delete_log() {
    check_ajax_referer('fuel_genius_nonce', 'nonce');
    
    $user_id = get_current_user_id();
    $log_id = intval($_POST['log_id']);
    
    global $wpdb;
    $table = $wpdb->prefix . 'fuel_genius_fuel_logs';
    
    $result = $wpdb->delete($table, array('id' => $log_id, 'user_id' => $user_id));
    
    if ($result) {
        wp_send_json_success(array('message' => __('Fuel log permanently deleted!', 'fuel-genius')));
    } else {
        wp_send_json_error(array('message' => __('Failed to permanently delete fuel log', 'fuel-genius')));
    }
}
add_action('wp_ajax_fuel_genius_permanent_delete_log', 'fuel_genius_ajax_permanent_delete_log');

/**
 * Undo deletion (restore immediately)
 */
function fuel_genius_ajax_undo_delete() {
    check_ajax_referer('fuel_genius_nonce', 'nonce');
    
    $user_id = get_current_user_id();
    $item_type = sanitize_text_field($_POST['item_type']); // 'vehicle' or 'log'
    $item_id = intval($_POST['item_id']);
    
    global $wpdb;
    
    if ($item_type === 'vehicle') {
        $vehicles_table = $wpdb->prefix . 'fuel_genius_vehicles';
        $logs_table = $wpdb->prefix . 'fuel_genius_fuel_logs';
        
        // Restore vehicle
        $result = $wpdb->update(
            $vehicles_table,
            array('deleted_at' => null),
            array('id' => $item_id, 'user_id' => $user_id)
        );
        
        // Restore all logs for this vehicle
        $wpdb->update(
            $logs_table,
            array('deleted_at' => null),
            array('vehicle_id' => $item_id, 'user_id' => $user_id)
        );
        
        if ($result !== false) {
            wp_send_json_success(array('message' => __('Vehicle restored!', 'fuel-genius')));
        }
    } elseif ($item_type === 'log') {
        $table = $wpdb->prefix . 'fuel_genius_fuel_logs';
        
        $result = $wpdb->update(
            $table,
            array('deleted_at' => null),
            array('id' => $item_id, 'user_id' => $user_id)
        );
        
        if ($result !== false) {
            wp_send_json_success(array('message' => __('Fuel log restored!', 'fuel-genius')));
        }
    }
    
    wp_send_json_error(array('message' => __('Failed to undo deletion', 'fuel-genius')));
}
add_action('wp_ajax_fuel_genius_undo_delete', 'fuel_genius_ajax_undo_delete');

/**
 * Update fuel log
 */
function fuel_genius_ajax_update_fuel_log() {
    check_ajax_referer('fuel_genius_nonce', 'nonce');
    
    $user_id = get_current_user_id();
    $log_id = intval($_POST['log_id']);
    
    $log_date = sanitize_text_field($_POST['log_date']);
    $odometer_reading = floatval($_POST['odometer_reading']);
    $fuel_quantity = floatval($_POST['fuel_quantity']);
    $price_per_unit = floatval($_POST['price_per_unit']);
    $total_cost = floatval($_POST['total_cost']);
    $filled_to_full_tank = isset($_POST['filled_to_full_tank']) ? intval($_POST['filled_to_full_tank']) : 0;
    
    global $wpdb;
    $table = $wpdb->prefix . 'fuel_genius_fuel_logs';
    
    // Verify ownership
    $log = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table WHERE id = %d AND user_id = %d",
        $log_id, $user_id
    ));
    
    if (!$log) {
        wp_send_json_error(array('message' => __('Log not found', 'fuel-genius')));
    }
    
    $result = $wpdb->update(
        $table,
        array(
            'log_date' => $log_date,
            'odometer_reading' => $odometer_reading,
            'fuel_quantity' => $fuel_quantity,
            'price_per_unit' => $price_per_unit,
            'total_cost' => $total_cost,
            'filled_to_full_tank' => $filled_to_full_tank
        ),
        array('id' => $log_id, 'user_id' => $user_id)
    );
    
    if ($result !== false) {
        wp_send_json_success(array('message' => __('Fuel log updated successfully!', 'fuel-genius')));
    } else {
        wp_send_json_error(array('message' => __('Failed to update fuel log', 'fuel-genius')));
    }
}
add_action('wp_ajax_fuel_genius_update_fuel_log', 'fuel_genius_ajax_update_fuel_log');

/**
 * Get analytics data
 */
function fuel_genius_ajax_get_analytics() {
    check_ajax_referer('fuel_genius_nonce', 'nonce');
    
    $user_id = get_current_user_id();
    $vehicle_id = isset($_POST['vehicle_id']) ? intval($_POST['vehicle_id']) : 0;
    $period = isset($_POST['period']) ? sanitize_text_field($_POST['period']) : '30';
    
    $analytics = fuel_genius_get_analytics_data($user_id, $vehicle_id, $period);
    
    wp_send_json_success($analytics);
}
add_action('wp_ajax_fuel_genius_get_analytics', 'fuel_genius_ajax_get_analytics');

/**
 * Get last price for vehicle
 */
function fuel_genius_ajax_get_last_price() {
    check_ajax_referer('fuel_genius_nonce', 'nonce');
    
    $vehicle_id = isset($_POST['vehicle_id']) ? intval($_POST['vehicle_id']) : 0;
    
    if ($vehicle_id <= 0) {
        wp_send_json_error(array('message' => __('Invalid vehicle ID', 'fuel-genius')));
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'fuel_genius_fuel_logs';
    
    $last_price = $wpdb->get_var($wpdb->prepare(
        "SELECT price_per_unit FROM $table 
        WHERE vehicle_id = %d AND deleted_at IS NULL
        ORDER BY log_date DESC, created_at DESC 
        LIMIT 1",
        $vehicle_id
    ));
    
    wp_send_json_success(array(
        'last_price' => $last_price ? floatval($last_price) : 0
    ));
}
add_action('wp_ajax_fuel_genius_get_last_price', 'fuel_genius_ajax_get_last_price');

/**
 * Get last odometer reading for vehicle
 */
function fuel_genius_ajax_get_last_odometer() {
    check_ajax_referer('fuel_genius_nonce', 'nonce');
    
    $vehicle_id = isset($_POST['vehicle_id']) ? intval($_POST['vehicle_id']) : 0;
    
    if ($vehicle_id <= 0) {
        wp_send_json_error(array('message' => __('Invalid vehicle ID', 'fuel-genius')));
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'fuel_genius_fuel_logs';
    
    $last_odometer = $wpdb->get_var($wpdb->prepare(
        "SELECT odometer_reading FROM $table 
        WHERE vehicle_id = %d AND deleted_at IS NULL
        ORDER BY log_date DESC, odometer_reading DESC 
        LIMIT 1",
        $vehicle_id
    ));
    
    wp_send_json_success(array(
        'last_odometer' => $last_odometer ? floatval($last_odometer) : 0
    ));
}
add_action('wp_ajax_fuel_genius_get_last_odometer', 'fuel_genius_ajax_get_last_odometer');

/**
 * Generate report
 */
function fuel_genius_ajax_generate_report() {
    check_ajax_referer('fuel_genius_nonce', 'nonce');
    
    $user_id = get_current_user_id();
    $vehicle_id = isset($_POST['vehicle_id']) ? sanitize_text_field($_POST['vehicle_id']) : 'all';
    $start_date = sanitize_text_field($_POST['start_date']);
    $end_date = sanitize_text_field($_POST['end_date']);
    
    $report = fuel_genius_generate_report($user_id, $vehicle_id, $start_date, $end_date);
    
    wp_send_json_success($report);
}
add_action('wp_ajax_fuel_genius_generate_report', 'fuel_genius_ajax_generate_report');