<?php

/**
 * Maho
 *
 * @package    Mage_Sales
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Sales_Model_Resource_Billing_Agreement_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    /**
     * Mapping for fields
     *
     * @var array
     */
    protected $_map = ['fields' => [
        'customer_email'       => 'ce.email',
        'customer_firstname'   => 'firstname.value',
        'customer_middlename'  => 'middlename.value',
        'customer_lastname'    => 'lastname.value',
        'agreement_created_at' => 'main_table.created_at',
        'agreement_updated_at' => 'main_table.updated_at',
    ]];

    /**
     * Collection initialization
     *
     */
    #[\Override]
    protected function _construct()
    {
        $this->_init('sales/billing_agreement');
    }

    /**
     * Add cutomer details(email, firstname, lastname) to select
     *
     * @return $this
     */
    public function addCustomerDetails()
    {
        $select = $this->getSelect()->joinInner(
            ['ce' => $this->getTable('customer/entity')],
            'ce.entity_id = main_table.customer_id',
            ['customer_email' => 'email'],
        );

        $customer = Mage::getResourceSingleton('customer/customer');
        $adapter  = $this->getConnection();
        $attr     = $customer->getAttribute('firstname');
        $joinExpr = 'firstname.entity_id = main_table.customer_id AND '
            . $adapter->quoteInto('firstname.entity_type_id = ?', $customer->getTypeId()) . ' AND '
            . $adapter->quoteInto('firstname.attribute_id = ?', $attr->getAttributeId());

        $select->joinLeft(
            ['firstname' => $attr->getBackend()->getTable()],
            $joinExpr,
            ['customer_firstname' => 'value'],
        );

        $attr     = $customer->getAttribute('middlename');
        $joinExpr = 'middlename.entity_id = main_table.customer_id AND '
            . $adapter->quoteInto('middlename.entity_type_id = ?', $customer->getTypeId()) . ' AND '
            . $adapter->quoteInto('middlename.attribute_id = ?', $attr->getAttributeId());

        $select->joinLeft(
            ['middlename' => $attr->getBackend()->getTable()],
            $joinExpr,
            ['customer_middlename' => 'value'],
        );

        $attr = $customer->getAttribute('lastname');
        $joinExpr = 'lastname.entity_id = main_table.customer_id AND '
            . $adapter->quoteInto('lastname.entity_type_id = ?', $customer->getTypeId()) . ' AND '
            . $adapter->quoteInto('lastname.attribute_id = ?', $attr->getAttributeId());

        $select->joinLeft(
            ['lastname' => $attr->getBackend()->getTable()],
            $joinExpr,
            ['customer_lastname' => 'value'],
        );
        return $this;
    }
}
