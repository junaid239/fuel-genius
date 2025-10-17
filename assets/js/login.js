/**
 * Fuel Genius Login JavaScript
 * Handles login form interactions and authentication
 */

(function($) {
    'use strict';

    // Wait for DOM to be ready
    $(document).ready(function() {
        initLoginForm();
        initPasswordToggle();
        initFormValidation();
        initInputAnimations();
    });

    /**
     * Initialize login form submission
     */
    function initLoginForm() {
        const $form = $('#fuel-genius-login-form');
        const $submitBtn = $form.find('.fg-btn-login');
        const $btnText = $submitBtn.find('.btn-text');
        const $btnLoader = $submitBtn.find('.btn-loader');

        $form.on('submit', function(e) {
            e.preventDefault();

            // Get form values
            const username = $('#fg-username').val().trim();
            const password = $('#fg-password').val();
            const remember = $('#fg-remember').is(':checked');

            // Validate inputs
            if (!username || !password) {
                showAlert('Please fill in all fields', 'error');
                return;
            }

            // Disable submit button and show loader
            $submitBtn.prop('disabled', true);
            $btnText.hide();
            $btnLoader.show();

            // Send AJAX request
            $.ajax({
                url: fuelGeniusLogin.ajaxurl,
                type: 'POST',
                data: {
                    action: 'fuel_genius_login',
                    username: username,
                    password: password,
                    remember: remember,
                    nonce: fuelGeniusLogin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        showAlert(response.data.message, 'success');
                        
                        // Add success animation
                        $form.addClass('login-success');
                        
                        // Redirect after short delay
                        setTimeout(function() {
                            window.location.reload();
                        }, 1000);
                    } else {
                        showAlert(response.data.message, 'error');
                        resetSubmitButton();
                    }
                },
                error: function(xhr, status, error) {
                    showAlert('An error occurred. Please try again.', 'error');
                    resetSubmitButton();
                    console.error('Login error:', error);
                }
            });
        });

        function resetSubmitButton() {
            $submitBtn.prop('disabled', false);
            $btnText.show();
            $btnLoader.hide();
        }
    }

    /**
     * Initialize password toggle functionality
     */
    function initPasswordToggle() {
        const $passwordInput = $('#fg-password');
        const $toggleBtn = $('.password-toggle');
        const $eyeOpen = $toggleBtn.find('.eye-open');
        const $eyeClosed = $toggleBtn.find('.eye-closed');

        $toggleBtn.on('click', function() {
            const currentType = $passwordInput.attr('type');
            
            if (currentType === 'password') {
                $passwordInput.attr('type', 'text');
                $eyeOpen.hide();
                $eyeClosed.show();
            } else {
                $passwordInput.attr('type', 'password');
                $eyeOpen.show();
                $eyeClosed.hide();
            }
        });
    }

    /**
     * Initialize form validation
     */
    function initFormValidation() {
        const $inputs = $('.fg-input');

        $inputs.on('blur', function() {
            const $input = $(this);
            const value = $input.val().trim();

            if ($input.prop('required') && !value) {
                $input.addClass('input-error');
            } else {
                $input.removeClass('input-error');
            }
        });

        $inputs.on('focus', function() {
            $(this).removeClass('input-error');
        });

        // Add input error styles
        if (!$('style#input-error-styles').length) {
            $('<style id="input-error-styles">')
                .text('.fg-input.input-error { border-color: #ef4444; }')
                .appendTo('head');
        }
    }

    /**
     * Initialize input animations
     */
    function initInputAnimations() {
        const $inputs = $('.fg-input');

        $inputs.on('focus', function() {
            $(this).parent().addClass('input-focused');
        });

        $inputs.on('blur', function() {
            $(this).parent().removeClass('input-focused');
        });
    }

    /**
     * Show alert message
     */
    function showAlert(message, type) {
        const $alert = $('#fg-login-alert');
        const $alertMessage = $alert.find('.alert-message');

        // Remove existing classes
        $alert.removeClass('alert-error alert-success alert-warning');

        // Add new class
        $alert.addClass('alert-' + type);

        // Set message
        $alertMessage.text(message);

        // Show alert with animation
        $alert.slideDown(300);

        // Auto-hide after 5 seconds for non-error messages
        if (type !== 'error') {
            setTimeout(function() {
                $alert.slideUp(300);
            }, 5000);
        }
    }

    /**
     * Hide alert message
     */
    function hideAlert() {
        $('#fg-login-alert').slideUp(300);
    }

    /**
     * Add enter key support for form submission
     */
    $(document).on('keypress', '.fg-input', function(e) {
        if (e.which === 13) {
            e.preventDefault();
            $('#fuel-genius-login-form').submit();
        }
    });

    /**
     * Add loading state to external links
     */
    $('.login-footer a, .forgot-password').on('click', function() {
        const $link = $(this);
        $link.css('opacity', '0.6');
    });

    /**
     * Prevent double submission
     */
    let isSubmitting = false;
    $('#fuel-genius-login-form').on('submit', function() {
        if (isSubmitting) {
            return false;
        }
        isSubmitting = true;
        
        setTimeout(function() {
            isSubmitting = false;
        }, 3000);
    });

    /**
     * Add animation to form on load
     */
    setTimeout(function() {
        $('.fuel-genius-login-card').addClass('loaded');
    }, 100);

})(jQuery);
