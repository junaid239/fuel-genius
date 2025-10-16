<?php
/**
 * Restricted Dashboard Template (Login Screen) - REVISED FOR ULTIMATE MEMBER
 * Displays a styled link that redirects to the UM Login Page with a 'redirect_to' parameter.
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// 1. Define the base UM Login URL
// IMPORTANT: This should be the exact URL of your UM Login page.
$um_login_base_url = 'https://thejunaid.in/login/';

// 2. Get the current URL to use for redirection after login
// This ensures the user is sent back to the exact page where the shortcode is located.
$current_page_url = esc_url(remove_query_arg(array('loggedout', 'registration'), wp_unslash($_SERVER['REQUEST_URI'])));

// 3. Construct the full redirect URL for UM
$full_um_login_url = add_query_arg('redirect_to', urlencode($current_page_url), $um_login_base_url);

?>

<div class="fuel-genius-container">
    <div class="fuel-genius-restricted-card">
        <div class="restricted-header">
            <span class="fg-emoji" style="font-size: 32px; display: block; margin-bottom: 15px;">🔒</span>
            <h2><?php esc_html_e('Dashboard Restricted', 'fuel-genius'); ?></h2>
            <p><?php esc_html_e('Please log in to access your Fuel Genius dashboard and vehicle data.', 'fuel-genius'); ?></p>
        </div>

        <a href="<?php echo $full_um_login_url; ?>" class="fg-btn fg-btn-primary fg-login-button">
            <?php esc_html_e('Log In to Your Account', 'fuel-genius'); ?>
        </a>
        
        <p class="redirection-notice">
            <?php esc_html_e('You will be redirected back here after logging in.', 'fuel-genius'); ?>
        </p>

    </div>
</div>

<style>
/* Basic styling to match the screenshot */
.fuel-genius-container {
    max-width: 100%;
    margin: 40px auto;
    display: flex;
    justify-content: center;
    align-items: center;
}

.fuel-genius-restricted-card {
    background: #ffffff;
    border-radius: 12px;
    padding: 40px 30px;
    text-align: center;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
    max-width: 450px;
    width: 100%;
}

.fuel-genius-restricted-card .restricted-header h2 {
    font-size: 24px;
    font-weight: 700;
    color: var(--fg-dark);
    margin: 0 0 10px 0;
}

.fuel-genius-restricted-card .restricted-header p {
    color: var(--fg-secondary);
    margin-bottom: 30px;
    font-size: 16px;
}

.fg-login-button {
    /* Custom styling to match your screenshot's button color */
    background: #667eea !important;
    padding: 12px 25px !important;
    font-size: 16px !important;
    font-weight: 600 !important;
    border-radius: 8px !important;
    text-decoration: none; /* FIX: Removes the underline */
    display: block; /* Make it full width of its container */
    max-width: 250px; /* Constrain width */
    margin: 0 auto 15px auto;
}

.fg-login-button:hover {
    background: #764ba2 !important;
}

/* Redirection Notice */
.redirection-notice {
    font-size: 13px;
    color: var(--fg-secondary);
    margin-top: 20px;
}
</style>