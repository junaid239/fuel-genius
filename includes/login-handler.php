<?php
/**
 * Login Handler for Fuel Genius
 * Handles authentication and session management
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handle login AJAX request
 */
function fuel_genius_handle_login() {
    // Verify nonce
    check_ajax_referer('fuel_genius_login_nonce', 'nonce');

    $username = sanitize_text_field($_POST['username']);
    $password = $_POST['password'];
    $remember = isset($_POST['remember']) && $_POST['remember'] === 'true';

    // Attempt to authenticate user
    $user = wp_authenticate($username, $password);

    if (is_wp_error($user)) {
        wp_send_json_error(array(
            'message' => 'Invalid username or password. Please try again.'
        ));
        return;
    }

    // Set authentication cookie
    wp_set_current_user($user->ID);
    wp_set_auth_cookie($user->ID, $remember);
    do_action('wp_login', $user->user_login, $user);

    wp_send_json_success(array(
        'message' => 'Login successful! Redirecting...',
        'user_id' => $user->ID,
        'user_name' => $user->display_name
    ));
}
add_action('wp_ajax_nopriv_fuel_genius_login', 'fuel_genius_handle_login');
add_action('wp_ajax_fuel_genius_login', 'fuel_genius_handle_login');

/**
 * Handle logout AJAX request
 */
function fuel_genius_handle_logout() {
    check_ajax_referer('fuel_genius_nonce', 'nonce');
    
    wp_logout();
    
    wp_send_json_success(array(
        'message' => 'Logged out successfully'
    ));
}
add_action('wp_ajax_fuel_genius_logout', 'fuel_genius_handle_logout');
