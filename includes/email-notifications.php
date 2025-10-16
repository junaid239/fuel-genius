<?php
/**
 * Email Notifications for Fuel Logs
 * File: includes/email-notifications.php
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Send email with analytics and Excel attachment when fuel log is added
 */
function fuel_genius_send_log_email($log_id, $user_id, $vehicle_id, $log_data) {
    
    // DEBUG: Log that function was triggered
    error_log('=== EMAIL NOTIFICATION TRIGGERED ===');
    error_log('Log ID: ' . $log_id);
    error_log('User ID: ' . $user_id);
    error_log('Vehicle ID: ' . $vehicle_id);
    
    // Get user email
    $user = get_userdata($user_id);
    if (!$user) {
        error_log('ERROR: User not found for ID ' . $user_id);
        return;
    }
    $email_to = $user->user_email;
    error_log('User email: ' . $email_to);
    
    // Get vehicle details
    global $wpdb;
    $vehicle = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}fuel_genius_vehicles WHERE id = %d",
        $vehicle_id
    ));
    
    if (!$vehicle) {
        error_log('ERROR: Vehicle not found for ID ' . $vehicle_id);
        return;
    }
    
    $vehicle_name = $vehicle->year . ' ' . $vehicle->make . ' ' . $vehicle->model;
    error_log('Vehicle name: ' . $vehicle_name);
    
    // Get analytics for this vehicle (all time)
    $analytics = fuel_genius_get_analytics_data($user_id, $vehicle_id, 'all');
    error_log('Analytics data retrieved');
    
    // Calculate efficiency for this specific log
    $efficiency = fuel_genius_calculate_efficiency($vehicle_id, $log_id);
    $efficiency_text = 'N/A';
    
    if ($efficiency === 'partial') {
        $efficiency_text = 'Partial Fill';
    } elseif ($efficiency && is_numeric($efficiency)) {
        $efficiency_text = number_format($efficiency, 2) . ' km/L';
    }
    error_log('Efficiency calculated: ' . $efficiency_text);
    
    // Generate Excel file
    $excel_file = fuel_genius_create_excel_attachment($user_id, $vehicle_id, $vehicle_name, $analytics);
    if ($excel_file) {
        error_log('Excel file created: ' . $excel_file);
    } else {
        error_log('WARNING: Excel file creation failed');
    }
    
    // Email subject
    $subject = 'Fuel Log Added: ' . $vehicle_name . ' - ' . date('M d, Y', strtotime($log_data['log_date']));
    error_log('Email subject: ' . $subject);
    
    // Email body
    $message = fuel_genius_get_email_html($vehicle_name, $log_data, $efficiency_text, $analytics);
    error_log('Email HTML generated (length: ' . strlen($message) . ' chars)');
    
    // Headers
    $headers = array(
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>'
    );
    
    // Attachments
    $attachments = array();
    if ($excel_file && file_exists($excel_file)) {
        $attachments[] = $excel_file;
        error_log('Attachment added: ' . $excel_file);
    }
    
    // Send email
    error_log('Attempting to send email to: ' . $email_to);
    error_log('From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>');
    
    $sent = wp_mail($email_to, $subject, $message, $headers, $attachments);
    
    error_log('Email send result: ' . ($sent ? 'SUCCESS' : 'FAILED'));
    
    if (!$sent) {
        error_log('ERROR: wp_mail returned false');
    }
    
    // Clean up temp file
    if ($excel_file && file_exists($excel_file)) {
        @unlink($excel_file);
        error_log('Temporary file deleted');
    }
    
    error_log('=== EMAIL NOTIFICATION COMPLETE ===');
    
    return $sent;
}

/**
 * Create Excel attachment with analytics
 */
