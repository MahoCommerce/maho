# One-Page Checkout Implementation Plan

## Overview

This document provides step-by-step instructions to implement a true one-page checkout for Maho with a clean hybrid approach using minimal core modifications.

**Current Status:** Standard accordion-based checkout
**Target:** Clean one-page checkout (~540 lines total: 230 JS + 280 CSS + 30 core mods)

---

## Phase 1: Core Modifications (Minimal Changes)

### Step 1.1: Modify accordion.js

**File:** `/Users/fab/Projects/maho/public/js/varien/accordion.js`

**Location:** In the `Accordion` class constructor (around line 5-15)

**Add this property:**
```javascript
class Accordion {
    constructor(elem, clickableEntity, checkAllow) {
        this.container = document.getElementById(elem);
        this.checkAllow = checkAllow || false;
        this.disallowAccessToNextSections = false;

        // NEW: Flag to enable one-page mode (all sections visible)
        this.onePageMode = false;

        // ... rest of existing code
    }
```

**Location:** In the `openSection()` method (around line 27-54)

**Add this code at the beginning of the method:**
```javascript
openSection(section) {
    if (typeof section == 'string') {
        section = document.getElementById(section);
    }

    if (this.checkAllow && section && !section.classList.contains('allow')) {
        return;
    }

    // NEW: In one-page mode, just mark as active without hiding others
    if (this.onePageMode) {
        if (section) {
            section.classList.add('active', 'allow');
            const contents = section.querySelector('.a-item');
            if (contents) {
                contents.style.display = 'block';
            }
            this.currentSection = section.id;
        }
        return; // Don't execute accordion logic
    }

    // Existing accordion logic continues below...
    if (section.id != this.currentSection) {
        this.closeExistingSection();
        // ... rest of existing code
    }
}
```

**Total lines added:** ~20 lines

---

### Step 1.2: Modify opcheckout.js

**File:** `/Users/fab/Projects/maho/public/js/varien/opcheckout.js`

**Location 1:** In the `Checkout` class constructor (around line 11-37)

**Add this property after line 26:**
```javascript
class Checkout {
    constructor(accordion, urls) {
        this.accordion = accordion;
        this.progressUrl = urls.progress;
        this.reviewUrl = urls.review;
        this.saveMethodUrl = urls.saveMethod;
        this.failureUrl = urls.failure;
        this.billingForm = false;
        this.shippingForm = false;
        this.syncBillingShipping = false;
        this.method = '';
        this.payment = '';
        this.loadWaiting = false;
        this.steps = ['login', 'billing', 'shipping', 'shipping_method', 'payment', 'review'];
        this.currentStep = 'billing';

        // NEW: One-page checkout mode flag
        this.onePageMode = false;

        // ... rest of existing code
    }
```

**Location 2:** Add new method after the constructor (around line 38)

```javascript
    /**
     * Enable one-page checkout mode
     * - All sections visible simultaneously
     * - No accordion collapse behavior
     */
    enableOnePageMode() {
        this.onePageMode = true;
        this.accordion.onePageMode = true;

        // Mark all sections as allowed
        this.accordion.sections.forEach(section => {
            section.classList.add('allow');
        });

        // Open all sections (won't hide others due to onePageMode flag)
        this.accordion.sections.forEach(section => {
            this.accordion.openSection(section);
        });

        console.log('One-page checkout mode enabled');
    }
```

**Location 3:** Modify `gotoSection()` method (around line 138-155)

