/**
 * Maho One-Page Checkout Helper
 *
 * @package     js
 * @copyright   Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license     https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Lightweight helper to enhance checkout with one-page behavior
 *
 * Features:
 * - Guest checkout by default with "Login" link
 * - Auto-save forms when complete
 * - Smart shipping section visibility
 * - Clean event-driven architecture (no promise wrappers)
 */
class OnePageCheckoutHelper {
    constructor() {
        this.initialized = false;
        this.autoSaveTimers = new Map();
    }

    init() {
        if (this.initialized) return;

        console.log('OnePageCheckoutHelper: Initializing...');

        // Setup features (one-page mode is already enabled in template)
        this.hideLoginSection();
        this.setupGuestCheckout();
        this.setupShippingVisibility();
        this.setupAutoSave();
        this.preloadSections();

        // Add placeholders immediately on init
        setTimeout(() => {
            this.addSectionPlaceholders();
        }, 600);

        this.initialized = true;
        console.log('OnePageCheckoutHelper: Initialized successfully');
    }

    /**
     * Preload all checkout sections on page load
     */
    preloadSections() {
        // Set up observer to show sections when they get content
        this.observeSectionContent();

        // Trigger initial load of all sections
        setTimeout(() => {
            console.log('OnePageCheckoutHelper: Triggering initial section loads...');

            // Load shipping methods if billing is complete
            if (window.billing) {
                const billingForm = document.getElementById('co-billing-form');
                if (billingForm && this.isFormComplete(billingForm)) {
                    console.log('OnePageCheckoutHelper: Billing complete, loading shipping...');
                    billing.save();
                }
            }

            // Load payment methods if shipping method is selected
            setTimeout(() => {
                if (window.shippingMethod) {
                    const selectedShipping = document.querySelector('input[name="shipping_method"]:checked');
                    if (selectedShipping) {
                        console.log('OnePageCheckoutHelper: Shipping selected, loading payment...');
                        shippingMethod.save();
                    }
                }
            }, 500);

            // Load review if payment is selected
            setTimeout(() => {
                if (window.payment) {
                    const selectedPayment = document.querySelector('input[name="payment[method]"]:checked');
                    if (selectedPayment) {
                        console.log('OnePageCheckoutHelper: Payment selected, loading review...');
                        payment.save();
                    }
                }
            }, 1000);
        }, 300);
    }

    /**
     * Hide sections that don't have content yet
     */
    hideEmptySections() {
        const sectionsToCheck = [
            { section: 'opc-shipping_method', content: 'checkout-shipping-method-load' },
            { section: 'opc-payment', content: 'checkout-payment-method-load' }
        ];

        sectionsToCheck.forEach(({ section, content }) => {
            const sectionEl = document.getElementById(section);
            const contentEl = document.getElementById(content);

            if (sectionEl && contentEl) {
                if (!contentEl.innerHTML.trim()) {
                    sectionEl.classList.add('opc-section-empty');
                    sectionEl.style.display = 'none';
                } else {
                    sectionEl.classList.remove('opc-section-empty');
                    sectionEl.style.display = 'block';
                }
            }
        });
    }

    /**
     * Watch for content changes in sections
     */
    observeSectionContent() {
        const contentAreas = [
            'checkout-shipping-method-load',
            'checkout-payment-method-load'
        ];

        contentAreas.forEach(areaId => {
            const area = document.getElementById(areaId);
            if (!area) return;

            // Use MutationObserver to detect when content is added
            const observer = new MutationObserver(() => {
                this.hideEmptySections();
            });

            observer.observe(area, {
                childList: true,
                subtree: true,
                characterData: true
            });
        });
    }

    /**
     * Guest checkout by default
     */
    setupGuestCheckout() {
        const guestRadio = document.getElementById('login:guest');
        const registerRadio = document.getElementById('login:register');

        if (!guestRadio) {
            console.log('OnePageCheckoutHelper: No guest checkout option (user logged in)');
            return;
        }

        // Force guest checkout selection
        guestRadio.checked = true;
        if (registerRadio) {
            registerRadio.checked = false;
        }

        // Auto-save guest method immediately
        if (window.checkout && typeof checkout.setMethod === 'function') {
            setTimeout(() => {
                console.log('OnePageCheckoutHelper: Setting guest checkout method...');
                checkout.setMethod();
            }, 200);
        }

        // Setup login link click handler (link is in template now)
        this.setupLoginLink();

        // Setup "Create account" checkbox handler
        this.setupCreateAccountCheckbox();

        console.log('OnePageCheckoutHelper: Guest checkout configured');
    }