function fuel_genius_create_excel_attachment($user_id, $vehicle_id, $vehicle_name, $analytics) {
    
    error_log('Creating Excel attachment...');
    
    $upload_dir = wp_upload_dir();
    $temp_dir = $upload_dir['basedir'] . '/fuel-genius-temp/';
    
    // Create temp directory
    if (!file_exists($temp_dir)) {
        wp_mkdir_p($temp_dir);
        error_log('Created temp directory: ' . $temp_dir);
    }
    
    $filename = 'fuel-analytics-' . date('Y-m-d-His') . '.csv';
    $filepath = $temp_dir . $filename;
    
    $file = @fopen($filepath, 'w');
    
    if (!$file) {
        error_log('ERROR: Could not create file at ' . $filepath);
        return false;
    }
    
    // UTF-8 BOM for Excel
    fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Header
    fputcsv($file, array('Fuel Genius Analytics Report'));
    fputcsv($file, array('Vehicle:', $vehicle_name));
    fputcsv($file, array('Generated:', date('Y-m-d H:i:s')));
    fputcsv($file, array(''));
    
    // Analytics Summary
    fputcsv($file, array('ANALYTICS SUMMARY'));
    fputcsv($file, array('Metric', 'Value'));
    fputcsv($file, array('Total Fuel Logs', $analytics['total_logs']));
    fputcsv($file, array('Average Efficiency (km/L)', number_format($analytics['avg_efficiency'], 2)));
    fputcsv($file, array('Best Efficiency (km/L)', number_format($analytics['best_efficiency'], 2)));
    fputcsv($file, array('Worst Efficiency (km/L)', number_format($analytics['worst_efficiency'], 2)));
    fputcsv($file, array('Total Distance (km)', number_format($analytics['total_distance'])));
    fputcsv($file, array('Total Fuel (L)', number_format($analytics['total_fuel'], 2)));
    fputcsv($file, array('Total Spending (Rs)', number_format($analytics['total_spending'], 2)));
    fputcsv($file, array('Avg Cost Per Fill-Up (Rs)', number_format($analytics['avg_cost_per_fillup'], 2)));
    fputcsv($file, array('Days Since Last Fill-Up', $analytics['days_since_last_fillup']));
    
    if (isset($analytics['estimated_range']) && $analytics['estimated_range'] > 0) {
        fputcsv($file, array('Estimated Range (km)', number_format($analytics['estimated_range'], 2)));
    }
    
    fputcsv($file, array(''));
    
    // Recent logs
    global $wpdb;
    $logs = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}fuel_genius_fuel_logs 
        WHERE user_id = %d AND vehicle_id = %d 
        ORDER BY log_date DESC, odometer_reading DESC 
        LIMIT 10",
        $user_id, $vehicle_id
    ), ARRAY_A);
    
    if (!empty($logs)) {
        fputcsv($file, array('RECENT FUEL LOGS (Last 10)'));
        fputcsv($file, array('Date', 'Odometer (km)', 'Fuel (L)', 'Price (Rs/L)', 'Total Cost (Rs)', 'Full Tank'));
        
        foreach ($logs as $log) {
            fputcsv($file, array(
                $log['log_date'],
                $log['odometer_reading'],
                number_format($log['fuel_quantity'], 2),
                number_format($log['price_per_unit'], 2),
                number_format($log['total_cost'], 2),
                $log['filled_to_full_tank'] ? 'Yes' : 'No'
            ));
        }
    }
    
    fclose($file);
    
    error_log('Excel file created successfully: ' . $filepath);
    return $filepath;
}

/**
 * Get HTML email template
 */
