/**
 * Maho
 *
 * @package     default_default
 * @copyright   Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license     https://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

/**
 * Maho Installer - Modern Interactive Experience
 */
(function() {
    'use strict';

    // ========================================
    // License Agreement Page
    // ========================================

    function initializeLicensePage() {
        const licenseBox = document.getElementById('licenseBox');
        const agreeCheckbox = document.getElementById('agree');
        const submitButton = document.getElementById('submitButton');
        const licenseForm = document.getElementById('licenseForm');

        if (!licenseBox || !agreeCheckbox || !submitButton) {
            return; // Not on license page
        }

        // Handle checkbox change
        agreeCheckbox.addEventListener('change', (e) => {
            submitButton.disabled = !e.target.checked;
        });

        // Handle form submission with view transition
        if (licenseForm) {
            licenseForm.addEventListener('submit', (e) => {
                if (!agreeCheckbox.checked) {
                    e.preventDefault();
                    return;
                }
                // Let the form submit naturally, view transition will be handled by browser
            });
        }
    }

    // ========================================
    // Configuration Page - SSL Toggle
    // ========================================

    function initializeConfigPage() {
        const useSecureCheckbox = document.getElementById('use_secure');
        const secureOptions = document.getElementById('use_secure_options');

        if (useSecureCheckbox && secureOptions) {
            // Handle SSL checkbox toggle
            useSecureCheckbox.addEventListener('change', (e) => {
                if (e.target.checked) {
                    secureOptions.classList.remove('hidden');
                    // Animate in
                    secureOptions.style.opacity = '0';
                    secureOptions.style.transform = 'translateY(-10px)';
                    setTimeout(() => {
                        secureOptions.style.transition = 'opacity 0.3s ease-out, transform 0.3s ease-out';
                        secureOptions.style.opacity = '1';
                        secureOptions.style.transform = 'translateY(0)';
                    }, 10);
                } else {
                    // Animate out
                    secureOptions.style.opacity = '0';
                    secureOptions.style.transform = 'translateY(-10px)';
                    setTimeout(() => {
                        secureOptions.classList.add('hidden');
                    }, 300);
                }
            });
        }

        // Database type switcher
        const dbModelSelect = document.getElementById('db_model_select');
        if (dbModelSelect) {
            const dbForms = document.querySelectorAll('.db-connection-form');

            dbModelSelect.addEventListener('change', (e) => {
                const selectedType = e.target.value;

                dbForms.forEach(form => {
                    const formType = form.dataset.dbType;

                    if (formType === selectedType) {
                        // Show selected
                        form.classList.remove('hidden');
                        form.style.opacity = '0';
                        form.style.transform = 'translateY(-10px)';
                        setTimeout(() => {
                            form.style.transition = 'opacity 0.3s ease-out, transform 0.3s ease-out';
                            form.style.opacity = '1';
                            form.style.transform = 'translateY(0)';
                        }, 10);
                    } else {
                        // Hide others
                        form.style.opacity = '0';
                        form.style.transform = 'translateY(-10px)';
                        setTimeout(() => {
                            form.classList.add('hidden');
                        }, 300);
                    }
                });
            });
        }
    }

    // ========================================
    // Form Validation Enhancement
    // ========================================

    function enhanceFormValidation() {
        // Add focus animations to all inputs
        const inputs = document.querySelectorAll('.input-text, select, textarea');
        inputs.forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement?.classList.add('focused');
            });

            input.addEventListener('blur', function() {
                this.parentElement?.classList.remove('focused');
            });

            // Add validation state handling
            input.addEventListener('invalid', function(e) {
                this.classList.add('validation-failed');

                // Scroll to first error smoothly
                if (this === document.querySelector('.validation-failed')) {
                    this.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            });

            input.addEventListener('input', function() {
                if (this.classList.contains('validation-failed') && this.validity.valid) {
                    this.classList.remove('validation-failed');
                    this.classList.add('validation-success');
                    setTimeout(() => {
                        this.classList.remove('validation-success');
                    }, 1000);
                }
            });
        });

        // Enhance all forms with smooth transitions
        const forms = document.querySelectorAll('form');
        forms.forEach(form => {
            form.addEventListener('submit', function(e) {
                const invalidInputs = this.querySelectorAll(':invalid');
                if (invalidInputs.length > 0) {
                    e.preventDefault();
                    invalidInputs.forEach(input => {
                        input.classList.add('validation-failed');
                    });
                    invalidInputs[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
                    invalidInputs[0].focus();
                }
            });
        });
    }

    // ========================================
    // Smooth Scrolling for In-Page Links
    // ========================================

    function initializeSmoothScrolling() {
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    e.preventDefault();
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    }

    // ========================================
    // Progress Stepper Enhancements
    // ========================================

    function enhanceProgressStepper() {
        const stepper = document.querySelector('.progress-stepper');
        if (!stepper) return;

        const activeStep = stepper.querySelector('.stepper-item.active');
        const completedSteps = stepper.querySelectorAll('.stepper-item.completed');

        // Animate completed steps sequentially on page load
        completedSteps.forEach((step, index) => {
            step.style.opacity = '0';
            setTimeout(() => {
                step.style.transition = 'opacity 0.3s ease-out';
                step.style.opacity = '1';
            }, index * 100);
        });

        // Animate active step
        if (activeStep) {
            activeStep.style.opacity = '0';
            setTimeout(() => {
                activeStep.style.transition = 'opacity 0.5s ease-out';
                activeStep.style.opacity = '1';
            }, completedSteps.length * 100);
        }
    }

    // ========================================
    // Intersection Observer for Fade-in Effects
    // ========================================

    function initializeScrollAnimations() {
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                    observer.unobserve(entry.target);
                }
            });
        }, observerOptions);

        // Observe elements that should animate in
        document.querySelectorAll('.form-list li, .button-set').forEach(el => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(20px)';
            el.style.transition = 'opacity 0.5s ease-out, transform 0.5s ease-out';
            observer.observe(el);
        });

        // Add visible class styles dynamically
        const style = document.createElement('style');
        style.textContent = `
            .visible {
                opacity: 1 !important;
                transform: translateY(0) !important;
            }
        `;
        document.head.appendChild(style);
    }

    // ========================================
    // Auto-focus First Input
    // ========================================

    function autoFocusFirstInput() {
        const firstInput = document.querySelector('.input-text:not([readonly]):not([disabled])');
        if (firstInput && window.innerWidth > 768) {
            setTimeout(() => {
                firstInput.focus();
            }, 300);
        }
    }

    // ========================================
    // Message Animations
    // ========================================

    function animateMessages() {
        const messages = document.querySelectorAll('.error-msg, .success-msg, .notice-msg, .note-msg');
        messages.forEach((msg, index) => {
            msg.style.opacity = '0';
            msg.style.transform = 'translateX(-20px)';
            setTimeout(() => {
                msg.style.transition = 'opacity 0.3s ease-out, transform 0.3s ease-out';
                msg.style.opacity = '1';
                msg.style.transform = 'translateX(0)';
            }, index * 100);
        });
    }

    // ========================================
    // Keyboard Navigation Enhancement
    // ========================================

    function enhanceKeyboardNavigation() {
        // Allow Enter key to check checkbox labels
        document.querySelectorAll('.checkbox-label').forEach(label => {
            label.setAttribute('tabindex', '0');
            label.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    const checkbox = label.querySelector('input[type="checkbox"]');
                    if (checkbox) {
                        checkbox.checked = !checkbox.checked;
                        checkbox.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                }
            });
        });

        // Trap focus in modals if any exist
        const modals = document.querySelectorAll('[role="dialog"]');
        modals.forEach(modal => {
            const focusableElements = modal.querySelectorAll(
                'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
            );
            const firstFocusable = focusableElements[0];
            const lastFocusable = focusableElements[focusableElements.length - 1];

            modal.addEventListener('keydown', (e) => {
                if (e.key === 'Tab') {
                    if (e.shiftKey && document.activeElement === firstFocusable) {
                        e.preventDefault();
                        lastFocusable.focus();
                    } else if (!e.shiftKey && document.activeElement === lastFocusable) {
                        e.preventDefault();
                        firstFocusable.focus();
                    }
                }
            });
        });
    }

    // ========================================
    // Password Strength Indicator (if needed)
    // ========================================

    function addPasswordStrengthIndicator() {
        const passwordInputs = document.querySelectorAll('input[type="password"]');
        passwordInputs.forEach(input => {
            // Skip confirmation fields
            if (input.name.includes('confirm') || input.id.includes('confirm')) {
                return;
            }

            const container = input.parentElement;
            if (!container) return;

            const strengthMeter = document.createElement('div');
            strengthMeter.className = 'password-strength';
            strengthMeter.style.display = 'none'; // Initially hidden
            strengthMeter.innerHTML = `
                <div class="password-strength-bar">
                    <div class="password-strength-fill"></div>
                </div>
                <div class="password-strength-text"></div>
            `;

            // Add styles
            const style = document.createElement('style');
            style.textContent = `
                .password-strength {
                    margin-top: 8px;
                }
                .password-strength-bar {
                    height: 4px;
                    background: var(--color-border-light);
                    border-radius: 2px;
                    overflow: hidden;
                }
                .password-strength-fill {
                    height: 100%;
                    width: 0;
                    transition: width 0.3s ease, background 0.3s ease;
                    background: var(--color-border);
                }
                .password-strength-text {
                    font-size: var(--font-size-xs);
                    margin-top: 4px;
                    color: var(--color-text-light);
                }
            `;
            if (!document.getElementById('password-strength-styles')) {
                style.id = 'password-strength-styles';
                document.head.appendChild(style);
            }

            container.appendChild(strengthMeter);

            const fill = strengthMeter.querySelector('.password-strength-fill');
            const text = strengthMeter.querySelector('.password-strength-text');

            input.addEventListener('input', function() {
                const password = this.value;
                let strength = 0;
                let strengthText = '';

                if (password.length === 0) {
                    strengthMeter.style.display = 'none';
                    fill.style.width = '0';
                    text.textContent = '';
                    return;
                }

                // Show the meter when user starts typing
                strengthMeter.style.display = 'block';

                if (password.length >= 8) strength++;
                if (password.length >= 12) strength++;
                if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength++;
                if (/\d/.test(password)) strength++;
                if (/[^a-zA-Z0-9]/.test(password)) strength++;

                const percentage = (strength / 5) * 100;
                fill.style.width = percentage + '%';

                if (strength <= 2) {
                    fill.style.background = '#dc3545';
                    strengthText = 'Weak';
                } else if (strength <= 3) {
                    fill.style.background = '#ffc107';
                    strengthText = 'Fair';
                } else if (strength <= 4) {
                    fill.style.background = '#28a745';
                    strengthText = 'Good';
                } else {
                    fill.style.background = '#20c997';
                    strengthText = 'Strong';
                }

                text.textContent = strengthText;
                text.style.color = fill.style.background;
            });
        });
    }

    // ========================================
    // Initialize Everything
    // ========================================

    function init() {
        // Wait for DOM to be fully loaded
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', init);
            return;
        }

        // Initialize all features
        initializeLicensePage();
        initializeConfigPage();
        enhanceFormValidation();
        initializeSmoothScrolling();
        enhanceProgressStepper();
        initializeScrollAnimations();
        autoFocusFirstInput();
        animateMessages();
        enhanceKeyboardNavigation();
        addPasswordStrengthIndicator();

        // Add page loaded class for animations
        setTimeout(() => {
            document.body.classList.add('page-loaded');
        }, 100);
    }

    init();
})();
