<?php

/**
 * Maho
 *
 * @package    Mage_Paypal
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Paypal_Block_Adminhtml_System_Config_Fieldset_Location extends Mage_Adminhtml_Block_System_Config_Form_Fieldset
{
    /**
     * Add conflicts resolution js code to the fieldset
     *
     * @param Varien_Data_Form_Element_Abstract $element
     * @param bool $tooltipsExist Init tooltips observer or not
     * @return string
     */
    #[\Override]
    protected function _getExtraJs($element, $tooltipsExist = false)
    {
        $js = '
            document.addEventListener("DOMContentLoaded", function() {
                document.querySelectorAll(".with-button button.button").forEach(function(configureButton) {
                    togglePaypalSolutionConfigureButton(configureButton, true);
                });
                var paypalConflictsObject = {
                    "isConflict": false,
                    "ecMissed": false,
                    sharePayflowEnabling: function(enabler, isEvent) {
                        var ecPayflowEnabler = document.querySelector(".paypal-ec-payflow-enabler");
                        if (!ecPayflowEnabler) {
                            return;
                        }
                        var ecPayflowScopeElement = adminSystemConfig.getScopeElement(ecPayflowEnabler);

                        if (!enabler.enablerObject.ecPayflow) {
                            if ((!ecPayflowScopeElement || !ecPayflowScopeElement.checked) && isEvent
                                && enabler.value == 1
                            ) {
                                ecPayflowEnabler.value = 0;
                                fireEvent(ecPayflowEnabler, "change");
                            }
                            return;
                        }

                        var enablerScopeElement = adminSystemConfig.getScopeElement(enabler);
                        if (enablerScopeElement && ecPayflowScopeElement
                            && enablerScopeElement.checked != ecPayflowScopeElement.checked
                            && (isEvent || ecPayflowScopeElement.checked)
                        ) {
                            ecPayflowScopeElement.click();
                        }

                        var ecEnabler = document.querySelector(".paypal-ec-enabler");
                        if (ecPayflowEnabler.value != enabler.value && (isEvent || enabler.value == 1)) {
                            ecPayflowEnabler.value = enabler.value;
                            fireEvent(ecPayflowEnabler, "change");
                            if (ecPayflowEnabler.value == 1) {
                                if (ecEnabler) {
                                    var ecEnablerScopeElement = adminSystemConfig.getScopeElement(ecEnabler);
                                    ecEnabler.value = 1;
                                    if (ecEnablerScopeElement && ecEnablerScopeElement.checked) {
                                        paypalConflictsObject.checklessEventAction(ecEnablerScopeElement, false);
                                    }
                                    paypalConflictsObject.checklessEventAction(ecEnabler, true);
                                }
                            }
                        }
                        if (!isEvent && ecPayflowEnabler.value == 1 && ecEnabler) {
                            var ecSolution = document.querySelector(".pp-method-express");
                            if (ecSolution && !ecSolution.classList.contains("enabled")) {
                                ecSolution.classList.add("enabled");
                            }
                        }
                    },
                    onChangeEnabler: function(event) {
                        paypalConflictsObject.checkPaymentConflicts(event.target, "change");
                    },
                    onClickEnablerScope: function(event) {
                        var tr = adminSystemConfig.getUpTr(event.target);
                        var enabler = tr.querySelector(".paypal-enabler");
                        paypalConflictsObject.checkPaymentConflicts(enabler, "click");
                    },
                    getSharedElements: function(element) {
                        var sharedElements = [];
                        adminSystemConfig.mapClasses(element, true, function(elementClassName) {
                            document.querySelectorAll("." + elementClassName).forEach(function(sharedElement) {
                                if (sharedElements.indexOf(sharedElement) == -1) {
                                    sharedElements.push(sharedElement);
                                }
                            });
                        });
                        if (sharedElements.length == 0) {
                            sharedElements.push(element);
                        }
                        return sharedElements;
                    },
                    checklessEventAction: function(element, isChange) {
                        var action = isChange ? "change" : "click";
                        var handler = isChange
                            ? paypalConflictsObject.onChangeEnabler
                            : paypalConflictsObject.onClickEnablerScope;
                        paypalConflictsObject.getSharedElements(element).forEach(function(sharedElement) {
                            sharedElement.removeEventListener(action, handler);
                            if (isChange) {
                                sharedElement.value = element.value;
                                if (sharedElement.requiresObj) {
                                    sharedElement.requiresObj.indicateEnabled();
                                }
                            }
                        });
                        if (isChange) {
                            fireEvent(element, "change");
                        } else {
                            element.click();
                        }
                        paypalConflictsObject.getSharedElements(element).forEach(function(sharedElement) {
                            sharedElement.addEventListener(action, handler);
                        });
                    },
                    ecCheckAvailability: function() {
                        var ecButton = document.querySelector(".pp-method-express button.button");
                        if (!ecButton) {
                            return;
                        }
                        var couldBeConfigured = true;
                        document.querySelectorAll(".paypal-enabler").forEach(function(enabler) {
                            if (enabler.enablerObject.ecEnabler || enabler.enablerObject.ecConflicts
                                || enabler.enablerObject.ecSeparate
                            ) {
                                return;
                            }
                            if (enabler.value == 1) {
                                couldBeConfigured = false;
                            }
                        });
                        if (couldBeConfigured) {
                            togglePaypalSolutionConfigureButton(ecButton, true);
                        } else {
                            togglePaypalSolutionConfigureButton(ecButton, false);
                        }
                    },
                    // type could be "initial", "change", "click"
                    checkPaymentConflicts: function(enabler, type) {
                        var isEvent = (type != "initial");
                        var ecEnabler = document.querySelector(".paypal-ec-enabler");

                        if (enabler.value == 0) {
                            if (!enabler.enablerObject.ecIndependent && type == "change") {
                                if (ecEnabler && ecEnabler.value == 1) {
                                    var ecEnablerScopeElement = adminSystemConfig.getScopeElement(ecEnabler);
                                    if (!ecEnablerScopeElement || !ecEnablerScopeElement.checked) {
                                        ecEnabler.value = 0;
                                        paypalConflictsObject.checklessEventAction(ecEnabler, true);
                                    }
                                }
                            }
                            paypalConflictsObject.ecCheckAvailability();
                            paypalConflictsObject.sharePayflowEnabling(enabler, isEvent);
                            return;
                        }

                        var confirmationApproved = isEvent;
                        var confirmationShowed = false;
                        // check other solutions
                        document.querySelectorAll(".paypal-enabler").forEach(function(anotherEnabler) {
                            var anotherEnablerScopeElement = adminSystemConfig.getScopeElement(anotherEnabler);
                            if (!confirmationApproved && isEvent || anotherEnabler == enabler
                                || anotherEnabler.value == 0
                                && (!anotherEnablerScopeElement || !anotherEnablerScopeElement.checked)
                            ) {
                                return;
                            }
                            var conflict = enabler.enablerObject.ecConflicts && anotherEnabler.enablerObject.ecEnabler
                                || enabler.enablerObject.ecEnabler && anotherEnabler.enablerObject.ecConflicts
                                || !enabler.enablerObject.ecIndependent && anotherEnabler.enablerObject.ecConflicts
                                || !enabler.enablerObject.ecEnabler && !anotherEnabler.enablerObject.ecEnabler;

                            if (conflict && !confirmationShowed && anotherEnabler.value == 1) {
                                if (isEvent) {
                                    confirmationApproved = confirm(\'' . $this->helper('core')->jsQuoteEscape($this->__('There is already another PayPal solution enabled. Enable this solution instead?')) . '\');
                                } else {
                                    paypalConflictsObject.isConflict = true;
                                }
                                confirmationShowed = true;
                            }
                            if (conflict && confirmationApproved) {
                                anotherEnabler.value = 0;
                                if (anotherEnablerScopeElement && anotherEnablerScopeElement.checked && isEvent) {
                                    paypalConflictsObject.checklessEventAction(anotherEnablerScopeElement, false);
                                }
                                paypalConflictsObject.checklessEventAction(anotherEnabler, true);
                            }
                        });

                        if (!enabler.enablerObject.ecIndependent) {
                            if (!isEvent && (!ecEnabler || ecEnabler.value == 0)) {
                                if (!enabler.enablerObject.ecPayflow) {
                                    paypalConflictsObject.ecMissed = true;
                                }
                            } else if (isEvent && ecEnabler && confirmationApproved) {
                                var ecEnablerScopeElement = adminSystemConfig.getScopeElement(ecEnabler);
                                if (ecEnablerScopeElement && ecEnablerScopeElement.checked) {
                                    paypalConflictsObject.checklessEventAction(ecEnablerScopeElement, false);
                                }
                                if (ecEnabler.value == 0) {
                                    ecEnabler.value = 1;
                                    paypalConflictsObject.checklessEventAction(ecEnabler, true);
                                }
                            }
                        }

                        if (!confirmationApproved && isEvent) {
                            enabler.value = 0;
                            paypalConflictsObject.checklessEventAction(enabler, true);
                        }
                        paypalConflictsObject.ecCheckAvailability();
                        paypalConflictsObject.sharePayflowEnabling(enabler, isEvent);
                    },

                    handleBmlEnabler: function(event) {
                        required = event.target;
                        var bml = required.bmlEnabler;
                        if (required.value == "1") {
                            bml.value = "1";
                        }
                        paypalConflictsObject.toggleBmlEnabler(required);
                    },

                    toggleBmlEnabler: function(required) {
                        var bml = $(required).bmlEnabler;
                        if (!bml) {
                            return;
                        }
                        if (required.value != "1") {
                            bml.value = "0";
                            bml.disabled = true;
                        }
                        bml.requiresObj.indicateEnabled();
                    }
                };

                // fill enablers with conflict data
                document.querySelectorAll(".paypal-enabler").forEach(function(enablerElement) {
                    var enablerObj = {
                        ecIndependent: false,
                        ecConflicts: false,
                        ecEnabler: false,
                        ecSeparate: false,
                        ecPayflow: false
                    };
                    Array.from(enablerElement.classList).forEach(function(className) {
                        switch (className) {
                            case "paypal-ec-conflicts":
                                enablerObj.ecConflicts = true;
                            case "paypal-ec-independent":
                                enablerObj.ecIndependent = true;
                                break;
                            case "paypal-ec-enabler":
                                enablerObj.ecEnabler = true;
                                enablerObj.ecIndependent = true;
                                break;
                            case "paypal-ec-separate":
                                enablerObj.ecSeparate = true;
                                enablerObj.ecIndependent = true;
                                break;
                            case "paypal-ec-pe":
                                enablerObj.ecPayflow = true;
                                break;
                        }
                    });
                    enablerElement.enablerObject = enablerObj;

                    enablerElement.addEventListener("change", paypalConflictsObject.onChangeEnabler);
                    var enablerScopeElement = adminSystemConfig.getScopeElement(enablerElement);
                    if (enablerScopeElement) {
                        enablerScopeElement.addEventListener("click", paypalConflictsObject.onClickEnablerScope);
                    }
                });

                // initially uncheck payflow
                var ecPayflowEnabler = document.querySelector(".paypal-ec-payflow-enabler");
                if (ecPayflowEnabler) {
                    if (ecPayflowEnabler.value == 1) {
                        ecPayflowEnabler.value = 0;
                        fireEvent(ecPayflowEnabler, "change");
                    }

                    var ecPayflowScopeElement = adminSystemConfig.getScopeElement(ecPayflowEnabler);
                    if (ecPayflowScopeElement && !ecPayflowScopeElement.checked) {
                        ecPayflowScopeElement.click();
                    }
                }

                document.querySelectorAll(".paypal-bml").forEach(function(bmlEnabler) {
                    Array.from(bmlEnabler.classList).forEach(function(className) {
                        if (className.indexOf("requires-") !== -1) {
                            var required = document.getElementById(className.replace("requires-", ""));
                            required.bmlEnabler = bmlEnabler;
                            required.addEventListener("change", paypalConflictsObject.handleBmlEnabler);
                        }
                    });
                });

                document.querySelectorAll(".paypal-enabler").forEach(function(enablerElement) {
                    paypalConflictsObject.checkPaymentConflicts(enablerElement, "initial");
                    paypalConflictsObject.toggleBmlEnabler(enablerElement);
                });
                if (paypalConflictsObject.isConflict || paypalConflictsObject.ecMissed) {
                    var notification = \'' . $this->helper('core')->jsQuoteEscape($this->__('The following error(s) occured:')) . '\';
                    if (paypalConflictsObject.isConflict) {
                        notification += "\\n  " + \'' . $this->helper('core')->jsQuoteEscape($this->__('Some PayPal solutions conflict.')) . '\';
                    }
                    if (paypalConflictsObject.ecMissed) {
                        notification += "\\n  " + \'' . $this->helper('core')->jsQuoteEscape($this->__('PayPal Express Checkout is not enabled.')) . '\';
                    }
                    notification += "\\n" + \'' . $this->helper('core')->jsQuoteEscape($this->__('Please re-enable the previously enabled payment solutions.')) . '\';
                    setTimeout(function() {
                        alert(notification);
                    }, 1);
                }

                document.querySelectorAll(".requires").forEach(function(dependent) {
                    if (dependent.classList.contains("paypal-ec-enabler")) {
                        dependent.requiresObj.callback = function(required) {
                            if (required.classList.contains("paypal-enabler") && required.value == 0) {
                                dependent.disabled = true;
                            }
                        }
                        dependent.requiresObj.requires.forEach(function(required) {
                            dependent.requiresObj.callback(required);
                        });
                    }
                });

                var originalFormValidation = configForm.validator.options.onFormValidate;
                configForm.validator.options.onFormValidate = function(result, form) {
                    originalFormValidation(result, form);
                    if (result) {
                        var ecPayflowEnabler = document.querySelector(".paypal-ec-payflow-enabler");
                        if (!ecPayflowEnabler) {
                            return;
                        }
                        var ecPayflowScopeElement = adminSystemConfig.getScopeElement(ecPayflowEnabler);
                        if ((!ecPayflowScopeElement || !ecPayflowScopeElement.checked)
                            && ecPayflowEnabler.value == 1
                        ) {
                            document.querySelectorAll(".paypal-ec-enabler").forEach(function(ecEnabler) {
                                ecEnabler.value = 0;
                            });
                        }
                    }
                }
            });
        ';

        /** @var Mage_Adminhtml_Helper_Js $helper */
        $helper = $this->helper('adminhtml/js');
        return parent::_getExtraJs($element, $tooltipsExist) . $helper->getScript($js);
    }
}
