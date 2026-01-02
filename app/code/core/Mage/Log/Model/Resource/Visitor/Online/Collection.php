<?php

/**
 * Maho
 *
 * @package    Mage_Log
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Log_Model_Resource_Visitor_Online_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    /**
     * joined fields array
     *
     * @var array
     */
    protected $_fields   = [];

    /**
     * Initialize collection model
     */
    #[\Override]
    protected function _construct()
    {
        $this->_init('log/visitor_online');
    }

    /**
     * Add Customer data to collection
     *
     * @return $this
     */
    public function addCustomerData()
    {
        $customer   = Mage::getModel('customer/customer');
        // alias => attribute_code
        $attributes = [
            'customer_lastname'   => 'lastname',
            'customer_middlename' => 'middlename',
            'customer_firstname'  => 'firstname',
            'customer_email'      => 'email',
        ];

        foreach ($attributes as $alias => $attributeCode) {
            $attribute = $customer->getAttribute($attributeCode);

            if ($attribute->getBackendType() == 'static') {
                $tableAlias = 'customer_' . $attribute->getAttributeCode();

                $this->getSelect()->joinLeft(
                    [$tableAlias => $attribute->getBackend()->getTable()],
                    sprintf('%s.entity_id=main_table.customer_id', $tableAlias),
                    [$alias => $attribute->getAttributeCode()],
                );

                $this->_fields[$alias] = sprintf('%s.%s', $tableAlias, $attribute->getAttributeCode());
            } else {
                $tableAlias = 'customer_' . $attribute->getAttributeCode();

                $joinConds  = [
                    sprintf('%s.entity_id=main_table.customer_id', $tableAlias),
                    $this->getConnection()->quoteInto($tableAlias . '.attribute_id=?', $attribute->getAttributeId()),
                ];

                $this->getSelect()->joinLeft(
                    [$tableAlias => $attribute->getBackend()->getTable()],
                    implode(' AND ', $joinConds),
                    [$alias => 'value'],
                );

                $this->_fields[$alias] = sprintf('%s.value', $tableAlias);
            }
        }

        $this->setFlag('has_customer_data', true);
        return $this;
    }

    /**
     * Filter collection by specified website(s)
     *
     * @param int|array $websiteIds
     * @return $this
     */
    public function addWebsiteFilter($websiteIds)
    {
        if ($this->getFlag('has_customer_data')) {
            $this->getSelect()
                ->where('customer_email.website_id IN (?)', $websiteIds);
        }
        return $this;
    }

    /**
     * Add field filter to collection
     * If $attribute is an array will add OR condition with following format:
     * [
     *     ['attribute'=>'firstname', 'like'=>'test%'],
     *     ['attribute'=>'lastname', 'like'=>'test%'],
     * ]
     *
     * @param string $field
     * @param null|string|array $condition
     * @return Mage_Core_Model_Resource_Db_Collection_Abstract
     * @see self::_getConditionSql for $condition
     */
    #[\Override]
    public function addFieldToFilter($field, $condition = null)
    {
        if (isset($this->_fields[$field])) {
            $field = $this->_fields[$field];
        }

        return parent::addFieldToFilter($field, $condition);
    }
}
