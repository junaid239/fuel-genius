<?php
/**
 * Calculations and Analytics Functions
 * Handles all efficiency calculations, analytics, insights, and reports
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Calculate fuel efficiency for a specific log entry
 * 
 * IMPORTANT LOGIC (Updated with Full Tank Method):
 * Efficiency is only calculated when BOTH fill-ups (current and next) were filled to full tank.
 * 
 * When you fill to full tank:
 * - The fuel you add represents the fuel consumed since the last full tank fill
 * - Efficiency = Distance traveled / Fuel added
 * 
 * This function calculates: "What efficiency did I get from the fuel I added in THIS log?"
 * 
 * @param int $vehicle_id Vehicle ID
 * @param int $log_id Current log ID
 * @return float|null|string Efficiency in km/L, null if cannot calculate, or 'partial' for partial fills
 */
function fuel_genius_calculate_efficiency($vehicle_id, $log_id) {
    global $wpdb;
    $table = $wpdb->prefix . 'fuel_genius_fuel_logs';
    
    // Get current log (the fill-up we're calculating efficiency for)
    $current_log = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table WHERE id = %d AND vehicle_id = %d AND deleted_at IS NULL",
        $log_id, $vehicle_id
    ));
    
    if (!$current_log) {
        return null;
    }
    
    // Check if this was a full tank fill
    if ($current_log->filled_to_full_tank != 1) {
        return 'partial'; // Partial fill - cannot calculate accurate efficiency
    }
    
    // Get previous full tank fill (to calculate distance traveled)
    $previous_log = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table 
        WHERE vehicle_id = %d 
        AND filled_to_full_tank = 1
        AND deleted_at IS NULL
        AND (log_date < %s OR (log_date = %s AND odometer_reading < %d))
        ORDER BY log_date DESC, odometer_reading DESC 
        LIMIT 1",
        $vehicle_id, 
        $current_log->log_date,
        $current_log->log_date,
        $current_log->odometer_reading
    ));
    
    if (!$previous_log) {
        return null; // No previous full tank fill to calculate efficiency
    }
    
    // Calculate distance traveled since last full tank
    $distance = $current_log->odometer_reading - $previous_log->odometer_reading;
    
    // Validate the data
    if ($distance <= 0) {
        return null; // Invalid: current odometer should be higher
    }
    
    if ($current_log->fuel_quantity <= 0) {
        return null; // Invalid: fuel quantity must be positive
    }
    
    // Calculate efficiency: distance traveled / fuel added at CURRENT fill-up
    // Since tank was full previously and full now, fuel added = fuel consumed
    $efficiency = $distance / $current_log->fuel_quantity;
    
    return round($efficiency, 2);
}

/**
 * Get comprehensive analytics data for a user
 * 
 * @param int $user_id User ID
 * @param int $vehicle_id Vehicle ID (0 for all vehicles)
 * @param string $period Time period ('7', '30', '90', '180', '365', 'all')
 * @return array Analytics data
 */
