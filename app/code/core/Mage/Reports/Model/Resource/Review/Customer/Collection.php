<?php

/**
 * Maho
 *
 * @package    Mage_Reports
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Reports_Model_Resource_Review_Customer_Collection extends Mage_Review_Model_Resource_Review_Collection
{
    /**
     * Join customers
     *
     * @return $this
     */
    public function joinCustomers()
    {
        /**
         * Allow to use analytic function to result select
         */
        $this->_useAnalyticFunction = true;

        /** @var Maho\Db\Adapter\AdapterInterface $adapter */
        $adapter            = $this->getConnection();
        /** @var Mage_Customer_Model_Resource_Customer $customer */
        $customer           = Mage::getResourceSingleton('customer/customer');
        /** @var Mage_Eav_Model_Entity_Attribute $firstnameAttr */
        $firstnameAttr      = $customer->getAttribute('firstname');
        /** @var Mage_Eav_Model_Entity_Attribute $firstnameAttr */
        $middlenameAttr      = $customer->getAttribute('middlename');
        /** @var Mage_Eav_Model_Entity_Attribute $lastnameAttr */
        $lastnameAttr       = $customer->getAttribute('lastname');

        $firstnameCondition = ['table_customer_firstname.entity_id = detail.customer_id'];

        if ($firstnameAttr->getBackend()->isStatic()) {
            $firstnameField = 'firstname';
        } else {
            $firstnameField = 'value';
            $firstnameCondition[] = $adapter->quoteInto(
                'table_customer_firstname.attribute_id = ?',
                (int) $firstnameAttr->getAttributeId(),
            );
        }

        $this->getSelect()->joinInner(
            ['table_customer_firstname' => $firstnameAttr->getBackend()->getTable()],
            implode(' AND ', $firstnameCondition),
            [],
        );

        $middlenameCondition = ['table_customer_middlename.entity_id = detail.customer_id'];

        if ($middlenameAttr->getBackend()->isStatic()) {
            $middlenameField = 'middlename';
        } else {
            $middlenameField = 'value';
            $middlenameCondition[] = $adapter->quoteInto(
                'table_customer_middlename.attribute_id = ?',
                (int) $middlenameAttr->getAttributeId(),
            );
        }

        $this->getSelect()->joinInner(
            ['table_customer_middlename' => $middlenameAttr->getBackend()->getTable()],
            implode(' AND ', $middlenameCondition),
            [],
        );

        $lastnameCondition  = ['table_customer_lastname.entity_id = detail.customer_id'];
        if ($lastnameAttr->getBackend()->isStatic()) {
            $lastnameField = 'lastname';
        } else {
            $lastnameField = 'value';
            $lastnameCondition[] = $adapter->quoteInto(
                'table_customer_lastname.attribute_id = ?',
                (int) $lastnameAttr->getAttributeId(),
            );
        }

        //Prepare fullname field result
        $customerFullname = $adapter->getConcatSql([
            "table_customer_firstname.{$firstnameField}",
            "table_customer_middlename.{$middlenameField}",
            "table_customer_lastname.{$lastnameField}",
        ], ' ');
        $this->getSelect()->reset(Maho\Db\Select::COLUMNS)
            ->joinInner(
                ['table_customer_lastname' => $lastnameAttr->getBackend()->getTable()],
                implode(' AND ', $lastnameCondition),
                [],
            )
            ->columns([
                'customer_id' => 'detail.customer_id',
                'customer_name' => $customerFullname,
                'review_cnt'    => 'COUNT(main_table.review_id)'])
            ->group('detail.customer_id');

        return $this;
    }

    /**
     * Get select count sql
     *
     * @return Maho\Db\Select
     */
    #[\Override]
    public function getSelectCountSql()
    {
        $countSelect = clone $this->_select;
        $countSelect->reset(Maho\Db\Select::ORDER);
        $countSelect->reset(Maho\Db\Select::GROUP);
        $countSelect->reset(Maho\Db\Select::HAVING);
        $countSelect->reset(Maho\Db\Select::LIMIT_COUNT);
        $countSelect->reset(Maho\Db\Select::LIMIT_OFFSET);
        $countSelect->reset(Maho\Db\Select::COLUMNS);

        $countSelect->columns(new Maho\Db\Expr('COUNT(DISTINCT detail.customer_id)'));

        return  $countSelect;
    }
}