**Replace the existing method with:**
```javascript
    gotoSection(section, reloadProgressBlock) {
        // Track progression past login
        if ((this.currentStep === 'login' || this.currentStep === 'billing') && section === 'billing') {
            document.body.classList.add('opc-has-progressed-from-login');
        }

        if (reloadProgressBlock) {
            this.reloadProgressBlock(this.currentStep);
        }

        this.currentStep = section;
        const sectionElement = document.getElementById(`opc-${section}`);
        sectionElement.classList.add('allow');

        // NEW: In one-page mode, just scroll to section instead of opening accordion
        if (this.onePageMode) {
            sectionElement.scrollIntoView({ behavior: 'smooth', block: 'start' });
        } else {
            this.accordion.openSection(`opc-${section}`);
        }

        if (!reloadProgressBlock) {
            this.resetPreviousSteps();
        }

        const checkoutSteps = document.getElementById('checkoutSteps');
        if (checkoutSteps) checkoutSteps.scrollIntoView();
    }
```

**Total lines added:** ~30 lines

---

## Phase 2: Create One-Page Helper

### Step 2.1: Create the helper file

**File:** `/Users/fab/Projects/maho/public/js/mage/checkout/onepage-helper.js` (NEW)

```javascript
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

        // Enable one-page mode in core checkout
        if (window.checkout && typeof checkout.enableOnePageMode === 'function') {
            checkout.enableOnePageMode();
        } else {
            console.error('OnePageCheckoutHelper: checkout.enableOnePageMode() not found');
        }

        // Setup features
        this.setupGuestCheckout();
        this.setupShippingVisibility();
        this.setupAutoSave();
        this.hideLoginSection();

        this.initialized = true;
        console.log('OnePageCheckoutHelper: Initialized successfully');
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

        // Auto-select guest
        if (!guestRadio.checked && !registerRadio?.checked) {
            guestRadio.checked = true;
        }

        // Auto-save method and proceed to billing
        if (guestRadio.checked && window.checkout) {
            setTimeout(() => {
                // Save guest method
                if (typeof checkout.setMethod === 'function') {
                    checkout.setMethod();
                }
            }, 100);
        }

        // Add "Already registered?" link to billing section
        this.addLoginLink();

        console.log('OnePageCheckoutHelper: Guest checkout configured');
    }

    /**
     * Add login link to billing section header
     */
    addLoginLink() {
        const billingSection = document.getElementById('opc-billing');
        if (!billingSection) return;

        const loginLink = document.createElement('div');
        loginLink.className = 'onepage-login-link';
        loginLink.innerHTML = `
            <p style="margin: -10px 0 15px 0; padding: 10px; background: #f9f9f9; border-left: 3px solid #555;">
                Already have an account?
                <a href="#" id="onepage-show-login" style="font-weight: bold; text-decoration: underline;">Login here</a>
            </p>
        `;

        const stepTitle = billingSection.querySelector('.step-title');
        if (stepTitle && stepTitle.nextSibling) {
            stepTitle.parentNode.insertBefore(loginLink, stepTitle.nextSibling);
        }

        // Show login section on click
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
        const useBillingYes = document.getElementById('billing:use_for_shipping_yes');
        const useBillingNo = document.getElementById('billing:use_for_shipping_no');
        const shippingSection = document.getElementById('opc-shipping');

        if (!shippingSection) return;

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
        useBillingYes?.addEventListener('change', updateVisibility);
        useBillingNo?.addEventListener('change', updateVisibility);

        // Initial state
        updateVisibility();
    }

    /**
     * Auto-save forms when complete
     */
    setupAutoSave() {
        // Billing: Auto-save when all required fields filled
        const billingForm = document.getElementById('co-billing-form');
        if (billingForm) {
            billingForm.addEventListener('change', () => {
                this.scheduleAutoSave('billing', () => {
                    if (window.billing && this.isFormComplete(billingForm)) {
                        console.log('OnePageCheckoutHelper: Auto-saving billing...');
                        billing.save();
                    }
                });
            });
        }

        // Shipping: Auto-save when all required fields filled
        const shippingForm = document.getElementById('co-shipping-form');
        if (shippingForm) {
            shippingForm.addEventListener('change', () => {
                this.scheduleAutoSave('shipping', () => {
                    if (window.shipping && this.isFormComplete(shippingForm)) {
                        console.log('OnePageCheckoutHelper: Auto-saving shipping...');
                        shipping.save();
                    }
                });
            });
        }

        // Shipping method: Auto-save when selected
        document.addEventListener('change', (e) => {
            if (e.target.name === 'shipping_method' && window.shippingMethod) {
                setTimeout(() => {
                    console.log('OnePageCheckoutHelper: Auto-saving shipping method...');
                    shippingMethod.save();
                }, 300);
            }
        });

        // Payment method: Auto-save when selected
        document.addEventListener('change', (e) => {
            if (e.target.name === 'payment[method]' && window.payment) {
                setTimeout(() => {
                    console.log('OnePageCheckoutHelper: Auto-saving payment method...');
                    payment.save();
                }, 300);
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
}

// Auto-initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        const helper = new OnePageCheckoutHelper();
        helper.init();
    });
} else {
    const helper = new OnePageCheckoutHelper();
    helper.init();
}
```

