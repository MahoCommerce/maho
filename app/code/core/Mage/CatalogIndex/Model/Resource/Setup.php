<?php

/**
 * Maho
 *
 * @package    Mage_CatalogIndex
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_CatalogIndex_Model_Resource_Setup extends Mage_Core_Model_Resource_Setup
{
    /**
     * @var array|null
     */
    protected $_storeToWebsite   = null;

    /**
     * @param string $table
     * @return $this
     */
    public function convertStoreToWebsite($table)
    {
        $assignment = $this->_getStoreToWebsiteAssignments();
        foreach ($assignment as $website => $stores) {
            $this->_setWebsiteInfo($table, $website, $stores);
        }
        return $this;
    }

    /**
     * @return Mage_Core_Model_Website[]
     */
    protected function _getStoreToWebsiteAssignments()
    {
        if (is_null($this->_storeToWebsite)) {
            $this->_storeToWebsite = [];
            $websiteCollection = Mage::getModel('core/website')->getCollection();
            foreach ($websiteCollection as $website) {
                $this->_storeToWebsite[$website->getId()] = $website->getStoreIds();
            }
        }

        return $this->_storeToWebsite;
    }

    /**
     * @param string $table
     * @param int $websiteId
     * @param array $storeIds
     * @return $this
     */
    protected function _setWebsiteInfo($table, $websiteId, $storeIds)
    {
        $this->getConnection()->update(
            $table,
            ['website_id' => $websiteId],
            $this->getConnection()->quoteInto('store_id IN (?)', $storeIds),
        );

        return $this;
    }
}
