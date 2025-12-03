<?php

/**
 * Maho
 *
 * @package    Mage_Rule
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

abstract class Mage_Rule_Model_Resource_Rule_Collection_Abstract extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    /**
     * Store associated with rule entities information map
     *
     * Example:
     * [
     *    'entity_type1' => [
     *        'associations_table' => 'table_name',
     *        'rule_id_field'      => 'rule_id',
     *        'entity_id_field'    => 'entity_id'
     *    ],
     *    'entity_type2' => [
     *        'associations_table' => 'table_name',
     *        'rule_id_field'      => 'rule_id',
     *        'entity_id_field'    => 'entity_id'
     *    ]
     *    ....
     * ]
     *
     * @var array
     */
    protected $_associatedEntitiesMap = [];

    /**
     * Add website ids to rules data
     *
     * @return Mage_Rule_Model_Resource_Rule_Collection_Abstract
     */
    #[\Override]
    protected function _afterLoad()
    {
        parent::_afterLoad();
        if ($this->getFlag('add_websites_to_result') && $this->_items) {
            /** @var Mage_Rule_Model_Abstract $item */
            foreach ($this->_items as $item) {
                $item->afterLoad();
            }
        }

        return $this;
    }

    /**
     * Init flag for adding rule website ids to collection result
     *
     * @param bool|null $flag
     *
     * @return Mage_Rule_Model_Resource_Rule_Collection_Abstract
     */
    public function addWebsitesToResult($flag = null)
    {
        $flag ??= true;
        $this->setFlag('add_websites_to_result', $flag);
        return $this;
    }

    /**
     * Limit rules collection by specific websites
     *
     * @param int|array|Mage_Core_Model_Website $websiteId
     *
     * @return Mage_Rule_Model_Resource_Rule_Collection_Abstract
     */
    public function addWebsiteFilter($websiteId)
    {
        $entityInfo = $this->_getAssociatedEntityInfo('website');
        if (!$this->getFlag('is_website_table_joined')) {
            $this->setFlag('is_website_table_joined', true);
            if ($websiteId instanceof Mage_Core_Model_Website) {
                $websiteId = $websiteId->getId();
            }

            $subSelect = $this->getConnection()->select()
                ->from(['website' => $this->getTable($entityInfo['associations_table'])], '')
                ->where('website.' . $entityInfo['entity_id_field'] . ' IN (?)', $websiteId);
            $this->getSelect()->exists(
                $subSelect,
                'main_table.' . $entityInfo['rule_id_field'] . ' = website.' . $entityInfo['rule_id_field'],
            );
        }
        return $this;
    }

    /**
     * Provide support for website id filter
     *
     * @param string $field
     * @param mixed $condition
     *
     * @return Mage_Rule_Model_Resource_Rule_Collection_Abstract
     */
    #[\Override]
    public function addFieldToFilter($field, $condition = null)
    {
        if ($field == 'website_ids') {
            return $this->addWebsiteFilter($condition);
        }

        parent::addFieldToFilter($field, $condition);
        return $this;
    }

    /**
     * Filter collection to only active or inactive rules
     *
     * @param int $isActive
     *
     * @return Mage_Rule_Model_Resource_Rule_Collection_Abstract
     */
    public function addIsActiveFilter($isActive = 1)
    {
        if (!$this->getFlag('is_active_filter')) {
            $this->addFieldToFilter('is_active', (int) $isActive ? 1 : 0);
            $this->setFlag('is_active_filter', true);
        }
        return $this;
    }

    /**
     * Retrieve correspondent entity information (associations table name, columns names)
     * of rule's associated entity by specified entity type
     *
     * @param string $entityType
     *
     * @return array
     */
    protected function _getAssociatedEntityInfo($entityType)
    {
        if (isset($this->_associatedEntitiesMap[$entityType])) {
            return $this->_associatedEntitiesMap[$entityType];
        }

        throw Mage::exception(
            'Mage_Core',
            Mage::helper('rule')->__('There is no information about associated entity type "%s".', $entityType),
        );
    }

}