function fuel_genius_get_analytics_data($user_id, $vehicle_id = 0, $period = '30') {
    global $wpdb;
    $logs_table = $wpdb->prefix . 'fuel_genius_fuel_logs';
    $vehicles_table = $wpdb->prefix . 'fuel_genius_vehicles';
    
    // Build date filter
    $date_filter = '';
    if ($period !== 'all') {
        $days = intval($period);
        $date_filter = $wpdb->prepare(" AND log_date >= DATE_SUB(CURDATE(), INTERVAL %d DAY)", $days);
    }
    
    // Build vehicle filter
    $vehicle_filter = '';
    if ($vehicle_id > 0) {
        $vehicle_filter = $wpdb->prepare(" AND vehicle_id = %d", $vehicle_id);
    }
    
    // Get all logs for the period (excluding deleted)
    $logs = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $logs_table 
        WHERE user_id = %d AND deleted_at IS NULL {$vehicle_filter} {$date_filter}
        ORDER BY log_date ASC, odometer_reading ASC",
        $user_id
    ), ARRAY_A);
    
    // Get total logs count for this vehicle (all time, excluding deleted)
    $total_logs = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $logs_table WHERE user_id = %d AND deleted_at IS NULL {$vehicle_filter}",
        $user_id
    ));  
    
    // Get lifetime logs for total distance and fuel (excluding deleted)
    $lifetime_logs = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $logs_table 
        WHERE user_id = %d AND deleted_at IS NULL {$vehicle_filter}
        ORDER BY log_date ASC, odometer_reading ASC",
        $user_id
    ), ARRAY_A);
    
    // Initialize analytics
    $analytics = array(
        'total_logs' => (int)$total_logs,  
        'avg_efficiency' => 0,
        'best_efficiency' => 0,
        'worst_efficiency' => 0,
        'best_efficiency_date' => '',
        'worst_efficiency_date' => '',
        'total_spending' => 0,
        'total_distance' => 0,
        'total_fuel' => 0,
        'avg_cost_per_fillup' => 0,
        'days_since_last_fillup' => 0,
        'estimated_range' => 0,
        'efficiency_trend' => 'neutral'
    );
    
    if (empty($logs)) {
        return $analytics;
    }
    
    // Calculate efficiencies
    $efficiencies = array();
    foreach ($logs as $log) {
        $efficiency = fuel_genius_calculate_efficiency($log['vehicle_id'], $log['id']);
        if ($efficiency !== null && $efficiency !== 'partial' && is_numeric($efficiency)) {
            $efficiencies[] = array(
                'value' => $efficiency,
                'date' => $log['log_date']
            );
        }
    }
    
    // Calculate metrics
    if (!empty($efficiencies)) {
        $efficiency_values = array_column($efficiencies, 'value');
        $analytics['avg_efficiency'] = round(array_sum($efficiency_values) / count($efficiency_values), 2);
        
        $max_index = array_search(max($efficiency_values), $efficiency_values);
        $min_index = array_search(min($efficiency_values), $efficiency_values);
        
        $analytics['best_efficiency'] = max($efficiency_values);
        $analytics['best_efficiency_date'] = $efficiencies[$max_index]['date'];
        $analytics['worst_efficiency'] = min($efficiency_values);
        $analytics['worst_efficiency_date'] = $efficiencies[$min_index]['date'];
        
        // Calculate trend (compare last 3 vs previous 3)
        if (count($efficiency_values) >= 6) {
            $recent_avg = array_sum(array_slice($efficiency_values, -3)) / 3;
            $previous_avg = array_sum(array_slice($efficiency_values, -6, 3)) / 3;
            $analytics['efficiency_trend'] = $recent_avg > $previous_avg ? 'up' : ($recent_avg < $previous_avg ? 'down' : 'neutral');
        }
    }
    
    // Total spending for period
    $analytics['total_spending'] = array_sum(array_column($logs, 'total_cost'));
    
    // Lifetime total distance and fuel
    if (!empty($lifetime_logs)) {
        $odometer_values = array_column($lifetime_logs, 'odometer_reading');
        $analytics['total_distance'] = max($odometer_values) - min($odometer_values);
        $analytics['total_fuel'] = array_sum(array_column($lifetime_logs, 'fuel_quantity'));
    }
    
    // Average cost per fill-up
    if (count($logs) > 0) {
        $analytics['avg_cost_per_fillup'] = round($analytics['total_spending'] / count($logs), 2);
    }
    
    // Days since last fill-up
    if (!empty($logs)) {
        $last_log_date = max(array_column($logs, 'log_date'));
        $date1 = new DateTime($last_log_date);
        $date2 = new DateTime();
        $analytics['days_since_last_fillup'] = $date1->diff($date2)->days;
    }
    
    // Estimated range on full tank
    if ($vehicle_id > 0 && $analytics['avg_efficiency'] > 0) {
        $vehicle = $wpdb->get_row($wpdb->prepare(
            "SELECT tank_capacity FROM $vehicles_table WHERE id = %d AND deleted_at IS NULL",
            $vehicle_id
        ));
        
        if ($vehicle && $vehicle->tank_capacity > 0) {
            $analytics['estimated_range'] = round($analytics['avg_efficiency'] * $vehicle->tank_capacity, 2);
        }
    }
    
    return $analytics;
}

