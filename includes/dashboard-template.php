<?php
/**
 * Dashboard Template
 * Frontend HTML structure for the Fuel Genius dashboard
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

$current_user_id = get_current_user_id();
?>

<div id="fuel-genius-dashboard" class="fuel-genius-container">
    
    <!-- Success/Error Messages -->
    <div id="fg-message-container"></div>
    
    <!-- Undo Notification (Hidden by default) -->
    <div id="fg-undo-notification" class="fg-undo-notification" style="display:none;">
        <span class="fg-undo-message"></span>
        <button type="button" class="fg-btn fg-btn-warning fg-undo-btn">
            <span class="fg-emoji">↩️</span> Undo
        </button>
    </div>
    
    <!-- Section 1: Add Fuel Log -->
    <section class="fg-section fg-add-log-section">
        <div class="fg-section-header">
            <h2><span class="fg-emoji">⛽</span> <?php esc_html_e('Add Fuel Log', 'fuel-genius'); ?></h2>
        </div>
        
        <form id="fg-fuel-log-form" class="fg-form-container">
            <div class="fg-form-row">
                <div class="fg-form-group">
                    <label for="log-vehicle"><?php esc_html_e('Vehicle', 'fuel-genius'); ?> *</label>
                    <select id="log-vehicle" name="vehicle_id" required>
                        <option value=""><?php esc_html_e('Select vehicle...', 'fuel-genius'); ?></option>
                    </select>
                </div>
                <div class="fg-form-group">
                    <label for="log-date"><?php esc_html_e('Date', 'fuel-genius'); ?> *</label>
                    <input type="date" id="log-date" name="log_date" value="<?php echo esc_attr(date('Y-m-d')); ?>" required>
                </div>
            </div>
            
            <div class="fg-form-row">
                <div class="fg-form-group">
                    <label for="log-odometer"><?php esc_html_e('Odometer Reading (km)', 'fuel-genius'); ?> *</label>
                    <input type="number" id="log-odometer" name="odometer_reading" min="0" required>
                </div>
                <div class="fg-form-group">
                    <label for="log-quantity"><?php esc_html_e('Fuel Quantity (L)', 'fuel-genius'); ?> *</label>
                    <input type="number" id="log-quantity" name="fuel_quantity" step="0.01" min="0.01" required>
                </div>
            </div>
            
            <div class="fg-form-row">
                <div class="fg-form-group">
                    <label for="log-price"><?php esc_html_e('Price Per Litre (₹)', 'fuel-genius'); ?> *</label>
                    <input type="number" id="log-price" name="price_per_unit" step="0.01" min="0.01" required>
                </div>
                <div class="fg-form-group">
                    <label for="log-total-cost"><?php esc_html_e('Total Cost (₹)', 'fuel-genius'); ?></label>
                    <input type="number" id="log-total-cost" name="total_cost" step="0.01" readonly>
                </div>
            </div>
            
            <div class="fg-form-group">
                <label>
                    <input type="checkbox" id="log-full-tank" name="filled_to_full_tank" value="1" checked>
                    <?php esc_html_e('Filled to full tank?', 'fuel-genius'); ?>
                </label>
            </div>
            
            <div class="fg-form-actions">
                <button type="submit" class="fg-btn fg-btn-primary">
                    <span class="fg-emoji">💾</span> <?php esc_html_e('Add Fuel Log', 'fuel-genius'); ?>
                </button>
            </div>
        </form>
    </section>
    
    <!-- Section 2: Analytics Dashboard -->
    <section class="fg-section fg-analytics-section">
        <div class="fg-section-header">
            <h2><span class="fg-emoji">📊</span> <?php esc_html_e('Analytics Dashboard', 'fuel-genius'); ?></h2>
            
            <!-- Time Period Filter -->
            <div class="fg-time-filter">
                <label for="fg-time-period"><?php esc_html_e('Time Period:', 'fuel-genius'); ?></label>
                <select id="fg-time-period">
                    <option value="7"><?php esc_html_e('Last 7 days', 'fuel-genius'); ?></option>
                    <option value="30"><?php esc_html_e('Last 30 days', 'fuel-genius'); ?></option>
                    <option value="90"><?php esc_html_e('Last 3 months', 'fuel-genius'); ?></option>
                    <option value="180"><?php esc_html_e('Last 6 months', 'fuel-genius'); ?></option>
                    <option value="365"><?php esc_html_e('Last Year', 'fuel-genius'); ?></option>
                    <option value="all" selected><?php esc_html_e('All Time', 'fuel-genius'); ?></option>
                </select>
            </div>
        </div>
        
        <!-- Key Metrics Cards -->
        <div id="fg-metrics-grid" class="fg-metrics-grid">
            <div class="fg-loading"><?php esc_html_e('Loading analytics...', 'fuel-genius'); ?></div>
        </div>
    </section>
    
    <!-- Section 3: Recent Fuel Logs -->
    <section class="fg-section fg-recent-logs-section">
        <div class="fg-section-header">
            <h2><span class="fg-emoji">📋</span> <?php esc_html_e('Recent Fuel Logs (Last 5)', 'fuel-genius'); ?></h2>
        </div>
        
        <div id="fg-recent-logs-container">
            <div class="fg-loading"><?php esc_html_e('Loading logs...', 'fuel-genius'); ?></div>
        </div>
        
        <!-- View All Logs Button -->
        <div class="fg-view-all-logs">
            <button type="button" id="fg-view-all-logs-btn" class="fg-btn fg-btn-primary">
                <span class="fg-emoji">📋</span> <?php esc_html_e('View All Logs', 'fuel-genius'); ?>
            </button>
        </div>
    </section>
    
    <!-- Section 4: Reports -->
    <section class="fg-section fg-reports-section">
        <div class="fg-section-header">
            <h2><span class="fg-emoji">📄</span> <?php esc_html_e('Mileage & Costs Analysis', 'fuel-genius'); ?></h2>
        </div>
        
        <form id="fg-report-form" class="fg-form-container">
            <div class="fg-form-row">
                <div class="fg-form-group">
                    <label for="report-vehicle"><?php esc_html_e('Vehicle', 'fuel-genius'); ?></label>
                    <select id="report-vehicle" name="vehicle_id">
                        <option value="all"><?php esc_html_e('All Vehicles', 'fuel-genius'); ?></option>
                    </select>
                </div>
                <div class="fg-form-group">
                    <label for="report-start-date"><?php esc_html_e('Start Date', 'fuel-genius'); ?></label>
                    <input type="date" id="report-start-date" name="start_date" required>
                </div>
                <div class="fg-form-group">
                    <label for="report-end-date"><?php esc_html_e('End Date', 'fuel-genius'); ?></label>
                    <input type="date" id="report-end-date" name="end_date" value="<?php echo esc_attr(date('Y-m-d')); ?>" required>
                </div>
            </div>
            
            <div class="fg-form-actions">
                <button type="submit" class="fg-btn fg-btn-primary">
                    <span class="fg-emoji">📊</span> <?php esc_html_e('Generate Report', 'fuel-genius'); ?>
                </button>
            </div>
        </form>
        
        <div id="fg-report-results" class="fg-report-results" style="display:none;">
            <!-- Report content loaded via AJAX -->
        </div>
        
        <div id="fg-export-buttons" class="fg-export-buttons" style="display:none;">
            <button id="fg-export-pdf" class="fg-btn fg-btn-secondary">
                <span class="fg-emoji">📕</span> <?php esc_html_e('Download PDF', 'fuel-genius'); ?>
            </button>
            <button id="fg-export-csv" class="fg-btn fg-btn-secondary">
                <span class="fg-emoji">📑</span> <?php esc_html_e('Export to CSV', 'fuel-genius'); ?>
            </button>
            <button id="fg-export-excel" class="fg-btn fg-btn-secondary">
                <span class="fg-emoji">📗</span> <?php esc_html_e('Export to Excel', 'fuel-genius'); ?>
            </button>
        </div>
    </section>
    
    <!-- Section 5: My Vehicles -->
    <section class="fg-section fg-vehicles-section">
        <div class="fg-section-header">
            <h2><span class="fg-emoji">🚗</span> <?php esc_html_e('My Vehicles', 'fuel-genius'); ?></h2>
            <button id="fg-add-vehicle-btn" class="fg-btn fg-btn-primary">
                <span class="fg-emoji">➕</span> <?php esc_html_e('Add Vehicle', 'fuel-genius'); ?>
            </button>
        </div>
        
        <div id="fg-vehicles-grid" class="fg-vehicles-grid">
            <div class="fg-loading"><?php esc_html_e('Loading vehicles...', 'fuel-genius'); ?></div>
        </div>
        
        <!-- Add Vehicle Form (Hidden by default) -->
        <div id="fg-add-vehicle-form" class="fg-form-container" style="display:none;">
            <h3><?php esc_html_e('Add New Vehicle', 'fuel-genius'); ?></h3>
            <form id="fg-vehicle-form">
                <div class="fg-form-row">
                    <div class="fg-form-group">
                        <label for="vehicle-make"><?php esc_html_e('Make', 'fuel-genius'); ?> *</label>
                        <input type="text" id="vehicle-make" name="make" required>
                    </div>
                    <div class="fg-form-group">
                        <label for="vehicle-model"><?php esc_html_e('Model', 'fuel-genius'); ?> *</label>
                        <input type="text" id="vehicle-model" name="model" required>
                    </div>
                </div>
                
                <div class="fg-form-row">
                    <div class="fg-form-group">
                        <label for="vehicle-year"><?php esc_html_e('Year', 'fuel-genius'); ?> *</label>
                        <input type="number" id="vehicle-year" name="year" min="1900" max="2099" required>
                    </div>
                    <div class="fg-form-group">
                        <label for="vehicle-fuel-type"><?php esc_html_e('Fuel Type', 'fuel-genius'); ?> *</label>
                        <select id="vehicle-fuel-type" name="fuel_type" required>
                            <option value=""><?php esc_html_e('Select...', 'fuel-genius'); ?></option>
                            <option value="Petrol"><?php esc_html_e('Petrol', 'fuel-genius'); ?></option>
                            <option value="Diesel"><?php esc_html_e('Diesel', 'fuel-genius'); ?></option>
                        </select>
                    </div>
                </div>
                
                <div class="fg-form-group">
                    <label for="vehicle-tank-capacity"><?php esc_html_e('Tank Capacity (Litres)', 'fuel-genius'); ?></label>
                    <input type="number" id="vehicle-tank-capacity" name="tank_capacity" step="0.01" min="0">
                </div>
                
                <div class="fg-form-actions">
                    <button type="submit" class="fg-btn fg-btn-primary">
                        <?php esc_html_e('Save Vehicle', 'fuel-genius'); ?>
                    </button>
                    <button type="button" id="fg-cancel-vehicle-btn" class="fg-btn fg-btn-secondary">
                        <?php esc_html_e('Cancel', 'fuel-genius'); ?>
                    </button>
                </div>
            </form>
        </div>
    </section>
    
    <!-- Section 6: Trash -->
    <section class="fg-section fg-trash-section">
        <div class="fg-section-header">
            <h2><span class="fg-emoji">🗑️</span> <?php esc_html_e('Trash', 'fuel-genius'); ?></h2>
            <button id="fg-view-trash-btn" class="fg-btn fg-btn-secondary">
                <span class="fg-emoji">👁️</span> <?php esc_html_e('View Trash', 'fuel-genius'); ?>
            </button>
        </div>
        
        <div id="fg-trash-content" style="display:none;">
            <p style="color: #666; font-size: 14px; margin-bottom: 15px;">
                <?php esc_html_e('Items in trash will be automatically deleted after 30 days.', 'fuel-genius'); ?>
            </p>
            
            <!-- Trashed Vehicles -->
            <div class="fg-trash-category">
                <h3><?php esc_html_e('Deleted Vehicles', 'fuel-genius'); ?></h3>
                <div id="fg-trashed-vehicles-container">
                    <div class="fg-loading"><?php esc_html_e('Loading...', 'fuel-genius'); ?></div>
                </div>
            </div>
            
            <!-- Trashed Logs -->
            <div class="fg-trash-category">
                <h3><?php esc_html_e('Deleted Fuel Logs', 'fuel-genius'); ?></h3>
                <div id="fg-trashed-logs-container">
                    <div class="fg-loading"><?php esc_html_e('Loading...', 'fuel-genius'); ?></div>
                </div>
            </div>
        </div>
    </section>
    
</div>

<!-- Edit Log Modal -->
<div id="fg-edit-modal" class="fg-modal" style="display:none;">
    <div class="fg-modal-content">
        <div class="fg-modal-header">
            <h3><?php esc_html_e('Edit Fuel Log', 'fuel-genius'); ?></h3>
            <span class="fg-modal-close">&times;</span>
        </div>
        <form id="fg-edit-log-form">
            <input type="hidden" id="edit-log-id" name="log_id">
            
            <div class="fg-form-group">
                <label for="edit-log-date"><?php esc_html_e('Date', 'fuel-genius'); ?></label>
                <input type="date" id="edit-log-date" name="log_date" required>
            </div>
            
            <div class="fg-form-group">
                <label for="edit-log-odometer"><?php esc_html_e('Odometer Reading (km)', 'fuel-genius'); ?></label>
                <input type="number" id="edit-log-odometer" name="odometer_reading" min="0" required>
            </div>
            
            <div class="fg-form-group">
                <label for="edit-log-quantity"><?php esc_html_e('Fuel Quantity (L)', 'fuel-genius'); ?></label>
                <input type="number" id="edit-log-quantity" name="fuel_quantity" step="0.01" min="0.01" required>
            </div>
            
            <div class="fg-form-group">
                <label for="edit-log-price"><?php esc_html_e('Price Per Litre (₹)', 'fuel-genius'); ?></label>
                <input type="number" id="edit-log-price" name="price_per_unit" step="0.01" min="0.01" required>
            </div>
            
            <div class="fg-form-group">
                <label for="edit-log-total"><?php esc_html_e('Total Cost (₹)', 'fuel-genius'); ?></label>
                <input type="number" id="edit-log-total" name="total_cost" step="0.01" readonly>
            </div>
            
            <div class="fg-form-group">
                <label>
                    <input type="checkbox" id="edit-log-full-tank" name="filled_to_full_tank" value="1" checked>
                    <?php esc_html_e('Filled to full tank?', 'fuel-genius'); ?>
                </label>
            </div>
            
            <div class="fg-form-actions">
                <button type="submit" class="fg-btn fg-btn-primary">
                    <?php esc_html_e('Update Log', 'fuel-genius'); ?>
                </button>
                <button type="button" class="fg-btn fg-btn-secondary fg-modal-close">
                    <?php esc_html_e('Cancel', 'fuel-genius'); ?>
                </button>
            </div>
        </form>
    </div>
</div>