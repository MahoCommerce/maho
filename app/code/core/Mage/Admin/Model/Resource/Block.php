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

class Mage_Admin_Model_Resource_Block extends Mage_Core_Model_Resource_Db_Abstract
{
    /**
     * Cache id
     */
    public const CACHE_ID = 'permission_block';

    /**
     * Disallowed names for block
     *
     * @var array
     */
    protected $disallowedBlockNames = ['install/complete'];

    #[\Override]
    protected function _construct()
    {
        $this->_init('admin/permission_block', 'block_id');
    }

    /**
     * Get allowed types
     *
     * @return array
     */
    public function getAllowedTypes()
    {
        $data = Mage::app()->getCache()->load(self::CACHE_ID);
        if ($data === false) {
            $this->_generateCache();
            $data = Mage::app()->getCache()->load(self::CACHE_ID);
        }
        return Mage::helper('core')->jsonDecode($data);
    }

    /**
     * Regenerate cache
     */
    protected function _generateCache()
    {
        /** @var Mage_Admin_Model_Resource_Block_Collection $collection */
        $collection = Mage::getResourceModel('admin/block_collection');
        $collection->addFieldToFilter('is_allowed', ['eq' => 1]);
        $disallowedBlockNames = $this->getDisallowedBlockNames();
        if (is_array($disallowedBlockNames) && count($disallowedBlockNames) > 0) {
            $collection->addFieldToFilter('block_name', ['nin' => $disallowedBlockNames]);
        }
        $data = $collection->getColumnValues('block_name');
        $data = array_flip($data);
        Mage::app()->saveCache(
            Mage::helper('core')->jsonEncode($data),
            self::CACHE_ID,
            [Mage_Core_Model_Resource_Db_Collection_Abstract::CACHE_TAG],
        );
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

    /**
     *  Get disallowed names for block
     *
     * @return array
     */
    public function getDisallowedBlockNames()
    {
        return $this->disallowedBlockNames;
    }
}