/**
 * Get chart data for visualizations
 * 
 * @param int $user_id User ID
 * @param int $vehicle_id Vehicle ID
 * @param string $period Time period
 * @return array Chart data for all charts
 */
function fuel_genius_get_chart_data($user_id, $vehicle_id = 0, $period = '30') {
    global $wpdb;
    $logs_table = $wpdb->prefix . 'fuel_genius_fuel_logs';
    $vehicles_table = $wpdb->prefix . 'fuel_genius_vehicles';
    
    // Build date filter
    $date_filter = '';
    if ($period !== 'all') {
        $days = intval($period);
        $date_filter = $wpdb->prepare(" AND log_date >= DATE_SUB(CURDATE(), INTERVAL %d DAY)", $days);
    }
    
    // Build vehicle filter
    $vehicle_filter = '';
    if ($vehicle_id > 0) {
        $vehicle_filter = $wpdb->prepare(" AND vehicle_id = %d", $vehicle_id);
    }
    
    // Get logs for the period (excluding deleted)
    $logs = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $logs_table 
        WHERE user_id = %d AND deleted_at IS NULL {$vehicle_filter} {$date_filter}
        ORDER BY log_date ASC, odometer_reading ASC",
        $user_id
    ), ARRAY_A);
    
    $chart_data = array(
        'efficiency_over_time' => array('labels' => array(), 'data' => array()),
        'price_trend' => array('labels' => array(), 'data' => array()),
        'monthly_spending' => array('labels' => array(), 'data' => array()),
        'cost_breakdown' => array('labels' => array(), 'data' => array())
    );
    
    if (empty($logs)) {
        return $chart_data;
    }
    
    // Efficiency over time
    foreach ($logs as $log) {
        $efficiency = fuel_genius_calculate_efficiency($log['vehicle_id'], $log['id']);
        if ($efficiency !== null) {
            $chart_data['efficiency_over_time']['labels'][] = $log['log_date'];
            $chart_data['efficiency_over_time']['data'][] = $efficiency;
        }
    }
    
    // Price trend
    foreach ($logs as $log) {
        $chart_data['price_trend']['labels'][] = $log['log_date'];
        $chart_data['price_trend']['data'][] = floatval($log['price_per_unit']);
    }
    
    // Monthly spending
    $monthly_data = array();
    foreach ($logs as $log) {
        $month = date('Y-m', strtotime($log['log_date']));
        if (!isset($monthly_data[$month])) {
            $monthly_data[$month] = 0;
        }
        $monthly_data[$month] += floatval($log['total_cost']);
    }
    
    foreach ($monthly_data as $month => $total) {
        $chart_data['monthly_spending']['labels'][] = date('M Y', strtotime($month . '-01'));
        $chart_data['monthly_spending']['data'][] = $total;
    }
    
    // Cost breakdown by vehicle (if multiple vehicles)
    if ($vehicle_id == 0) {
        $breakdown = $wpdb->get_results($wpdb->prepare(
            "SELECT v.make, v.model, SUM(l.total_cost) as total_cost
            FROM $logs_table l
            LEFT JOIN $vehicles_table v ON l.vehicle_id = v.id
            WHERE l.user_id = %d AND l.deleted_at IS NULL AND v.deleted_at IS NULL {$date_filter}
            GROUP BY l.vehicle_id",
            $user_id
        ), ARRAY_A);
        
        foreach ($breakdown as $item) {
            $chart_data['cost_breakdown']['labels'][] = $item['make'] . ' ' . $item['model'];
            $chart_data['cost_breakdown']['data'][] = floatval($item['total_cost']);
        }
    }
    
    return $chart_data;
}

