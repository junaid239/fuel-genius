<?php
/**
 * Shortcodes Handler
 * Registers and handles the [fuel_genius_dashboard] shortcode
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register the main dashboard shortcode
 */
function fuel_genius_dashboard_shortcode($atts) {
    // Start output buffering
    ob_start();

    // Check if user is logged in
    if (!is_user_logged_in()) {
        // Include the restricted access template (which now redirects to UM login)
        include FUEL_GENIUS_PLUGIN_DIR . 'includes/restricted-dashboard.php';
        
        // Return buffered content (the login screen)
        return ob_get_clean();
    }
    
    // If logged in, include the full dashboard template
    include FUEL_GENIUS_PLUGIN_DIR . 'includes/dashboard-template.php';
    
    // Return buffered content (the full dashboard)
    return ob_get_clean();
}
add_shortcode('fuel_genius_dashboard', 'fuel_genius_dashboard_shortcode');