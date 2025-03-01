<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Customer Show Customer Model
 *
 * @package    Mage_Adminhtml
 *
 * @method string getField()
 */
class Mage_Adminhtml_Model_System_Config_Backend_Customer_Show_Customer extends Mage_Core_Model_Config_Data
{
    /**
     * Retrieve attribute code
     *
     * @return string
     */
    protected function _getAttributeCode()
    {
        return str_replace('_show', '', $this->getField());
    }

    /**
     * Retrieve attribute objects
     *
     * @return array
     */
    protected function _getAttributeObjects()
    {
        return [
            Mage::getSingleton('eav/config')->getAttribute('customer', $this->_getAttributeCode()),
        ];
    }

    /**
     * Actions after save
     *
     * @return $this
     */
    #[\Override]
    protected function _afterSave()
    {
        $result = parent::_afterSave();

        $valueConfig = [
            ''    => ['is_required' => 0, 'is_visible' => 0],
            'opt' => ['is_required' => 0, 'is_visible' => 1],
            '1'   => ['is_required' => 0, 'is_visible' => 1],
            'req' => ['is_required' => 1, 'is_visible' => 1],
        ];

        $value = $this->getValue();
        $data = $valueConfig[$value] ?? $valueConfig[''];

        if ($this->getScope() == 'websites') {
            $website = Mage::app()->getWebsite($this->getWebsiteCode());
            $dataFieldPrefix = 'scope_';
        } else {
            $website = null;
            $dataFieldPrefix = '';
        }

        foreach ($this->_getAttributeObjects() as $attributeObject) {
            if ($website) {
                $attributeObject->setWebsite($website);
                $attributeObject->load($attributeObject->getId());
            }
            $attributeObject->setData($dataFieldPrefix . 'is_required', $data['is_required']);
            $attributeObject->setData($dataFieldPrefix . 'is_visible', $data['is_visible']);
            $attributeObject->save();
        }

        return $result;
    }

    /**
     * Processing object after delete data
     *
     * @return Mage_Core_Model_Abstract
     */
    #[\Override]
    protected function _afterDelete()
    {
        $result = parent::_afterDelete();

        if ($this->getScope() == 'websites') {
            $website = Mage::app()->getWebsite($this->getWebsiteCode());
            foreach ($this->_getAttributeObjects() as $attributeObject) {
                $attributeObject->setWebsite($website);
                $attributeObject->load($attributeObject->getId());
                $attributeObject->setData('scope_is_required', null);
                $attributeObject->setData('scope_is_visible', null);
                $attributeObject->save();
            }
        }

        return $result;
    }
}
