<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_Model_System_Config_Source_Shipping_Allmethods
{
    /**
     * Return array of carriers.
     * If $isActiveOnlyFlag is set to true, will return only active carriers
     *
     * @param bool $isActiveOnlyFlag
     * @return array
     */
    public function toOptionArray($isActiveOnlyFlag = false)
    {
        $methods = [['value' => '', 'label' => '']];
        $carriers = Mage::getSingleton('shipping/config')->getAllCarriers();
        foreach ($carriers as $carrierCode => $carrierModel) {
            if (!$carrierModel->isActive() && (bool) $isActiveOnlyFlag === true) {
                continue;
            }
            $carrierMethods = $carrierModel->getAllowedMethods();
            if (!$carrierMethods) {
                continue;
            }
            $carrierTitle = Mage::getStoreConfig('carriers/' . $carrierCode . '/title');
            $methods[$carrierCode] = [
                'label'   => $carrierTitle,
                'value' => [],
            ];
            foreach ($carrierMethods as $methodCode => $methodTitle) {
                // Handle cases where methodTitle might be an array (e.g., nested shipping method groups)
                if (is_array($methodTitle)) {
                    foreach ($methodTitle as $subMethodCode => $subMethodTitle) {
                        $methods[$carrierCode]['value'][] = [
                            'value' => $carrierCode . '_' . $subMethodCode,
                            'label' => '[' . $carrierCode . '] ' . $subMethodTitle,
                        ];
                    }
                } else {
                    $methods[$carrierCode]['value'][] = [
                        'value' => $carrierCode . '_' . $methodCode,
                        'label' => '[' . $carrierCode . '] ' . $methodTitle,
                    ];
                }
            }
        }

        return $methods;
    }
}