    /**
     * Handle "Create account" checkbox to show/hide password fields
     */
    setupCreateAccountCheckbox() {
        const createAccountCheckbox = document.getElementById('billing:create_account');
        const passwordSection = document.getElementById('register-customer-password');
        const rememberMeBox = document.querySelector('.remember-me-box');

        if (!createAccountCheckbox || !passwordSection) {
            console.log('OnePageCheckoutHelper: Create account elements not found');
            return;
        }

        createAccountCheckbox.addEventListener('change', () => {
            if (createAccountCheckbox.checked) {
                document.body.classList.add('show-password-fields');
                console.log('OnePageCheckoutHelper: Password fields shown');
            } else {
                document.body.classList.remove('show-password-fields');
                console.log('OnePageCheckoutHelper: Password fields hidden');
            }
        });

        console.log('OnePageCheckoutHelper: Create account checkbox configured');
    }

    /**
     * Setup login link click handler
     */
    setupLoginLink() {
        const showLoginBtn = document.getElementById('onepage-show-login');
        if (showLoginBtn) {
            showLoginBtn.addEventListener('click', (e) => {
                e.preventDefault();
                const loginSection = document.getElementById('opc-login');
                if (loginSection) {
                    loginSection.style.display = 'block';
                    loginSection.scrollIntoView({ behavior: 'smooth' });
                }
            });
        }
    }

    /**
     * Hide login section by default
     */
    hideLoginSection() {
        const loginSection = document.getElementById('opc-login');
        if (loginSection) {
            loginSection.style.display = 'none';
            console.log('OnePageCheckoutHelper: Login section hidden');
        }
    }

    /**
     * Hide/show shipping section based on "Ship to this address"
     */
    setupShippingVisibility() {
        const setupVisibility = () => {
            const useBillingYes = document.getElementById('billing:use_for_shipping_yes');
            const useBillingNo = document.getElementById('billing:use_for_shipping_no');
            const shippingSection = document.getElementById('opc-shipping');

            if (!shippingSection) {
                console.log('OnePageCheckoutHelper: Shipping section not found yet');
                return;
            }

            if (!useBillingYes || !useBillingNo) {
                console.log('OnePageCheckoutHelper: Shipping radio buttons not found yet');
                return;
            }

            const updateVisibility = () => {
                if (useBillingYes && useBillingYes.checked) {
                    shippingSection.style.display = 'none';
                    console.log('OnePageCheckoutHelper: Shipping section hidden (using billing address)');
                } else {
                    shippingSection.style.display = 'block';
                    console.log('OnePageCheckoutHelper: Shipping section shown (different address)');
                }
            };

            // Listen for changes
            useBillingYes.addEventListener('change', updateVisibility);
            useBillingNo.addEventListener('change', updateVisibility);

            // Initial state - check immediately and after delay
            updateVisibility();
            setTimeout(updateVisibility, 200);
            setTimeout(updateVisibility, 500);

            console.log('OnePageCheckoutHelper: Shipping visibility configured');
        };

        // Try immediately and retry after delays
        setupVisibility();
        setTimeout(setupVisibility, 200);
        setTimeout(setupVisibility, 500);
    }

    /**
     * Auto-save forms on ANY change
     */
    setupAutoSave() {
        // Billing: Auto-save on ANY change (not just when complete)
        const billingForm = document.getElementById('co-billing-form');
        if (billingForm) {
            billingForm.addEventListener('change', () => {
                this.scheduleAutoSave('billing', () => {
                    if (window.billing) {
                        console.log('OnePageCheckoutHelper: Auto-saving billing...');
                        billing.save();
                    }
                });
            });

            // Also trigger on blur for text inputs
            billingForm.addEventListener('blur', (e) => {
                if (e.target.matches('input, select, textarea')) {
                    this.scheduleAutoSave('billing', () => {
                        if (window.billing) {
                            console.log('OnePageCheckoutHelper: Auto-saving billing (blur)...');
                            billing.save();
                        }
                    });
                }
            }, true);

            // DON'T trigger initial save on empty form - only if complete
            setTimeout(() => {
                if (window.billing && this.isFormComplete(billingForm)) {
                    console.log('OnePageCheckoutHelper: Initial billing save (form has data)...');
                    billing.save();
                } else {
                    console.log('OnePageCheckoutHelper: Skipping initial billing save (form empty)');
                    // Add placeholder for downstream sections
                    this.addSectionPlaceholders();
                }
            }, 500);
        }

        // Shipping: Auto-save on ANY change
        const shippingForm = document.getElementById('co-shipping-form');
        if (shippingForm) {
            shippingForm.addEventListener('change', () => {
                this.scheduleAutoSave('shipping', () => {
                    if (window.shipping) {
                        console.log('OnePageCheckoutHelper: Auto-saving shipping...');
                        shipping.save();
                    }
                });
            });

            shippingForm.addEventListener('blur', (e) => {
                if (e.target.matches('input, select, textarea')) {
                    this.scheduleAutoSave('shipping', () => {
                        if (window.shipping) {
                            console.log('OnePageCheckoutHelper: Auto-saving shipping (blur)...');
                            shipping.save();
                        }
                    });
                }
            }, true);
        }

        // Shipping method: Auto-save immediately when selected
        document.addEventListener('change', (e) => {
            if (e.target.name === 'shipping_method' && window.shippingMethod) {
                console.log('OnePageCheckoutHelper: Auto-saving shipping method...');
                setTimeout(() => shippingMethod.save(), 100);
            }
        });

        // Payment method: Auto-save immediately when selected
        document.addEventListener('change', (e) => {
            if (e.target.name === 'payment[method]' && window.payment) {
                console.log('OnePageCheckoutHelper: Auto-saving payment method...');
                setTimeout(() => payment.save(), 100);
            }
        });

        console.log('OnePageCheckoutHelper: Auto-save listeners configured');
    }

