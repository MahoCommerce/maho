<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Admin
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
            $types = $this->_generateCache();
            $data = Mage::app()->getCache()->load(self::CACHE_ID);
            if ($data === false) {
                // Cache backend is disabled or could not persist the entry;
                // fall back to the freshly built data instead of dropping it.
                return $types;
            }
        }
        $decoded = Mage::helper('core')->jsonDecode($data);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Regenerate cache and return the freshly built allowlist, indexed by block name.
     */
    protected function _generateCache(): array
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
        return $data;
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
