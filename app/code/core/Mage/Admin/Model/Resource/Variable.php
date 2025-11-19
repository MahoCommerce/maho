<?php

/**
 * Maho
 *
 * @package    Mage_Admin
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Admin_Model_Resource_Variable extends Mage_Core_Model_Resource_Db_Abstract
{
    public const CACHE_ID = 'permission_variable';

    #[\Override]
    protected function _construct()
    {
        $this->_init('admin/permission_variable', 'variable_id');
    }

    protected function _generateCache()
    {
        /** @var Mage_Admin_Model_Resource_Variable_Collection $collection */
        $collection = Mage::getResourceModel('admin/variable_collection');
        $collection->addFieldToFilter('is_allowed', ['eq' => 1]);
        $data = $collection->getColumnValues('variable_name');
        $data = array_flip($data);
        Mage::app()->saveCache(
            Mage::helper('core')->jsonEncode($data),
            self::CACHE_ID,
            [Mage_Core_Model_Resource_Db_Collection_Abstract::CACHE_TAG],
        );
    }

    /**
     * Get allowed types
     */
    public function getAllowedPaths()
    {
        $data = Mage::app()->getCache()->load(self::CACHE_ID);
        if ($data === false) {
            $this->_generateCache();
            $data = Mage::app()->getCache()->load(self::CACHE_ID);
        }
        return Mage::helper('core')->jsonDecode($data);
    }

    #[\Override]
    protected function _afterSave(Mage_Core_Model_Abstract $object)
    {
        $this->_generateCache();
        return parent::_afterSave($object);
    }

    #[\Override]
    protected function _afterDelete(Mage_Core_Model_Abstract $object)
    {
        $this->_generateCache();
        return parent::_afterDelete($object);
    }
}