/**
 * Generate intelligent insights and alerts
 * 
 * @param int $user_id User ID
 * @param int $vehicle_id Vehicle ID
 * @return array Array of insights
 */
function fuel_genius_generate_insights($user_id, $vehicle_id = 0) {
    global $wpdb;
    $logs_table = $wpdb->prefix . 'fuel_genius_fuel_logs';
    
    $insights = array(
        'efficiency_alerts' => array(),
        'cost_insights' => array(),
        'milestones' => array(),
        'anomalies' => array()
    );
    
    $vehicle_filter = '';
    if ($vehicle_id > 0) {
        $vehicle_filter = $wpdb->prepare(" AND vehicle_id = %d", $vehicle_id);
    }
    
    // Get all logs (excluding deleted)
    $all_logs = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $logs_table 
        WHERE user_id = %d AND deleted_at IS NULL {$vehicle_filter}
        ORDER BY log_date DESC, odometer_reading DESC",
        $user_id
    ), ARRAY_A);
    
    if (empty($all_logs)) {
        return $insights;
    }
    
    // Calculate efficiencies for recent logs
    $efficiencies = array();
    foreach ($all_logs as $log) {
        $eff = fuel_genius_calculate_efficiency($log['vehicle_id'], $log['id']);
        if ($eff !== null) {
            $efficiencies[] = array(
                'value' => $eff,
                'date' => $log['log_date'],
                'log' => $log
            );
        }
    }
    
    // Efficiency Alerts
    if (count($efficiencies) >= 6) {
        $recent_3 = array_slice($efficiencies, 0, 3);
        $previous_3 = array_slice($efficiencies, 3, 3);
        
        $recent_avg = array_sum(array_column($recent_3, 'value')) / 3;
        $previous_avg = array_sum(array_column($previous_3, 'value')) / 3;
        
        $change = (($recent_avg - $previous_avg) / $previous_avg) * 100;
        
        if ($change < -10) {
            $insights['efficiency_alerts'][] = array(
                'type' => 'warning',
                'icon' => '⚠️',
                'message' => sprintf(__('Your efficiency dropped %.1f%% recently - check tire pressure and driving habits!', 'fuel-genius'), abs($change))
            );
        } elseif ($change > 10) {
            $insights['efficiency_alerts'][] = array(
                'type' => 'success',
                'icon' => '✅',
                'message' => sprintf(__('Great job! Your efficiency improved by %.1f%% recently!', 'fuel-genius'), $change)
            );
        }
        
        // Best efficiency
        if (!empty($efficiencies)) {
            $best = max(array_column($efficiencies, 'value'));
            $best_index = array_search($best, array_column($efficiencies, 'value'));
            $insights['efficiency_alerts'][] = array(
                'type' => 'info',
                'icon' => '✅',
                'message' => sprintf(__('Best efficiency this period: %.2f km/L on %s', 'fuel-genius'), $best, $efficiencies[$best_index]['date'])
            );
        }
        
        // Check for declining trend (3 consecutive decreases)
        if (count($efficiencies) >= 4) {
            $declining = true;
            for ($i = 0; $i < 3; $i++) {
                if ($efficiencies[$i]['value'] >= $efficiencies[$i + 1]['value']) {
                    $declining = false;
                    break;
                }
            }
            
            if ($declining) {
                $insights['efficiency_alerts'][] = array(
                    'type' => 'warning',
                    'icon' => '📉',
                    'message' => __('Efficiency has been declining for 3 consecutive fill-ups. Consider a vehicle check-up.', 'fuel-genius')
                );
            }
        }
    }
    
    // Cost Insights
    if (count($all_logs) >= 5) {
        $last_5_prices = array_slice(array_column($all_logs, 'price_per_unit'), 0, 5);
        $avg_price = array_sum($last_5_prices) / count($last_5_prices);
        $last_price = $last_5_prices[0];
        
        $price_change = $last_price - $avg_price;
        
        if ($price_change > 5) {
            $insights['cost_insights'][] = array(
                'type' => 'warning',
                'icon' => '📈',
                'message' => sprintf(__('Fuel prices increased by ₹%.2f/L compared to your average.', 'fuel-genius'), $price_change)
            );
        } elseif ($price_change < -5) {
            $insights['cost_insights'][] = array(
                'type' => 'success',
                'icon' => '📉',
                'message' => sprintf(__('Good news! Fuel prices decreased by ₹%.2f/L compared to your average.', 'fuel-genius'), abs($price_change))
            );
        }
        
        // Savings potential
        if (!empty($efficiencies)) {
            $current_avg_eff = array_sum(array_column($efficiencies, 'value')) / count($efficiencies);
            $improved_eff = $current_avg_eff + 2;
            
            // Estimate monthly savings
            $monthly_logs = array_filter($all_logs, function($log) {
                return strtotime($log['log_date']) >= strtotime('-30 days');
            });
            
            if (!empty($monthly_logs)) {
                $monthly_fuel = array_sum(array_column($monthly_logs, 'fuel_quantity'));
                $savings = ($monthly_fuel * 2 / $current_avg_eff) * $avg_price;
                
                if ($savings > 100) {
                    $insights['cost_insights'][] = array(
                        'type' => 'info',
                        'icon' => '💰',
                        'message' => sprintf(__('You could save ₹%.0f/month by improving efficiency by 2 km/L.', 'fuel-genius'), $savings)
                    );
                }
            }
        }
        
        // Monthly spending comparison
        $this_month_logs = array_filter($all_logs, function($log) {
            return date('Y-m', strtotime($log['log_date'])) == date('Y-m');
        });
        
        $last_month_logs = array_filter($all_logs, function($log) {
            return date('Y-m', strtotime($log['log_date'])) == date('Y-m', strtotime('-1 month'));
        });
        
        if (!empty($this_month_logs) && !empty($last_month_logs)) {
            $this_month_cost = array_sum(array_column($this_month_logs, 'total_cost'));
            $last_month_cost = array_sum(array_column($last_month_logs, 'total_cost'));
            
            $cost_diff = $this_month_cost - $last_month_cost;
            
            if (abs($cost_diff) > 500) {
                if ($cost_diff < 0) {
                    $insights['cost_insights'][] = array(
                        'type' => 'success',
                        'icon' => '💸',
                        'message' => sprintf(__('You spent ₹%.0f less this month compared to last month!', 'fuel-genius'), abs($cost_diff))
                    );
                } else {
                    $insights['cost_insights'][] = array(
                        'type' => 'info',
                        'icon' => '💵',
                        'message' => sprintf(__('You spent ₹%.0f more this month compared to last month.', 'fuel-genius'), $cost_diff)
                    );
                }
            }
        }
    }
    
    // Milestones
    $odometer_values = array_column($all_logs, 'odometer_reading');
    if (!empty($odometer_values)) {
        $total_distance = max($odometer_values) - min($odometer_values);
        
        $milestones_to_check = array(5000, 10000, 25000, 50000, 100000);
        foreach ($milestones_to_check as $milestone) {
            if ($total_distance >= $milestone && $total_distance < ($milestone + 1000)) {
                $insights['milestones'][] = array(
                    'type' => 'success',
                    'icon' => '🎉',
                    'message' => sprintf(__('Congratulations! You\'ve driven %s km!', 'fuel-genius'), number_format($milestone))
                );
                break;
            }
        }
    }
    
    $log_count = count($all_logs);
    $log_milestones = array(10, 25, 50, 100, 250);
    foreach ($log_milestones as $milestone) {
        if ($log_count == $milestone) {
            $insights['milestones'][] = array(
                'type' => 'success',
                'icon' => '🏆',
                'message' => sprintf(__('You\'ve logged %d fill-ups! Keep tracking!', 'fuel-genius'), $milestone)
            );
            break;
        }
    }
    
    // Anomaly Detection
    if (count($all_logs) >= 5) {
        $recent_logs = array_slice($all_logs, 0, 5);
        
        // Check for unusual fuel consumption
        $fuel_quantities = array_column($recent_logs, 'fuel_quantity');
        $avg_fuel = array_sum($fuel_quantities) / count($fuel_quantities);
        
        foreach ($recent_logs as $index => $log) {
            if ($index < 2) { // Only check 2 most recent
                $deviation = (abs($log['fuel_quantity'] - $avg_fuel) / $avg_fuel) * 100;
                
                if ($deviation > 30) {
                    $insights['anomalies'][] = array(
                        'type' => 'warning',
                        'icon' => '⚠️',
                        'message' => sprintf(__('Unusual fuel quantity detected on %s: %.2f L (%.0f%% different from average).', 'fuel-genius'), $log['log_date'], $log['fuel_quantity'], $deviation)
                    );
                }
            }
        }
        
        // Check for expensive fill-up
        $costs = array_column($recent_logs, 'total_cost');
        $avg_cost = array_sum($costs) / count($costs);
        $last_cost = $costs[0];
        
        if ($last_cost > $avg_cost * 1.3) {
            $increase = (($last_cost - $avg_cost) / $avg_cost) * 100;
            $insights['anomalies'][] = array(
                'type' => 'warning',
                'icon' => '💵',
                'message' => sprintf(__('Your last fill-up was %.0f%% more expensive than average (₹%.2f).', 'fuel-genius'), $increase, $last_cost)
            );
        }
    }
    
    return $insights;
}