**Total lines:** ~230 lines (with comments and whitespace)

---

## Phase 3: CSS Styling

### Step 3.1: Create CSS file

**File:** `/Users/fab/Projects/maho/public/skin/frontend/base/default/css/onepage-checkout.css` (NEW)

```css
/**
 * Maho One-Page Checkout Styles
 *
 * @package     skin_frontend_base_default
 * @copyright   Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license     https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/* ============================================ *
 * One-Page Checkout Layout
 * ============================================ */

/* Flatten accordion - show all sections */
.opc .section {
    display: block !important;
    margin-bottom: 30px;
    border: 1px solid #ddd;
    background: #fff;
    padding: 20px;
    border-radius: 4px;
}

.opc .section .step-title {
    border-bottom: 2px solid #e0e0e0;
    padding-bottom: 10px;
    margin-bottom: 20px;
    cursor: default;
}

.opc .section .step-title h2 {
    font-size: 18px;
    font-weight: 600;
    color: #333;
    margin: 0;
}

/* Remove "Edit" link from step titles */
.opc .section .step-title a {
    display: none;
}

/* Show all step content */
.opc .section .step,
.opc .section .a-item {
    display: block !important;
}

/* Hide collapsed section styles */
.opc .section .step {
    opacity: 1 !important;
    height: auto !important;
    overflow: visible !important;
}

/* ============================================ *
 * Section Visibility
 * ============================================ */

/* Login section hidden by default (shown on demand via JS) */
#opc-login {
    display: none;
}

/* Hide shipping section when using billing address (controlled by JS) */
#opc-shipping.hidden {
    display: none !important;
}

/* ============================================ *
 * Buttons
 * ============================================ */

/* Hide intermediate "Continue" buttons */
#billing-buttons-container,
#shipping-buttons-container,
#shipping-method-buttons-container,
#payment-buttons-container {
    display: none;
}

/* Show and style final "Place Order" button */
#review-buttons-container {
    display: block !important;
    margin-top: 30px;
    padding: 20px;
    background: #f9f9f9;
    border: 2px solid #333;
    border-radius: 4px;
    text-align: center;
}

#review-buttons-container .btn-checkout,
#review-buttons-container button.button {
    font-size: 18px;
    padding: 15px 40px;
    min-width: 250px;
    background: #5cb85c;
    color: white;
    border: none;
    cursor: pointer;
    font-weight: 600;
    text-transform: uppercase;
}

#review-buttons-container .btn-checkout:hover,
#review-buttons-container button.button:hover {
    background: #4cae4c;
}

/* ============================================ *
 * 2-Column Layout: Form | Order Summary
 * ============================================ */

/* Desktop: Form (left) + Summary (right) */
@media only screen and (min-width: 980px) {
    .checkout-onepage-index .main-container {
        max-width: 1200px;
        margin: 0 auto;
    }

    .checkout-onepage-index .col-main {
        float: left;
        width: 65%;
        padding-right: 3%;
    }

    .checkout-onepage-index .col-right.sidebar {
        display: block !important;
        float: right;
        width: 32%;
    }

    /* Move review section to sidebar */
    #opc-review {
        position: sticky;
        top: 20px;
    }
}

/* Tablet: Smaller margins */
@media only screen and (min-width: 768px) and (max-width: 979px) {
    .checkout-onepage-index .col-main {
        float: left;
        width: 60%;
        padding-right: 2%;
    }

    .checkout-onepage-index .col-right.sidebar {
        display: block !important;
        float: right;
        width: 38%;
    }
}

/* Mobile: Stack sections */
@media only screen and (max-width: 767px) {
    .checkout-onepage-index .col-main,
    .checkout-onepage-index .col-right.sidebar {
        float: none;
        width: 100%;
        padding: 0;
    }

    .opc .section {
        padding: 15px;
    }

    #review-buttons-container .btn-checkout,
    #review-buttons-container button.button {
        font-size: 16px;
        padding: 12px 30px;
        min-width: 200px;
    }
}

/* ============================================ *
 * Visual Polish
 * ============================================ */

/* Add step numbers */
.opc .section .step-title h2::before {
    content: attr(data-step-number) ". ";
    font-weight: 700;
    color: #555;
}

/* Completed sections get checkmark */
.opc .section.saved .step-title h2::before {
    content: "✓ ";
    color: #5cb85c;
}

/* Active section highlight */
.opc .section.active {
    border-color: #5cb85c;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

/* Form field spacing */
.opc .form-list li {
    margin-bottom: 15px;
}

/* Clear floats */
.opc .form-list::after {
    content: "";
    display: table;
    clear: both;
}

/* Login link styling */
.onepage-login-link {
    margin: 0 -20px 20px -20px;
}

.onepage-login-link p {
    margin: 0;
}

.onepage-login-link a {
    color: #333;
    text-decoration: underline;
}

.onepage-login-link a:hover {
    color: #000;
}

/* ============================================ *
 * Loading States
 * ============================================ */

.opc .section.processing {
    opacity: 0.6;
    pointer-events: none;
}

.opc .section.processing .step-title h2::after {
    content: " ...";
    animation: dotdotdot 1.5s infinite;
}

@keyframes dotdotdot {
    0%, 20% { content: " ."; }
    40% { content: " .."; }
    60%, 100% { content: " ..."; }
}
```

