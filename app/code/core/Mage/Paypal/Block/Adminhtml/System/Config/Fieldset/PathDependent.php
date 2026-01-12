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

class Mage_Paypal_Block_Adminhtml_System_Config_Fieldset_PathDependent extends Mage_Paypal_Block_Adminhtml_System_Config_Fieldset_Payment
{
    /**
     * Check whether current payment method has active dependencies
     *
     * @param array $groupConfig
     * @return bool
     */
    public function hasActivePathDependencies($groupConfig)
    {
        $activityPath = $groupConfig['hide_case_path'] ?? '';
        return !empty($activityPath) && (bool) (string) $this->_getConfigDataModel()->getConfigDataValue($activityPath);
    }

    /**
     * Do not render solution if disabled
     *
     * @return string
     */
    #[\Override]
    public function render(\Maho\Data\Form\Element\AbstractElement $element)
    {
        if (!$this->hasActivePathDependencies($this->getGroup($element)->asArray())) {
            return parent::render($element);
        }

        return '';
    }
}