/**
 * Generate comprehensive report
 * 
 * @param int $user_id User ID
 * @param string $vehicle_id Vehicle ID or 'all'
 * @param string $start_date Start date
 * @param string $end_date End date
 * @return array Report data
 */
function fuel_genius_generate_report($user_id, $vehicle_id, $start_date, $end_date) {
    global $wpdb;
    $logs_table = $wpdb->prefix . 'fuel_genius_fuel_logs';
    $vehicles_table = $wpdb->prefix . 'fuel_genius_vehicles';
    
    $vehicle_filter = '';
    if ($vehicle_id !== 'all') {
        $vehicle_filter = $wpdb->prepare(" AND vehicle_id = %d", intval($vehicle_id));
    }
    
    // Get logs for the date range (excluding deleted)
    $logs = $wpdb->get_results($wpdb->prepare(
        "SELECT l.*, v.make, v.model, v.year 
        FROM $logs_table l 
        LEFT JOIN $vehicles_table v ON l.vehicle_id = v.id 
        WHERE l.user_id = %d AND l.deleted_at IS NULL {$vehicle_filter}
        AND l.log_date >= %s 
        AND l.log_date <= %s 
        ORDER BY l.log_date ASC, l.odometer_reading ASC",
        $user_id, $start_date, $end_date
    ), ARRAY_A);
    
    $report = array(
        'vehicle_name' => $vehicle_id === 'all' ? 'All Vehicles' : '',
        'start_date' => $start_date,
        'end_date' => $end_date,
        'total_distance' => 0,
        'total_fuel' => 0,
        'total_cost' => 0,
        'avg_efficiency' => 0,
        'cost_per_km' => 0,
        'fillup_count' => count($logs),
        'avg_fillup_amount' => 0,
        'avg_days_between_fillups' => 0,
        'logs' => $logs
    );
    
    if (empty($logs)) {
        return $report;
    }
    
    // Get vehicle name if specific vehicle
    if ($vehicle_id !== 'all' && !empty($logs)) {
        $report['vehicle_name'] = $logs[0]['year'] . ' ' . $logs[0]['make'] . ' ' . $logs[0]['model'];
    }
    
    // Calculate metrics
    $odometer_values = array_column($logs, 'odometer_reading');
    $report['total_distance'] = max($odometer_values) - min($odometer_values);
    $report['total_fuel'] = array_sum(array_column($logs, 'fuel_quantity'));
    $report['total_cost'] = array_sum(array_column($logs, 'total_cost'));
    
    // Calculate efficiencies
    $efficiencies = array();
    foreach ($logs as $log) {
        $eff = fuel_genius_calculate_efficiency($log['vehicle_id'], $log['id']);
        if ($eff !== null) {
            $efficiencies[] = $eff;
        }
    }
    
    if (!empty($efficiencies)) {
        $report['avg_efficiency'] = round(array_sum($efficiencies) / count($efficiencies), 2);
    }
    
    // Cost per kilometer
    if ($report['total_distance'] > 0) {
        $report['cost_per_km'] = round($report['total_cost'] / $report['total_distance'], 2);
    }
    
    // Average fill-up amount
    if ($report['fillup_count'] > 0) {
        $report['avg_fillup_amount'] = round($report['total_fuel'] / $report['fillup_count'], 2);
    }
    
    // Average days between fill-ups
    if ($report['fillup_count'] > 1) {
        $dates = array_column($logs, 'log_date');
        $first_date = new DateTime(min($dates));
        $last_date = new DateTime(max($dates));
        $total_days = $first_date->diff($last_date)->days;
        $report['avg_days_between_fillups'] = round($total_days / ($report['fillup_count'] - 1), 1);
    }
    
    return $report;
}