**Total lines:** ~280 lines

---

## Phase 4: Template Integration

### Step 4.1: Modify main checkout template

**File:** `/Users/fab/Projects/maho/app/design/frontend/base/default/template/checkout/onepage.phtml`

**At the top of the file (after the PHP opening tag):**

```php
<?php
/**
 * Maho
 *
 * @package     base_default
 * @copyright   Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright   Copyright (c) 2022-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright   Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license     https://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

/** @var Mage_Checkout_Block_Onepage $this */

// NEW: Enable one-page mode styling
echo '<script>document.body.classList.add("checkout-onepage-index");</script>';
?>
```

**This adds the body class needed for CSS targeting.**

### Step 4.2: Include helper JavaScript

**File:** `/Users/fab/Projects/maho/app/design/frontend/base/default/layout/checkout.xml`

**Find the `<checkout_onepage_index>` block and add:**

```xml
<checkout_onepage_index>
    <reference name="head">
        <!-- Existing CSS/JS references -->

        <!-- NEW: One-page checkout helper -->
        <action method="addJs"><script>mage/checkout/onepage-helper.js</script></action>
        <action method="addCss"><stylesheet>css/onepage-checkout.css</stylesheet></action>
    </reference>

    <!-- Rest of existing layout... -->
</checkout_onepage_index>
```

**Total lines modified:** ~2 lines in template, ~2 lines in layout XML

---

## Phase 5: Testing & Validation

### Step 5.1: Manual testing checklist

Test these scenarios in order:

1. **Guest Checkout (Default Flow)**
   - [ ] Visit checkout page
   - [ ] Verify all sections visible
   - [ ] Verify "Guest" is auto-selected
   - [ ] Verify "Login" link appears in billing section
   - [ ] Fill billing address → should auto-save and load shipping methods
   - [ ] Select shipping method → should auto-save and load payment methods
   - [ ] Select payment method → should auto-save and load review
   - [ ] Place order → should complete successfully

2. **Registered Customer Login**
   - [ ] Click "Login here" link
   - [ ] Login section appears and scrolls into view
   - [ ] Login with credentials
   - [ ] Redirects to billing (or skips to payment if address exists)
   - [ ] Complete checkout

3. **"Ship to This Address" Toggle**
   - [ ] On billing section, select "Ship to this address"
   - [ ] Shipping section should hide
   - [ ] Select "Ship to different address"
   - [ ] Shipping section should show

4. **Virtual Product (No Shipping)**
   - [ ] Add virtual product to cart
   - [ ] Visit checkout
   - [ ] Verify shipping section and shipping_method section are hidden
   - [ ] Payment and review sections work normally

5. **Validation Errors**
   - [ ] Leave required field empty
   - [ ] Try to place order
   - [ ] Error message appears in correct section
   - [ ] Section scrolls into view

6. **Responsive Design**
   - [ ] Test on desktop (1200px+)
   - [ ] Test on tablet (768px-979px)
   - [ ] Test on mobile (< 768px)
   - [ ] Verify 2-column layout on desktop
   - [ ] Verify stacked layout on mobile

7. **Browser Compatibility**
   - [ ] Chrome
   - [ ] Firefox
   - [ ] Safari
   - [ ] Edge

### Step 5.2: Automated testing

**Create a simple test script:**

```bash
# Test that files exist
test -f public/js/mage/checkout/onepage-helper.js && echo "✓ Helper JS exists"
test -f public/skin/frontend/base/default/css/onepage-checkout.css && echo "✓ CSS exists"


# Check for required modifications
grep -q "this.onePageMode = false" public/js/varien/opcheckout.js && echo "✓ opcheckout.js modified"
grep -q "this.onePageMode = false" public/js/varien/accordion.js && echo "✓ accordion.js modified"

echo "All file checks passed!"
```

---

## Phase 6: Optimization & Polish

### Step 6.1: Performance

- [ ] Verify no unnecessary AJAX calls
- [ ] Check that auto-save debounce works (1 second delay)
- [ ] Ensure `checkout.loadWaiting` prevents race conditions
- [ ] Test on slow network (throttle to 3G)

### Step 6.2: Accessibility

- [ ] All sections keyboard navigable
- [ ] Form labels properly associated
- [ ] Error messages have ARIA attributes
- [ ] Focus management when sections show/hide

### Step 6.3: UX Polish

- [ ] Add loading spinners during AJAX calls
- [ ] Smooth scroll animations
- [ ] Success checkmarks when sections complete
- [ ] Clear error messaging

---

## Rollback Plan

If implementation fails, rollback is simple:

1. **Delete new files:**
   ```bash
   rm public/js/mage/checkout/onepage-helper.js
   rm public/skin/frontend/base/default/css/onepage-checkout.css
   ```

2. **Revert core modifications:**
   ```bash
   git checkout public/js/varien/opcheckout.js
   git checkout public/js/varien/accordion.js
   ```

3. **Remove template changes:**
   - Remove body class from `onepage.phtml`
   - Remove JS/CSS references from `checkout.xml`

4. **Flush cache:**
   ```bash
   ./maho cache:flush
   ```

---

## Troubleshooting

### Issue: Sections not all visible

**Diagnosis:**
- Check browser console for errors
- Verify `checkout.enableOnePageMode()` is called
- Verify CSS file is loaded

