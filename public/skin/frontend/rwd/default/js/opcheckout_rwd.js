/**
 * Maho
 *
 * @category   design
 * @package    rwd_default
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */
Checkout.prototype.gotoSection = function (section, reloadProgressBlock) {
    // Adds class so that the page can be styled to only show the "Checkout Method" step
    if ((this.currentStep === 'login' || this.currentStep === 'billing') && section === 'billing') {
        document.body.classList.add('opc-has-progressed-from-login');
    }

    if (reloadProgressBlock) {
        this.reloadProgressBlock(this.currentStep);
    }

    this.currentStep = section;
    var sectionElement = document.getElementById('opc-' + section);
    sectionElement.classList.add('allow');
    this.accordion.openSection('opc-' + section);

    // Scroll viewport to top of checkout steps for smaller viewports
    if (window.matchMedia('(max-width: ' + bp.xsmall + 'px)').matches) {
        const checkoutSteps = document.getElementById('checkoutSteps');
        const topPosition = checkoutSteps.getBoundingClientRect().top + window.scrollY;
        window.scrollTo({ top: topPosition, behavior: 'smooth' });
    }

    if (!reloadProgressBlock) {
        this.resetPreviousSteps();
    }
};