/**
 * Get comparative analysis data
 * 
 * @param int $user_id User ID
 * @param int $vehicle_id Vehicle ID
 * @return array Comparative data
 */
function fuel_genius_get_comparative_data($user_id, $vehicle_id = 0) {
    global $wpdb;
    $logs_table = $wpdb->prefix . 'fuel_genius_fuel_logs';
    $vehicles_table = $wpdb->prefix . 'fuel_genius_vehicles';
    
    $comparative = array(
        'month_comparison' => array(),
        'vehicle_comparison' => array()
    );
    
    $vehicle_filter = '';
    if ($vehicle_id > 0) {
        $vehicle_filter = $wpdb->prepare(" AND vehicle_id = %d", $vehicle_id);
    }
    
    // This month vs last month
    $this_month_start = date('Y-m-01');
    $this_month_end = date('Y-m-t');
    $last_month_start = date('Y-m-01', strtotime('-1 month'));
    $last_month_end = date('Y-m-t', strtotime('-1 month'));
    
    $this_month = fuel_genius_generate_report($user_id, $vehicle_id > 0 ? $vehicle_id : 'all', $this_month_start, $this_month_end);
    $last_month = fuel_genius_generate_report($user_id, $vehicle_id > 0 ? $vehicle_id : 'all', $last_month_start, $last_month_end);
    
    $comparative['month_comparison'] = array(
        'this_month' => $this_month,
        'last_month' => $last_month,
        'distance_change' => $this_month['total_distance'] > 0 && $last_month['total_distance'] > 0 
            ? round((($this_month['total_distance'] - $last_month['total_distance']) / $last_month['total_distance']) * 100, 1) 
            : 0,
        'fuel_change' => $this_month['total_fuel'] > 0 && $last_month['total_fuel'] > 0 
            ? round((($this_month['total_fuel'] - $last_month['total_fuel']) / $last_month['total_fuel']) * 100, 1) 
            : 0,
        'cost_change' => $this_month['total_cost'] > 0 && $last_month['total_cost'] > 0 
            ? round((($this_month['total_cost'] - $last_month['total_cost']) / $last_month['total_cost']) * 100, 1) 
            : 0,
        'efficiency_change' => $this_month['avg_efficiency'] > 0 && $last_month['avg_efficiency'] > 0 
            ? round((($this_month['avg_efficiency'] - $last_month['avg_efficiency']) / $last_month['avg_efficiency']) * 100, 1) 
            : 0
    );
    
    // Vehicle comparison (if user has multiple vehicles)
    if ($vehicle_id == 0) {
        $vehicles = $wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT v.* FROM $vehicles_table v 
            INNER JOIN $logs_table l ON v.id = l.vehicle_id 
            WHERE v.user_id = %d AND v.deleted_at IS NULL AND l.deleted_at IS NULL",
            $user_id
        ), ARRAY_A);
        
        if (count($vehicles) > 1) {
            $vehicle_stats = array();
            
            foreach ($vehicles as $vehicle) {
                $analytics = fuel_genius_get_analytics_data($user_id, $vehicle['id'], 'all');
                
                $vehicle_stats[] = array(
                    'vehicle_name' => $vehicle['year'] . ' ' . $vehicle['make'] . ' ' . $vehicle['model'],
                    'avg_efficiency' => $analytics['avg_efficiency'],
                    'total_cost' => $analytics['total_spending'],
                    'total_distance' => $analytics['total_distance']
                );
            }
            
            // Find most economical
            $efficiencies = array_column($vehicle_stats, 'avg_efficiency');
            $max_eff_index = array_search(max($efficiencies), $efficiencies);
            if ($max_eff_index !== false) {
                $vehicle_stats[$max_eff_index]['most_economical'] = true;
            }
            
            $comparative['vehicle_comparison'] = $vehicle_stats;
        }
    }
    
    return $comparative;
}

