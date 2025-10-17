<?php
/**
 * Interactive Login Screen Template
 * Displays an engaging login form that authenticates users directly
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="fuel-genius-login-wrapper">
    <div class="fuel-genius-login-container">
        <!-- Animated Background Elements -->
        <div class="login-bg-shapes">
            <div class="shape shape-1"></div>
            <div class="shape shape-2"></div>
            <div class="shape shape-3"></div>
        </div>

        <!-- Login Card -->
        <div class="fuel-genius-login-card">
            <!-- Logo/Brand Section -->
            <div class="login-brand">
                <div class="brand-icon">
                    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 2L2 7L12 12L22 7L12 2Z" fill="url(#gradient1)" stroke="currentColor" stroke-width="2"/>
                        <path d="M2 17L12 22L22 17" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        <path d="M2 12L12 17L22 12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        <defs>
                            <linearGradient id="gradient1" x1="2" y1="2" x2="22" y2="12">
                                <stop offset="0%" stop-color="#667eea"/>
                                <stop offset="100%" stop-color="#764ba2"/>
                            </linearGradient>
                        </defs>
                    </svg>
                </div>
                <h1><?php esc_html_e('Fuel Genius', 'fuel-genius'); ?></h1>
                <p class="login-subtitle"><?php esc_html_e('Track, Analyze, Optimize', 'fuel-genius'); ?></p>
            </div>

            <!-- Welcome Message -->
            <div class="login-welcome">
                <h2><?php esc_html_e('Welcome Back!', 'fuel-genius'); ?></h2>
                <p><?php esc_html_e('Sign in to access your fuel tracking dashboard', 'fuel-genius'); ?></p>
            </div>

            <!-- Alert Messages -->
            <div id="fg-login-alert" class="fg-alert" style="display: none;">
                <span class="alert-icon"></span>
                <span class="alert-message"></span>
            </div>

            <!-- Login Form -->
            <form id="fuel-genius-login-form" class="fg-login-form">
                <div class="form-group">
                    <label for="fg-username">
                        <svg class="input-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M20 21V19C20 17.9391 19.5786 16.9217 18.8284 16.1716C18.0783 15.4214 17.0609 15 16 15H8C6.93913 15 5.92172 15.4214 5.17157 16.1716C4.42143 16.9217 4 17.9391 4 19V21" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M12 11C14.2091 11 16 9.20914 16 7C16 4.79086 14.2091 3 12 3C9.79086 3 8 4.79086 8 7C8 9.20914 9.79086 11 12 11Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <?php esc_html_e('Username or Email', 'fuel-genius'); ?>
                    </label>
                    <input 
                        type="text" 
                        id="fg-username" 
                        name="username" 
                        class="fg-input" 
                        placeholder="<?php esc_attr_e('Enter your username or email', 'fuel-genius'); ?>"
                        required 
                        autocomplete="username"
                    >
                </div>

                <div class="form-group">
                    <label for="fg-password">
                        <svg class="input-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M7 11V7C7 5.67392 7.52678 4.40215 8.46447 3.46447C9.40215 2.52678 10.6739 2 12 2C13.3261 2 14.5979 2.52678 15.5355 3.46447C16.4732 4.40215 17 5.67392 17 7V11" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <?php esc_html_e('Password', 'fuel-genius'); ?>
                    </label>
                    <div class="password-input-wrapper">
                        <input 
                            type="password" 
                            id="fg-password" 
                            name="password" 
                            class="fg-input" 
                            placeholder="<?php esc_attr_e('Enter your password', 'fuel-genius'); ?>"
                            required 
                            autocomplete="current-password"
                        >
                        <button type="button" class="password-toggle" aria-label="Toggle password visibility">
                            <svg class="eye-open" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M1 12C1 12 5 4 12 4C19 4 23 12 23 12C23 12 19 20 12 20C5 20 1 12 1 12Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <svg class="eye-closed" style="display: none;" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M17.94 17.94C16.2306 19.243 14.1491 19.9649 12 20C5 20 1 12 1 12C2.24389 9.68192 3.96914 7.65663 6.06 6.06L17.94 17.94Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M12 4C14.1491 4.03513 16.2306 4.75701 17.94 6.06L6.06 17.94C3.96914 16.3434 2.24389 14.3181 1 12C1 12 5 4 12 4Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                <line x1="1" y1="1" x2="23" y2="23" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="form-options">
                    <label class="checkbox-label">
                        <input type="checkbox" id="fg-remember" name="remember">
                        <span class="checkbox-custom"></span>
                        <span><?php esc_html_e('Remember me', 'fuel-genius'); ?></span>
                    </label>
                    <a href="<?php echo esc_url(wp_lostpassword_url()); ?>" class="forgot-password">
                        <?php esc_html_e('Forgot Password?', 'fuel-genius'); ?>
                    </a>
                </div>

                <button type="submit" class="fg-btn fg-btn-primary fg-btn-login">
                    <span class="btn-text"><?php esc_html_e('Sign In', 'fuel-genius'); ?></span>
                    <span class="btn-loader" style="display: none;">
                        <svg class="spinner" viewBox="0 0 24 24">
                            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none" opacity="0.25"/>
                            <path d="M12 2C6.48 2 2 6.48 2 12" stroke="currentColor" stroke-width="4" fill="none" stroke-linecap="round"/>
                        </svg>
                    </span>
                </button>
            </form>

            <!-- Additional Links -->
            <div class="login-footer">
                <p><?php esc_html_e("Don't have an account?", 'fuel-genius'); ?> 
                    <a href="<?php echo esc_url(wp_registration_url()); ?>"><?php esc_html_e('Sign Up', 'fuel-genius'); ?></a>
                </p>
            </div>

            <!-- Features Section -->
            <div class="login-features">
                <div class="feature-item">
                    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M9 11L12 14L22 4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M21 12V19C21 19.5304 20.7893 20.0391 20.4142 20.4142C20.0391 20.7893 19.5304 21 19 21H5C4.46957 21 3.96086 20.7893 3.58579 20.4142C3.21071 20.0391 3 19.5304 3 19V5C3 4.46957 3.21071 3.96086 3.58579 3.58579C3.96086 3.21071 4.46957 3 5 3H16" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <span><?php esc_html_e('Track Multiple Vehicles', 'fuel-genius'); ?></span>
                </div>
                <div class="feature-item">
                    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M23 6L13.5 15.5L8.5 10.5L1 18" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M17 6H23V12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <span><?php esc_html_e('Analyze Fuel Efficiency', 'fuel-genius'); ?></span>
                </div>
                <div class="feature-item">
                    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M12 6V12L16 14" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <span><?php esc_html_e('Save Time & Money', 'fuel-genius'); ?></span>
                </div>
            </div>
        </div>
    </div>
</div>
