<?php

/**
 * Maho
 *
 * @package    Mage_Paypal
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Paypal_Block_Adminhtml_System_Config_Fieldset_Hint extends Mage_Adminhtml_Block_Abstract implements \Maho\Data\Form\Element\Renderer\RendererInterface
{
    protected $_template = 'paypal/system/config/fieldset/hint.phtml';

    /**
     * Render fieldset html
     *
     * @return string
     */
    #[\Override]
    public function render(\Maho\Data\Form\Element\AbstractElement $element)
    {
        $elementOriginalData = $element->getOriginalData();
        if (isset($elementOriginalData['help_link'])) {
            $this->setHelpLink($elementOriginalData['help_link']);
        }
        $js = '
            paypalToggleSolution = function(id, url) {
                var doScroll = false;
                Fieldset.toggleCollapse(id, url);
                if (this.classList.contains("open")) {
                    document.querySelectorAll(".with-button button.button").forEach(function(anotherButton) {
                        if (anotherButton != this && anotherButton.classList.contains("open")) {
                            anotherButton.click();
                            doScroll = true;
                        }
                    }.bind(this));
                }
                if (doScroll) {
                    var rect = this.getBoundingClientRect();
                    var scrollTop = window.pageYOffset + rect.top - 45;
                    window.scrollTo(0, scrollTop);
                }
            }

            togglePaypalSolutionConfigureButton = function(button, enable) {
                button.disabled = !enable;
                if (button.classList.contains("disabled") && enable) {
                    button.classList.remove("disabled");
                } else if (!button.classList.contains("disabled") && !enable) {
                    button.classList.add("disabled");
                }
            }

            // check store-view disabling Express Checkout
            document.addEventListener("DOMContentLoaded", function() {
                var ecButton = document.querySelector(".pp-method-express button.button");
                var ecEnabler = document.querySelector(".paypal-ec-enabler");
                if (!ecButton || ecEnabler) {
                    return;
                }
                document.querySelectorAll(".with-button button.button").forEach(function(configureButton) {
                    if (configureButton != ecButton && !configureButton.disabled
                        && !configureButton.classList.contains("paypal-ec-separate")
                    ) {
                        togglePaypalSolutionConfigureButton(ecButton, false);
                    }
                });
            });
        ';

        /** @var Mage_Adminhtml_Helper_Js $helper */
        $helper = $this->helper('adminhtml/js');
        return $this->toHtml() . $helper->getScript($js);
    }
}