/**
 * Get predictive analytics
 * 
 * @param int $user_id User ID
 * @param int $vehicle_id Vehicle ID
 * @return array Predictive data
 */
function fuel_genius_get_predictive_analytics($user_id, $vehicle_id = 0) {
    global $wpdb;
    $logs_table = $wpdb->prefix . 'fuel_genius_fuel_logs';
    
    $predictive = array(
        'projected_monthly_cost' => 0,
        'next_fillup_days' => 0,
        'monthly_forecast' => array()
    );
    
    $vehicle_filter = '';
    if ($vehicle_id > 0) {
        $vehicle_filter = $wpdb->prepare(" AND vehicle_id = %d", $vehicle_id);
    }
    
    // Get current month logs (excluding deleted)
    $current_month_logs = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $logs_table 
        WHERE user_id = %d AND deleted_at IS NULL {$vehicle_filter}
        AND MONTH(log_date) = MONTH(CURDATE()) 
        AND YEAR(log_date) = YEAR(CURDATE())
        ORDER BY log_date ASC",
        $user_id
    ), ARRAY_A);
    
    if (!empty($current_month_logs)) {
        $current_spending = array_sum(array_column($current_month_logs, 'total_cost'));
        $days_passed = date('j');
        $days_in_month = date('t');
        
        if ($days_passed > 0) {
            $predictive['projected_monthly_cost'] = round(($current_spending / $days_passed) * $days_in_month, 2);
        }
    }
    
    // Predict next fill-up (excluding deleted)
    $recent_logs = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $logs_table 
        WHERE user_id = %d AND deleted_at IS NULL {$vehicle_filter}
        ORDER BY log_date DESC 
        LIMIT 5",
        $user_id
    ), ARRAY_A);
    
    if (count($recent_logs) >= 2) {
        $days_between = array();
        for ($i = 0; $i < count($recent_logs) - 1; $i++) {
            $date1 = new DateTime($recent_logs[$i]['log_date']);
            $date2 = new DateTime($recent_logs[$i + 1]['log_date']);
            $days_between[] = $date1->diff($date2)->days;
        }
        
        if (!empty($days_between)) {
            $avg_days = array_sum($days_between) / count($days_between);
            $predictive['next_fillup_days'] = round($avg_days);
        }
    }
    
    return $predictive;
}