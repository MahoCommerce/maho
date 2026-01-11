<?php

/**
 * Maho
 *
 * @package    Mage_Paypal
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Paypal_Block_Adminhtml_System_Config_Field_SolutionType extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    /**
     * @return string
     */
    #[\Override]
    public function render(\Maho\Data\Form\Element\AbstractElement $element)
    {
        $countryCode = Mage::helper('paypal')->getConfigurationCountryCode();
        if ($countryCode === 'DE') {
            /** @var Mage_Paypal_Block_Adminhtml_System_Config_Field_Hidden $block */
            $block = Mage::getBlockSingleton('paypal/adminhtml_System_config_field_hidden');
            return $block->render($element);
        }

        return parent::render($element);
    }
}