**Fix:**
- Inspect `checkout.onePageMode` and `accordion.onePageMode` flags in console
- Check that CSS is not cached

### Issue: Auto-save not working

**Diagnosis:**
- Check console for "Auto-saving..." messages
- Verify form fields have `required-entry` class
- Check `checkout.loadWaiting` state

**Fix:**
- Review `isFormComplete()` logic
- Ensure `billing`, `shipping`, etc. global objects exist
- Check for validation errors blocking save

### Issue: Shipping section won't hide

**Diagnosis:**
- Check if radio buttons exist: `document.getElementById('billing:use_for_shipping_yes')`
- Verify `setupShippingVisibility()` is called

**Fix:**
- Check template has the radio buttons rendered
- Verify event listeners are attached
- Manually test: `document.getElementById('opc-shipping').style.display = 'none'`

### Issue: Payment/review sections empty

**Diagnosis:**
- Check Network tab for AJAX responses
- Verify `update_section` data in response
- Check if `checkout.setStepResponse()` processes it

**Fix:**
- Ensure backend returns correct `update_section` data
- Verify no JavaScript errors during response handling
- Check that original `nextStep()` methods are called

---

## Success Criteria

Implementation is successful when:

✅ All sections visible on page load
✅ Guest checkout auto-selected
✅ Login link works
✅ Shipping section hides when "Ship to this address" selected
✅ Billing auto-save loads shipping methods
✅ Shipping method auto-save loads payment methods
✅ Payment method auto-save loads review
✅ Place order completes successfully
✅ No JavaScript console errors
✅ 2-column layout on desktop
✅ Responsive on mobile
✅ No regression in standard checkout flow

---

## Maintenance Notes

### When upgrading Maho core:

1. **Check for conflicts in:**
   - `public/js/varien/opcheckout.js` (has our ~30 line modifications)
   - `public/js/varien/accordion.js` (has our ~20 line modifications)

2. **Merge strategy:**
   - If core changes, manually re-apply our modifications
   - Look for comments starting with `// NEW:` to identify our changes

3. **Test after upgrade:**
   - Run full testing checklist
   - Verify one-page mode still works
   - Check for new checkout features that need integration

### Adding new payment methods:

- No changes needed - auto-save will work automatically
- Just ensure payment method renders in `#checkout-payment-method-load`

### Adding new shipping methods:

- No changes needed - auto-save will work automatically
- Just ensure shipping method renders in `#checkout-shipping-method-load`

---

## File Summary

### Files to CREATE:
1. `/Users/fab/Projects/maho/public/js/mage/checkout/onepage-helper.js` (~230 lines)
2. `/Users/fab/Projects/maho/public/skin/frontend/base/default/css/onepage-checkout.css` (~280 lines)

### Files to MODIFY:
1. `/Users/fab/Projects/maho/public/js/varien/accordion.js` (+20 lines)
2. `/Users/fab/Projects/maho/public/js/varien/opcheckout.js` (+30 lines)
3. `/Users/fab/Projects/maho/app/design/frontend/base/default/template/checkout/onepage.phtml` (+1 line)
4. `/Users/fab/Projects/maho/app/design/frontend/base/default/layout/checkout.xml` (+2 lines)

### Total Impact:
- **New code:** ~510 lines
- **Modified code:** ~53 lines

---

## Timeline Estimate

- **Phase 1 (Core mods):** 30 minutes
- **Phase 2 (Helper):** 45 minutes
- **Phase 3 (CSS):** 30 minutes
- **Phase 4 (Templates):** 15 minutes
- **Phase 5 (Testing):** 60 minutes
- **Phase 6 (Polish):** 30 minutes

**Total: ~3.5 hours** for complete implementation and testing

---

## Next Steps

When ready to implement:

1. Start with Phase 1 (Core modifications)
2. Test after each phase
3. Commit after each successful phase
4. Full testing in Phase 5
5. Polish and optimize in Phase 6

Good luck! 🚀
