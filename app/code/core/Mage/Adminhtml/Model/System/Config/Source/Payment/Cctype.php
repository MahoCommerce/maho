<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2023 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * @category   Mage
 * @package    Mage_Adminhtml
 */
class Mage_Adminhtml_Model_System_Config_Source_Payment_Cctype
{
    public function toOptionArray()
    {
        $options =  [];

        foreach (Mage::getSingleton('payment/config')->getCcTypes() as $code => $name) {
            $options[] = [
               'value' => $code,
               'label' => $name
            ];
        }

        return $options;
    }
}