    /**
     * Schedule auto-save with debounce
     */
    scheduleAutoSave(key, callback) {
        // Clear existing timer
        if (this.autoSaveTimers.has(key)) {
            clearTimeout(this.autoSaveTimers.get(key));
        }

        // Schedule save after 1 second of inactivity
        const timer = setTimeout(callback, 1000);
        this.autoSaveTimers.set(key, timer);
    }

    /**
     * Check if form has all required fields filled
     */
    isFormComplete(form) {
        if (!form) return false;

        const requiredFields = form.querySelectorAll('.required-entry, [required]');

        return Array.from(requiredFields).every(field => {
            // Skip hidden fields
            if (field.offsetParent === null) return true;

            // Check if field has value
            if (field.type === 'checkbox' || field.type === 'radio') {
                const name = field.name;
                return form.querySelector(`[name="${name}"]:checked`) !== null;
            }

            return field.value && field.value.trim() !== '';
        });
    }

    /**
     * Add placeholder content to sections that aren't loaded yet
     */
    addSectionPlaceholders() {
        const placeholders = [
            {
                sectionId: 'checkout-shipping-method-load',
                content: '<div style="color: #999; font-size: 14px; padding: 25px; text-align: center; background: #f9f9f9; border-radius: 4px; margin: 10px 0;">Please complete the billing information above to see available shipping methods.</div>'
            },
            {
                sectionId: 'checkout-payment-method-load',
                content: '<div style="color: #999; font-size: 14px; padding: 25px; text-align: center; background: #f9f9f9; border-radius: 4px; margin: 10px 0;">Please complete the billing information and select a shipping method to see available payment methods.</div>'
            }
        ];

        placeholders.forEach(({ sectionId, content }) => {
            const section = document.getElementById(sectionId);

            if (section) {
                // Check if section has actual content (not just comments or whitespace)
                const tempDiv = document.createElement('div');
                tempDiv.innerHTML = section.innerHTML;

                // Remove comments
                const comments = tempDiv.querySelectorAll('*');
                comments.forEach(node => {
                    if (node.nodeType === 8) { // Comment node
                        node.remove();
                    }
                });

                // Check if there's any actual text or form elements
                const hasRealContent = tempDiv.textContent.trim().length > 0 ||
                                      tempDiv.querySelector('input, select, button, dd, li') !== null;

                console.log(`OnePageCheckoutHelper: Checking ${sectionId}: hasRealContent=${hasRealContent}`);

                if (!hasRealContent) {
                    section.innerHTML = content;
                    console.log(`OnePageCheckoutHelper: Added placeholder to ${sectionId}`);
                } else {
                    console.log(`OnePageCheckoutHelper: ${sectionId} already has content, skipping placeholder`);
                }
            } else {
                console.warn(`OnePageCheckoutHelper: ${sectionId} not found in DOM`);
            }
        });
    }
}

// Auto-initialize when DOM is ready
// Small delay to ensure all sections are rendered
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        setTimeout(() => {
            const helper = new OnePageCheckoutHelper();
            helper.init();
        }, 100);
    });
} else {
    setTimeout(() => {
        const helper = new OnePageCheckoutHelper();
        helper.init();
    }, 100);
}
