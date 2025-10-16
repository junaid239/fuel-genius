/**
 * Fuel Genius - Main JavaScript (With Trash & Undo)
 * Handles all frontend interactions and AJAX calls
 */

(function($) {
    'use strict';
    
    // State management
    let currentVehicleId = 0;
    let currentPeriod = 'all';
    let allVehicles = [];
    let reportData = null;
    let lastOdometerReading = 0;
    let undoTimeout = null;
    let lastDeletedItem = null;
    
    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        initializeApp();
        bindEvents();
    });
    
    /**
     * Initialize the application
     */
    function initializeApp() {
        loadVehicles();
    }
    
    /**
     * Bind all event listeners
     */
    function bindEvents() {
        // Vehicle Management
        $('#fg-add-vehicle-btn').on('click', showAddVehicleForm);
        $('#fg-cancel-vehicle-btn').on('click', hideAddVehicleForm);
        $('#fg-vehicle-form').on('submit', handleAddVehicle);
        $(document).on('click', '.fg-select-vehicle', handleSelectVehicle);
        $(document).on('click', '.fg-delete-vehicle', handleDeleteVehicle);
        
        // Fuel Log Management
        $('#fg-fuel-log-form').on('submit', handleAddFuelLog);
        $('#log-vehicle').on('change', handleVehicleChange);
        $('#log-quantity, #log-price').on('input', calculateTotalCost);
        $(document).on('click', '.fg-edit-log', handleEditLog);
        $(document).on('click', '.fg-delete-log', handleDeleteLog);
        $('#fg-edit-log-form').on('submit', handleUpdateLog);
        $('#edit-log-quantity, #edit-log-price').on('input', calculateEditTotalCost);
        
        // Modal
        $('.fg-modal-close').on('click', closeModal);
        $(document).on('click', '.fg-modal', function(e) {
            if ($(e.target).hasClass('fg-modal')) {
                closeModal();
            }
        });
        
        // Analytics
        $('#fg-time-period').on('change', handlePeriodChange);
        
        // Reports
        $('#fg-report-form').on('submit', handleGenerateReport);
        $('#fg-export-pdf').on('click', exportToPDF);
        $('#fg-export-csv').on('click', exportToCSV);
        $('#fg-export-excel').on('click', exportToExcel);
        
        // View All Logs
        $('#fg-view-all-logs-btn').on('click', handleViewAllLogs);
        
        // Trash functionality
        $('#fg-view-trash-btn').on('click', toggleTrash);
        $(document).on('click', '.fg-restore-vehicle', handleRestoreVehicle);
        $(document).on('click', '.fg-restore-log', handleRestoreLog);
        $(document).on('click', '.fg-permanent-delete-vehicle', handlePermanentDeleteVehicle);
        $(document).on('click', '.fg-permanent-delete-log', handlePermanentDeleteLog);
        
        // Undo functionality
        $(document).on('click', '.fg-undo-btn', handleUndo);
    }
    
    /**
     * Show message to user
     */
    function showMessage(message, type) {
        type = type || 'success';
        const icon = type === 'success' ? '✅' : '❌';
        const messageEl = $('<div>')
            .addClass('fg-message ' + type)
            .html('<span class="fg-emoji">' + icon + '</span> ' + message);
        
        $('#fg-message-container').append(messageEl);
        
        setTimeout(function() {
            messageEl.fadeOut(300, function() {
                $(this).remove();
            });
        }, 5000);
    }
    
    /**
     * Show undo notification
     */
    function showUndoNotification(message, itemType, itemId) {
        // Clear existing timeout
        if (undoTimeout) {
            clearTimeout(undoTimeout);
        }
        
        // Store deleted item info
        lastDeletedItem = {
            type: itemType,
            id: itemId
        };
        
        // Update message and show notification
        $('#fg-undo-notification .fg-undo-message').text(message);
        $('#fg-undo-notification').fadeIn(300);
        
        // Auto-hide after 10 seconds
        undoTimeout = setTimeout(function() {
            hideUndoNotification();
        }, 10000);
    }
    
    /**
     * Hide undo notification
     */
    function hideUndoNotification() {
        $('#fg-undo-notification').fadeOut(300);
        lastDeletedItem = null;
        if (undoTimeout) {
            clearTimeout(undoTimeout);
            undoTimeout = null;
        }
    }
    
    /**
     * Handle undo action
     */
    function handleUndo() {
        if (!lastDeletedItem) {
            return;
        }
        
        hideUndoNotification();
        
        ajaxRequest('fuel_genius_undo_delete', {
            item_type: lastDeletedItem.type,
            item_id: lastDeletedItem.id
        }, function(data) {
            showMessage(data.message, 'success');
            
            // Reload appropriate data
            if (lastDeletedItem.type === 'vehicle') {
                loadVehicles();
            } else if (lastDeletedItem.type === 'log') {
                if (currentVehicleId > 0) {
                    loadRecentLogs(currentVehicleId);
                    loadAnalytics(currentVehicleId, currentPeriod);
                }
            }
            
            lastDeletedItem = null;
        });
    }
    
    /**
     * Toggle trash section visibility
     */
    function toggleTrash() {
        const trashContent = $('#fg-trash-content');
        
        if (trashContent.is(':visible')) {
            trashContent.slideUp(300);
            $('#fg-view-trash-btn').html('<span class="fg-emoji">👁️</span> View Trash');
        } else {
            trashContent.slideDown(300);
            $('#fg-view-trash-btn').html('<span class="fg-emoji">🚫</span> Hide Trash');
            loadTrashItems();
        }
    }
    
    /**
     * Load trash items
     */
    function loadTrashItems() {
        loadTrashedVehicles();
        loadTrashedLogs();
    }
    
    /**
     * Load trashed vehicles
     */
    function loadTrashedVehicles() {
        const container = $('#fg-trashed-vehicles-container');
        container.html('<div class="fg-loading">Loading...</div>');
        
        ajaxRequest('fuel_genius_get_trashed_vehicles', {}, function(data) {
            if (data.vehicles.length === 0) {
                container.html('<div class="fg-empty-state"><p>No deleted vehicles</p></div>');
                return;
            }
            
            let html = '';
            data.vehicles.forEach(function(vehicle) {
                const deletedDate = new Date(vehicle.deleted_at);
                const daysAgo = Math.floor((new Date() - deletedDate) / (1000 * 60 * 60 * 24));
                
                html += `
                    <div class="fg-trash-item">
                        <div class="fg-trash-item-info">
                            <div class="fg-trash-item-title">
                                ${vehicle.year} ${vehicle.make} ${vehicle.model}
                            </div>
                            <div class="fg-trash-item-meta">
                                Deleted ${daysAgo} day${daysAgo !== 1 ? 's' : ''} ago
                            </div>
                        </div>
                        <div class="fg-trash-item-actions">
                            <button class="fg-btn fg-btn-success fg-restore-vehicle" data-vehicle-id="${vehicle.id}">
                                <span class="fg-emoji">↩️</span> Restore
                            </button>
                            <button class="fg-btn fg-btn-danger fg-permanent-delete-vehicle" data-vehicle-id="${vehicle.id}">
                                <span class="fg-emoji">🗑️</span> Delete Forever
                            </button>
                        </div>
                    </div>
                `;
            });
            
            container.html(html);
        });
    }
    
    /**
     * Load trashed logs
     */
    function loadTrashedLogs() {
        const container = $('#fg-trashed-logs-container');
        container.html('<div class="fg-loading">Loading...</div>');
        
        ajaxRequest('fuel_genius_get_trashed_logs', {}, function(data) {
            if (data.logs.length === 0) {
                container.html('<div class="fg-empty-state"><p>No deleted fuel logs</p></div>');
                return;
            }
            
            let html = '';
            data.logs.forEach(function(log) {
                const deletedDate = new Date(log.deleted_at);
                const daysAgo = Math.floor((new Date() - deletedDate) / (1000 * 60 * 60 * 24));
                const vehicleName = log.make ? `${log.year} ${log.make} ${log.model}` : 'Unknown Vehicle';
                
                html += `
                    <div class="fg-trash-item">
                        <div class="fg-trash-item-info">
                            <div class="fg-trash-item-title">
                                ${vehicleName} - ${log.log_date}
                            </div>
                            <div class="fg-trash-item-meta">
                                ${log.fuel_quantity}L @ ₹${log.price_per_unit}/L | 
                                Deleted ${daysAgo} day${daysAgo !== 1 ? 's' : ''} ago
                            </div>
                        </div>
                        <div class="fg-trash-item-actions">
                            <button class="fg-btn fg-btn-success fg-restore-log" data-log-id="${log.id}">
                                <span class="fg-emoji">↩️</span> Restore
                            </button>
                            <button class="fg-btn fg-btn-danger fg-permanent-delete-log" data-log-id="${log.id}">
                                <span class="fg-emoji">🗑️</span> Delete Forever
                            </button>
                        </div>
                    </div>
                `;
            });
            
            container.html(html);
        });
    }
    
    /**
     * Handle restore vehicle
     */
    function handleRestoreVehicle(e) {
        e.preventDefault();
        const vehicleId = $(this).data('vehicle-id');
        
        ajaxRequest('fuel_genius_restore_vehicle', { vehicle_id: vehicleId }, function(data) {
            showMessage(data.message, 'success');
            loadTrashedVehicles();
            loadVehicles();
        });
    }
    
    /**
     * Handle restore log
     */
    function handleRestoreLog(e) {
        e.preventDefault();
        const logId = $(this).data('log-id');
        
        ajaxRequest('fuel_genius_restore_log', { log_id: logId }, function(data) {
            showMessage(data.message, 'success');
            loadTrashedLogs();
            
            if (currentVehicleId > 0) {
                loadRecentLogs(currentVehicleId);
                loadAnalytics(currentVehicleId, currentPeriod);
            }
        });
    }
    
    /**
     * Handle permanent delete vehicle
     */
    function handlePermanentDeleteVehicle(e) {
        e.preventDefault();
        
        if (!confirm('This will PERMANENTLY delete this vehicle and all its fuel logs. This action cannot be undone. Are you sure?')) {
            return;
        }
        
        const vehicleId = $(this).data('vehicle-id');
        
        ajaxRequest('fuel_genius_permanent_delete_vehicle', { vehicle_id: vehicleId }, function(data) {
            showMessage(data.message, 'success');
            loadTrashedVehicles();
        });
    }
    
    /**
     * Handle permanent delete log
     */
    function handlePermanentDeleteLog(e) {
        e.preventDefault();
        
        if (!confirm('This will PERMANENTLY delete this fuel log. This action cannot be undone. Are you sure?')) {
            return;
        }
        
        const logId = $(this).data('log-id');
        
        ajaxRequest('fuel_genius_permanent_delete_log', { log_id: logId }, function(data) {
            showMessage(data.message, 'success');
            loadTrashedLogs();
        });
    }
    
    /**
     * Make AJAX request
     */
    function ajaxRequest(action, data, successCallback, errorCallback) {
        $.ajax({
            url: fuelGeniusAjax.ajaxurl,
            type: 'POST',
            data: $.extend({
                action: action,
                nonce: fuelGeniusAjax.nonce
            }, data),
            success: function(response) {
                if (response.success) {
                    if (successCallback) successCallback(response.data);
                } else {
                    if (errorCallback) {
                        errorCallback(response.data);
                    } else if (action !== 'fuel_genius_get_vehicles' && action !== 'fuel_genius_get_analytics') {
                        showMessage(response.data.message || 'An error occurred', 'error');
                    }
                }
            },
            error: function(xhr, status, error) {
                if (action.indexOf('add_') !== -1 || action.indexOf('update_') !== -1 || action.indexOf('delete_') !== -1) {
                    showMessage('Network error. Please try again.', 'error');
                }
                if (errorCallback) errorCallback();
            }
        });
    }
    
    /**
     * Load vehicles
     */
    function loadVehicles() {
        ajaxRequest('fuel_genius_get_vehicles', {}, function(data) {
            allVehicles = data.vehicles;
            renderVehicles(data.vehicles);
            populateVehicleDropdowns(data.vehicles);
            
            if (data.vehicles.length > 0 && currentVehicleId === 0) {
                currentVehicleId = data.vehicles[0].id;
                selectVehicle(currentVehicleId);
            }
        });
    }
    
    /**
     * Render vehicles grid
     */
    function renderVehicles(vehicles) {
        const container = $('#fg-vehicles-grid');
        
        if (vehicles.length === 0) {
            container.html(`
                <div class="fg-empty-state">
                    <span class="fg-emoji" style="font-size: 64px; display: block; margin-bottom: 20px; opacity: 0.3;">🚗</span>
                    <h3>No Vehicles Yet</h3>
                    <p>Add your first vehicle to start tracking fuel efficiency!</p>
                </div>
            `);
            return;
        }
        
        let html = '';
        vehicles.forEach(function(vehicle) {
            const isSelected = vehicle.id == currentVehicleId ? 'selected' : '';
            html += `
                <div class="fg-vehicle-card ${isSelected}" data-vehicle-id="${vehicle.id}">
                    <div class="fg-vehicle-header">
                        <h3 class="fg-vehicle-title">${vehicle.year} ${vehicle.make} ${vehicle.model}</h3>
                        <span class="fg-vehicle-badge">${vehicle.fuel_type}</span>
                    </div>
                    <div class="fg-vehicle-details">
                        <p><span class="fg-emoji">📅</span> Year: ${vehicle.year}</p>
                        <p><span class="fg-emoji">⛽</span> Fuel: ${vehicle.fuel_type}</p>
                        ${vehicle.tank_capacity ? `<p><span class="fg-emoji">🛢️</span> Tank: ${vehicle.tank_capacity} L</p>` : ''}
                    </div>
                    <div class="fg-vehicle-actions">
                        <button type="button" class="fg-btn fg-btn-primary fg-select-vehicle" data-vehicle-id="${vehicle.id}">
                            <span class="fg-emoji">✓</span> Select
                        </button>
                        <button type="button" class="fg-btn fg-btn-danger fg-delete-vehicle" data-vehicle-id="${vehicle.id}">
                            <span class="fg-emoji">🗑️</span> Delete
                        </button>
                    </div>
                </div>
            `;
        });
        
        container.html(html);
    }
    
    /**
     * Populate vehicle dropdowns
     */
    function populateVehicleDropdowns(vehicles) {
        const logDropdown = $('#log-vehicle');
        const reportDropdown = $('#report-vehicle');
        
        logDropdown.find('option:not(:first)').remove();
        reportDropdown.find('option:not(:first)').remove();
        
        vehicles.forEach(function(vehicle) {
            const option = `<option value="${vehicle.id}">${vehicle.year} ${vehicle.make} ${vehicle.model}</option>`;
            logDropdown.append(option);
            reportDropdown.append(option);
        });
        
        if (currentVehicleId > 0) {
            logDropdown.val(currentVehicleId);
        }
    }
    
    function showAddVehicleForm() {
        $('#fg-add-vehicle-form').slideDown(300);
        $('#vehicle-make').focus();
    }
    
    function hideAddVehicleForm() {
        $('#fg-add-vehicle-form').slideUp(300);
        $('#fg-vehicle-form')[0].reset();
    }
    
    function handleAddVehicle(e) {
        e.preventDefault();
        
        const formData = {
            make: $('#vehicle-make').val(),
            model: $('#vehicle-model').val(),
            year: $('#vehicle-year').val(),
            fuel_type: $('#vehicle-fuel-type').val(),
            tank_capacity: $('#vehicle-tank-capacity').val()
        };
        
        ajaxRequest('fuel_genius_add_vehicle', formData, function(data) {
            showMessage(data.message);
            hideAddVehicleForm();
            loadVehicles();
        });
    }
    
    function handleSelectVehicle(e) {
        e.preventDefault();
        e.stopPropagation();
        const vehicleId = $(this).data('vehicle-id');
        selectVehicle(vehicleId);
    }
    
    function selectVehicle(vehicleId) {
        currentVehicleId = vehicleId;
        
        $('.fg-vehicle-card').removeClass('selected');
        $('.fg-vehicle-card[data-vehicle-id="' + vehicleId + '"]').addClass('selected');
        
        $('#log-vehicle').val(vehicleId);
        
        loadRecentLogs(vehicleId);
        loadAnalytics(vehicleId, currentPeriod);
        getLastPrice(vehicleId);
        getLastOdometer(vehicleId);
    }
    
    function handleDeleteVehicle(e) {
        e.preventDefault();
        e.stopPropagation();
        
        if (!confirm('Delete this vehicle? It will be moved to trash and can be restored within 30 days.')) {
            return;
        }
        
        const vehicleId = $(this).data('vehicle-id');
        
        ajaxRequest('fuel_genius_delete_vehicle', { vehicle_id: vehicleId }, function(data) {
            // Show undo notification
            showUndoNotification(data.message + '. Click to undo.', 'vehicle', vehicleId);
            
            if (currentVehicleId == vehicleId) {
                currentVehicleId = 0;
            }
            
            loadVehicles();
            $('#fg-recent-logs-container').html('<div class="fg-empty-state"><p>Select a vehicle to view logs</p></div>');
        });
    }
    
    function handleVehicleChange() {
        const vehicleId = $(this).val();
        if (vehicleId) {
            getLastPrice(vehicleId);
            getLastOdometer(vehicleId);
        }
    }
    
    function getLastPrice(vehicleId) {
        ajaxRequest('fuel_genius_get_last_price', { vehicle_id: vehicleId }, function(data) {
            if (data.last_price > 0) {
                $('#log-price').val(data.last_price);
                calculateTotalCost();
            }
        });
    }
    
    function getLastOdometer(vehicleId) {
        ajaxRequest('fuel_genius_get_last_odometer', { vehicle_id: vehicleId }, function(data) {
            if (data.last_odometer > 0) {
                lastOdometerReading = data.last_odometer;
                $('#log-odometer').val(data.last_odometer);
            }
        });
    }
    
    function calculateTotalCost() {
        const quantity = parseFloat($('#log-quantity').val()) || 0;
        const price = parseFloat($('#log-price').val()) || 0;
        const total = (quantity * price).toFixed(2);
        $('#log-total-cost').val(total);
    }
    
    function calculateEditTotalCost() {
        const quantity = parseFloat($('#edit-log-quantity').val()) || 0;
        const price = parseFloat($('#edit-log-price').val()) || 0;
        const total = (quantity * price).toFixed(2);
        $('#edit-log-total').val(total);
    }
    
    function handleAddFuelLog(e) {
        e.preventDefault();
        
        const formData = {
            vehicle_id: $('#log-vehicle').val(),
            log_date: $('#log-date').val(),
            odometer_reading: $('#log-odometer').val(),
            fuel_quantity: $('#log-quantity').val(),
            price_per_unit: $('#log-price').val(),
            total_cost: $('#log-total-cost').val(),
            filled_to_full_tank: $('#log-full-tank').is(':checked') ? 1 : 0
        };
        
        ajaxRequest('fuel_genius_add_fuel_log', formData, function(data) {
            showMessage(data.message);
            $('#fg-fuel-log-form')[0].reset();
            $('#log-date').val(new Date().toISOString().split('T')[0]);
            
            if (currentVehicleId > 0) {
                $('#log-vehicle').val(currentVehicleId);
                loadRecentLogs(currentVehicleId);
                loadAnalytics(currentVehicleId, currentPeriod);
                getLastPrice(currentVehicleId);
                getLastOdometer(currentVehicleId);
            }
        });
    }
    
    function loadRecentLogs(vehicleId) {
        const container = $('#fg-recent-logs-container');
        container.html('<div class="fg-loading">Loading logs...</div>');
        
        ajaxRequest('fuel_genius_get_recent_logs', { vehicle_id: vehicleId }, function(data) {
            if (data.logs.length === 0) {
                container.html(`
                    <div class="fg-empty-state">
                        <span class="fg-emoji" style="font-size: 64px; display: block; margin-bottom: 20px; opacity: 0.3;">📋</span>
                        <h3>No Fuel Logs Yet</h3>
                        <p>Add your first fuel log to start tracking!</p>
                    </div>
                `);
                return;
            }
            
            let html = '<div class="fg-table-wrapper"><table class="fg-table"><thead><tr>';
            html += '<th>Date</th><th>Odometer (km)</th><th>Quantity (L)</th><th>Cost (₹)</th><th>Efficiency (km/L)</th><th>Actions</th>';
            html += '</tr></thead><tbody>';
            
            data.logs.forEach(function(log) {
                const logJson = JSON.stringify(log).replace(/"/g, '&quot;');
                const effDisplay = log.efficiency === 'partial' ? '<span style="color: #f39c12;">Partial Fill</span>' : 
                                  (log.efficiency ? parseFloat(log.efficiency).toFixed(2) : 'N/A');
                html += `<tr>
                    <td>${log.log_date}</td>
                    <td>${parseInt(log.odometer_reading).toLocaleString()}</td>
                    <td>${parseFloat(log.fuel_quantity).toFixed(2)}</td>
                    <td>₹${parseFloat(log.total_cost).toFixed(2)}</td>
                    <td>${effDisplay}</td>
                    <td class="fg-table-actions">
                        <button class="fg-btn fg-btn-warning fg-edit-log" data-log='${logJson}'>
                            <span class="fg-emoji">✏️</span> Edit
                        </button>
                        <button class="fg-btn fg-btn-danger fg-delete-log" data-log-id="${log.id}">
                            <span class="fg-emoji">🗑️</span> Delete
                        </button>
                    </td>
                </tr>`;
            });
            
            html += '</tbody></table></div>';
            container.html(html);
        });
    }
    
    function handleEditLog(e) {
        e.preventDefault();
        const log = $(this).data('log');
        
        $('#edit-log-id').val(log.id);
        $('#edit-log-date').val(log.log_date);
        $('#edit-log-odometer').val(log.odometer_reading);
        $('#edit-log-quantity').val(log.fuel_quantity);
        $('#edit-log-price').val(log.price_per_unit);
        $('#edit-log-total').val(log.total_cost);
        $('#edit-log-full-tank').prop('checked', log.filled_to_full_tank == 1);
        
        $('#fg-edit-modal').fadeIn(300);
    }
    
    function handleUpdateLog(e) {
        e.preventDefault();
        
        const formData = {
            log_id: $('#edit-log-id').val(),
            log_date: $('#edit-log-date').val(),
            odometer_reading: $('#edit-log-odometer').val(),
            fuel_quantity: $('#edit-log-quantity').val(),
            price_per_unit: $('#edit-log-price').val(),
            total_cost: $('#edit-log-total').val(),
            filled_to_full_tank: $('#edit-log-full-tank').is(':checked') ? 1 : 0
        };
        
        ajaxRequest('fuel_genius_update_fuel_log', formData, function(data) {
            showMessage(data.message);
            closeModal();
            
            if (currentVehicleId > 0) {
                loadRecentLogs(currentVehicleId);
                loadAnalytics(currentVehicleId, currentPeriod);
            }
        });
    }
    
    function handleDeleteLog(e) {
        e.preventDefault();
        
        if (!confirm('Delete this fuel log? It will be moved to trash and can be restored within 30 days.')) {
            return;
        }
        
        const logId = $(this).data('log-id');
        
        ajaxRequest('fuel_genius_delete_fuel_log', { log_id: logId }, function(data) {
            // Show undo notification
            showUndoNotification(data.message + '. Click to undo.', 'log', logId);
            
            if (currentVehicleId > 0) {
                loadRecentLogs(currentVehicleId);
                loadAnalytics(currentVehicleId, currentPeriod);
            }
        });
    }
    
    function closeModal() {
        $('.fg-modal').fadeOut(300);
        $('#fg-edit-log-form')[0].reset();
    }
    
    function handlePeriodChange() {
        currentPeriod = $(this).val();
        
        if (currentVehicleId > 0) {
            loadAnalytics(currentVehicleId, currentPeriod);
        }
    }
    
    function loadAnalytics(vehicleId, period) {
        const container = $('#fg-metrics-grid');
        container.html('<div class="fg-loading">Loading analytics...</div>');
        
        ajaxRequest('fuel_genius_get_analytics', { vehicle_id: vehicleId, period: period }, function(data) {
            let html = '';
            
            // Total Logs Card
            html += `
                <div class="fg-metric-card total-logs">
                    <div class="fg-metric-label">Total Fuel Logs</div>
                    <div class="fg-metric-value">${data.total_logs || 0}</div>
                    <div class="fg-metric-trend neutral">
                        <span class="fg-emoji">📋</span> All time entries
                    </div>
                </div>
            `;
            
            // Overall Average Efficiency
            html += `
                <div class="fg-metric-card ${getTrendClass(data.efficiency_trend)}">
                    <div class="fg-metric-label">Average Efficiency</div>
                    <div class="fg-metric-value">${data.avg_efficiency || '0'} km/L</div>
                    <div class="fg-metric-trend ${data.efficiency_trend}">
                        ${getTrendIcon(data.efficiency_trend)} ${data.efficiency_trend}
                    </div>
                </div>
            `;
            
            // Best Efficiency
            html += `
                <div class="fg-metric-card success">
                    <div class="fg-metric-label">Best Efficiency</div>
                    <div class="fg-metric-value">${data.best_efficiency || '0'} km/L</div>
                    <div class="fg-metric-trend neutral">
                        ${data.best_efficiency_date || 'N/A'}
                    </div>
                </div>
            `;
            
            // Worst Efficiency
            html += `
                <div class="fg-metric-card warning">
                    <div class="fg-metric-label">Worst Efficiency</div>
                    <div class="fg-metric-value">${data.worst_efficiency || '0'} km/L</div>
                    <div class="fg-metric-trend neutral">
                        <span class="fg-emoji">⚠️</span> ${data.worst_efficiency_date || 'N/A'}
                    </div>
                </div>
            `;
            
            // Total Spending
            html += `
                <div class="fg-metric-card">
                    <div class="fg-metric-label">Total Spending (Period)</div>
                    <div class="fg-metric-value">₹${parseFloat(data.total_spending || 0).toFixed(2)}</div>
                </div>
            `;
            
            // Total Distance
            html += `
                <div class="fg-metric-card">
                    <div class="fg-metric-label">Total Distance (Lifetime)</div>
                    <div class="fg-metric-value">${parseInt(data.total_distance || 0).toLocaleString()} km</div>
                </div>
            `;
            
            // Total Fuel
            html += `
                <div class="fg-metric-card">
                    <div class="fg-metric-label">Total Fuel (Lifetime)</div>
                    <div class="fg-metric-value">${parseFloat(data.total_fuel || 0).toFixed(2)} L</div>
                </div>
            `;
            
            // Average Cost Per Fill-Up
            html += `
                <div class="fg-metric-card">
                    <div class="fg-metric-label">Avg Cost Per Fill-Up</div>
                    <div class="fg-metric-value">₹${parseFloat(data.avg_cost_per_fillup || 0).toFixed(2)}</div>
                </div>
            `;
            
            // Days Since Last Fill-Up
            const daysSinceClass = data.days_since_last_fillup <= 7 ? 'success' : (data.days_since_last_fillup > 14 ? 'danger' : 'warning');
            html += `
                <div class="fg-metric-card ${daysSinceClass}">
                    <div class="fg-metric-label">Days Since Last Fill-Up</div>
                    <div class="fg-metric-value">${data.days_since_last_fillup || 0}</div>
                </div>
            `;
            
            // Estimated Range
            if (data.estimated_range > 0) {
                html += `
                    <div class="fg-metric-card">
                        <div class="fg-metric-label">Estimated Range (Full Tank)</div>
                        <div class="fg-metric-value">${parseFloat(data.estimated_range).toFixed(2)} km</div>
                    </div>
                `;
            }
            
            container.html(html);
        });
    }
    
    function getTrendClass(trend) {
        if (trend === 'up') return 'success';
        if (trend === 'down') return 'danger';
        return '';
    }
    
    function getTrendIcon(trend) {
        if (trend === 'up') return '<span class="fg-emoji">⬆️</span>';
        if (trend === 'down') return '<span class="fg-emoji">⬇️</span>';
        return '<span class="fg-emoji">➡️</span>';
    }
    
    function handleViewAllLogs() {
        if (currentVehicleId === 0) {
            showMessage('Please select a vehicle first', 'error');
            return;
        }
        
        // Scroll to reports section
        $('html, body').animate({
            scrollTop: $('.fg-reports-section').offset().top - 20
        }, 500);
        
        // Auto-fill report form
        $('#report-vehicle').val(currentVehicleId);
        const endDate = new Date();
        const startDate = new Date();
        startDate.setDate(startDate.getDate() - 30);
        
        $('#report-start-date').val(startDate.toISOString().split('T')[0]);
        $('#report-end-date').val(endDate.toISOString().split('T')[0]);
        
        // Trigger report generation
        setTimeout(function() {
            $('#fg-report-form').submit();
        }, 600);
    }
    
    function handleGenerateReport(e) {
        e.preventDefault();
        
        const formData = {
            vehicle_id: $('#report-vehicle').val(),
            start_date: $('#report-start-date').val(),
            end_date: $('#report-end-date').val()
        };
        
        ajaxRequest('fuel_genius_generate_report', formData, function(data) {
            reportData = data;
            renderReport(data);
            $('#fg-report-results').slideDown(300);
            $('#fg-export-buttons').slideDown(300);
        });
    }
    
    function renderReport(data) {
        let html = `
            <div class="fg-report-header">
                <h3>Report: ${data.vehicle_name}</h3>
                <p>${data.start_date} to ${data.end_date}</p>
            </div>
            <div class="fg-report-stats">
                <div class="fg-report-stat">
                    <div class="fg-report-stat-label">Total Distance</div>
                    <div class="fg-report-stat-value">${parseInt(data.total_distance || 0).toLocaleString()} km</div>
                </div>
                <div class="fg-report-stat">
                    <div class="fg-report-stat-label">Total Fuel Used</div>
                    <div class="fg-report-stat-value">${parseFloat(data.total_fuel || 0).toFixed(2)} L</div>
                </div>
                <div class="fg-report-stat">
                    <div class="fg-report-stat-label">Total Cost</div>
                    <div class="fg-report-stat-value">₹${parseFloat(data.total_cost || 0).toFixed(2)}</div>
                </div>
                <div class="fg-report-stat">
                    <div class="fg-report-stat-label">Average Efficiency</div>
                    <div class="fg-report-stat-value">${parseFloat(data.avg_efficiency || 0).toFixed(2)} km/L</div>
                </div>
                <div class="fg-report-stat">
                    <div class="fg-report-stat-label">Cost Per Kilometer</div>
                    <div class="fg-report-stat-value">₹${parseFloat(data.cost_per_km || 0).toFixed(2)}/km</div>
                </div>
                <div class="fg-report-stat">
                    <div class="fg-report-stat-label">Number of Fill-Ups</div>
                    <div class="fg-report-stat-value">${data.fillup_count || 0}</div>
                </div>
            </div>
        `;
        
        $('#fg-report-results').html(html);
    }
    
    function exportToPDF() {
        if (!reportData || typeof window.jspdf === 'undefined') {
            showMessage('Unable to export PDF. Please generate a report first.', 'error');
            return;
        }
        
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF();
        
        doc.setFontSize(20);
        doc.text('Fuel Genius Report', 20, 20);
        
        doc.setFontSize(12);
        doc.text('Vehicle: ' + reportData.vehicle_name, 20, 35);
        doc.text('Period: ' + reportData.start_date + ' to ' + reportData.end_date, 20, 42);
        
        doc.setFontSize(14);
        doc.text('Summary', 20, 55);
        
        doc.setFontSize(10);
        let y = 65;
        doc.text('Total Distance: ' + parseInt(reportData.total_distance || 0).toLocaleString() + ' km', 20, y);
        y += 7;
        doc.text('Total Fuel Used: ' + parseFloat(reportData.total_fuel || 0).toFixed(2) + ' L', 20, y);
        y += 7;
        doc.text('Total Cost: Rs ' + parseFloat(reportData.total_cost || 0).toFixed(2), 20, y);
        y += 7;
        doc.text('Average Efficiency: ' + parseFloat(reportData.avg_efficiency || 0).toFixed(2) + ' km/L', 20, y);
        y += 7;
        doc.text('Cost Per Kilometer: Rs ' + parseFloat(reportData.cost_per_km || 0).toFixed(2) + '/km', 20, y);
        y += 7;
        doc.text('Number of Fill-Ups: ' + (reportData.fillup_count || 0), 20, y);
        
        doc.save('fuel-genius-report.pdf');
        showMessage('PDF downloaded successfully!', 'success');
    }
    
    function exportToCSV() {
        if (!reportData || !reportData.logs) {
            showMessage('Unable to export CSV. Please generate a report first.', 'error');
            return;
        }
        
        let csv = 'Date,Vehicle,Odometer (km),Fuel Quantity (L),Price Per Litre (Rs),Total Cost (Rs)\n';
        
        reportData.logs.forEach(function(log) {
            const vehicleName = log.year ? (log.year + ' ' + log.make + ' ' + log.model) : reportData.vehicle_name;
            csv += log.log_date + ',' + vehicleName + ',' + log.odometer_reading + ',' + log.fuel_quantity + ',' + log.price_per_unit + ',' + log.total_cost + '\n';
        });
        
        const blob = new Blob([csv], { type: 'text/csv' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'fuel-genius-export.csv';
        a.click();
        window.URL.revokeObjectURL(url);
        
        showMessage('CSV exported successfully!', 'success');
    }
    
    function exportToExcel() {
        if (!reportData || !reportData.logs || typeof XLSX === 'undefined') {
            showMessage('Unable to export Excel. Please generate a report first.', 'error');
            return;
        }
        
        const logsData = reportData.logs.map(function(log) {
            const vehicleName = log.year ? (log.year + ' ' + log.make + ' ' + log.model) : reportData.vehicle_name;
            return {
                'Date': log.log_date,
                'Vehicle': vehicleName,
                'Odometer (km)': log.odometer_reading,
                'Fuel Quantity (L)': log.fuel_quantity,
                'Price Per Litre (Rs)': log.price_per_unit,
                'Total Cost (Rs)': log.total_cost
            };
        });
        
        const wb = XLSX.utils.book_new();
        const logsSheet = XLSX.utils.json_to_sheet(logsData);
        XLSX.utils.book_append_sheet(wb, logsSheet, 'Fuel Logs');
        
        XLSX.writeFile(wb, 'fuel-genius-export.xlsx');
        
        showMessage('Excel file exported successfully!', 'success');
    }
    
})(jQuery);