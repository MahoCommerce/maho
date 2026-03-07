<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2017-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_Model_System_Config_Backend_Serialized extends Mage_Core_Model_Config_Data
{
    /**
     * @return $this
     */
    #[\Override]
    protected function _afterLoad()
    {
        if (!is_array($this->getValue())) {
            $serializedValue = $this->getValue();
            $unserializedValue = false;
            if (!empty($serializedValue)) {
                try {
                    $unserializedValue = Mage::helper('core/unserializeArray')
                        ->unserialize((string) $serializedValue);
                } catch (Exception $e) {
                    Mage::logException($e);
                }
            }
            $this->setValue($unserializedValue);
        }
        return $this;
    }

    #[\Override]
    protected function _beforeSave()
    {
        if (is_array($this->getValue())) {
            $this->setValue(Mage::helper('core')->jsonEncode($this->getValue()));
        }
        return $this;
    }
}