function fuel_genius_get_email_html($vehicle_name, $log_data, $efficiency_text, $analytics) {
    ob_start();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; background: #f4f4f4; margin: 0; padding: 0; }
            .container { max-width: 600px; margin: 20px auto; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px 20px; text-align: center; }
            .header h1 { margin: 0; font-size: 24px; }
            .content { padding: 30px 20px; }
            .section { margin-bottom: 25px; }
            .section-title { font-size: 18px; font-weight: 700; color: #2c3e50; margin-bottom: 15px; border-bottom: 2px solid #667eea; padding-bottom: 8px; }
            .log-details { background: #f8f9fa; border-left: 4px solid #667eea; padding: 20px; border-radius: 4px; }
            .detail-row { padding: 10px 0; border-bottom: 1px solid #e9ecef; }
            .detail-row:last-child { border-bottom: none; }
            .detail-label { font-weight: 600; color: #666; display: block; margin-bottom: 4px; font-size: 13px; }
            .detail-value { font-weight: 700; color: #2c3e50; font-size: 16px; display: block; }
            .analytics-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; margin-top: 15px; }
            .analytics-card { background: #f8f9fa; padding: 15px; border-radius: 6px; text-align: center; }
            .analytics-label { font-size: 12px; color: #666; margin-bottom: 5px; }
            .analytics-value { font-size: 20px; font-weight: 700; color: #667eea; }
            .efficiency-badge { display: inline-block; background: #27ae60; color: white; padding: 10px 20px; border-radius: 20px; font-weight: 600; margin: 15px 0; font-size: 16px; }
            .footer { background: #2c3e50; color: white; padding: 20px; text-align: center; font-size: 14px; }
            .button { display: inline-block; background: #3498db; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; margin: 15px 0; }
            @media only screen and (max-width: 600px) {
                .analytics-grid { grid-template-columns: 1fr; }
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>Fuel Log Added</h1>
                <p style="margin: 5px 0 0 0; opacity: 0.9;"><?php echo esc_html($vehicle_name); ?></p>
            </div>
            
            <div class="content">
                <div class="section">
                    <div class="section-title">Current Fill-Up Details</div>
                    <div class="log-details">
                        <div class="detail-row">
                            <span class="detail-label">Date</span>
                            <span class="detail-value"><?php echo date('M d, Y', strtotime($log_data['log_date'])); ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Odometer</span>
                            <span class="detail-value"><?php echo number_format($log_data['odometer_reading']); ?> km</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Fuel Quantity</span>
                            <span class="detail-value"><?php echo number_format($log_data['fuel_quantity'], 2); ?> L</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Price Per Litre</span>
                            <span class="detail-value">Rs <?php echo number_format($log_data['price_per_unit'], 2); ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Total Cost</span>
                            <span class="detail-value">Rs <?php echo number_format($log_data['total_cost'], 2); ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Full Tank</span>
                            <span class="detail-value"><?php echo $log_data['filled_to_full_tank'] ? 'Yes' : 'No'; ?></span>
                        </div>
                    </div>
                    <div style="text-align: center;">
                        <span class="efficiency-badge">Efficiency: <?php echo esc_html($efficiency_text); ?></span>
                    </div>
                </div>
                
                <div class="section">
                    <div class="section-title">Vehicle Analytics (All Time)</div>
                    <div class="analytics-grid">
                        <div class="analytics-card">
                            <div class="analytics-label">Total Logs</div>
                            <div class="analytics-value"><?php echo $analytics['total_logs']; ?></div>
                        </div>
                        <div class="analytics-card">
                            <div class="analytics-label">Avg Efficiency</div>
                            <div class="analytics-value"><?php echo number_format($analytics['avg_efficiency'], 2); ?> km/L</div>
                        </div>
                        <div class="analytics-card">
                            <div class="analytics-label">Total Distance</div>
                            <div class="analytics-value"><?php echo number_format($analytics['total_distance']); ?> km</div>
                        </div>
                        <div class="analytics-card">
                            <div class="analytics-label">Total Fuel</div>
                            <div class="analytics-value"><?php echo number_format($analytics['total_fuel'], 2); ?> L</div>
                        </div>
                        <div class="analytics-card">
                            <div class="analytics-label">Total Spent</div>
                            <div class="analytics-value">Rs <?php echo number_format($analytics['total_spending'], 2); ?></div>
                        </div>
                        <div class="analytics-card">
                            <div class="analytics-label">Avg Cost/Fill</div>
                            <div class="analytics-value">Rs <?php echo number_format($analytics['avg_cost_per_fillup'], 2); ?></div>
                        </div>
                    </div>
                </div>
                
                <p style="text-align: center; color: #7f8c8d; font-size: 14px;">
                    Detailed analytics and recent logs attached as Excel file
                </p>
                
                <div style="text-align: center;">
                    <a href="<?php echo esc_url(home_url()); ?>" class="button">
                        View Dashboard
                    </a>
                </div>
            </div>
            
            <div class="footer">
                <p style="margin: 0;">Automated notification from Fuel Genius</p>
                <p style="margin: 5px 0 0 0; font-size: 12px; opacity: 0.8;">
                    &copy; <?php echo date('Y'); ?> <?php echo get_bloginfo('name'); ?>
                </p>
            </div>
        </div>
    </body>
    </html>
    <?php
    return ob_get_clean();
}

/**
 * Hook into add fuel log action
 */
add_action('fuel_genius_after_add_log', 'fuel_genius_send_log_email', 10, 4);