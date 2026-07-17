<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

class Mage_Sales_Model_Resource_Order_Shipment extends Mage_Sales_Model_Resource_Order_Abstract
{
    /**
     * Serializeable field: packages
     *
     * @var array
     */
    protected $_serializableFields = [
        'packages' => [null, []],
    ];

    /**
     * @var string
     */
    protected $_eventPrefix                  = 'sales_order_shipment_resource';

    /**
     * Is grid available
     *
     * @var bool
     */
    protected $_grid                         = true;

    /**
     * @var bool
     */
    protected $_useIncrementId               = true;

    /**
     * @var string
     */
    protected $_entityTypeForIncrementId     = 'shipment';

    #[\Override]
    protected function _construct()
    {
        $this->_init('sales/shipment', 'entity_id');
    }

    /**
     * Init virtual grid records for entity
     *
     * @return $this
     */
    #[\Override]
    protected function _initVirtualGridColumns()
    {
        parent::_initVirtualGridColumns();
        $adapter           = $this->getReadConnection();
        $checkedFirstname  = $adapter->getIfNullSql('{{table}}.firstname', $adapter->quote(''));
        $checkedMidllename = $adapter->getIfNullSql('{{table}}.middlename', $adapter->quote(''));
        $checkedLastname   = $adapter->getIfNullSql('{{table}}.lastname', $adapter->quote(''));
        $concatName        = $adapter->getConcatSql([
            $checkedFirstname,
            $adapter->quote(' '),
            $checkedMidllename,
            $adapter->quote(' '),
            $checkedLastname,
        ]);
        $concatName = new Maho\Db\Expr("TRIM(REPLACE($concatName,'  ', ' '))");

        $this->addVirtualGridColumn(
            'shipping_name',
            'sales/order_address',
            ['shipping_address_id' => 'entity_id'],
            $concatName,
        )
        ->addVirtualGridColumn(
            'order_increment_id',
            'sales/order',
            ['order_id' => 'entity_id'],
            'increment_id',
        )
        ->addVirtualGridColumn(
            'order_created_at',
            'sales/order',
            ['order_id' => 'entity_id'],
            'created_at',
        );

        return $this;
    }
}
