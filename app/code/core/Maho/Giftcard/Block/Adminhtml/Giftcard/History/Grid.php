<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Giftcard
 */

declare(strict_types=1);

class Maho_Giftcard_Block_Adminhtml_Giftcard_History_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('giftcardHistoryGrid');
        $this->setDefaultSort('created_at');
        $this->setSaveParametersInSession(true);
        $this->setUseAjax(true);
    }

    #[\Override]
    protected function _prepareCollection()
    {
        $this->setCollection($this->_createHistoryCollection());

        return parent::_prepareCollection();
    }

    /**
     * History collection with the joins the columns depend on, shared with
     * the card-scoped Edit_History tab.
     */
    protected function _createHistoryCollection(): Maho_Giftcard_Model_Resource_History_Collection
    {
        /** @var Maho_Giftcard_Model_Resource_History_Collection $collection */
        $collection = Mage::getResourceModel('giftcard/history_collection');

        // MIN() over the junction is a valid currency source (all websites share
        // one base currency); a subquery avoids duplicating rows per website
        $collection->getSelect()->join(
            ['gc' => $collection->getTable('giftcard/giftcard')],
            'main_table.giftcard_id = gc.giftcard_id',
            [
                'code',
                'recipient_email',
                'website_id' => new Maho\Db\Expr(sprintf(
                    '(SELECT MIN(gw.website_id) FROM %s gw WHERE gw.giftcard_id = gc.giftcard_id)',
                    $collection->getTable('giftcard/website'),
                )),
            ],
        );

        // Join order table to get increment_id
        $collection->getSelect()->joinLeft(
            ['so' => $collection->getTable('sales/order')],
            'main_table.order_id = so.entity_id',
            ['order_increment_id' => 'increment_id'],
        );

        return $collection;
    }

    #[\Override]
    protected function _prepareColumns()
    {
        $this->addColumn('history_id', [
            'header' => Mage::helper('giftcard')->__('ID'),
            'align'  => 'right',
            'width'  => '50px',
            'index'  => 'history_id',
        ]);

        $this->addColumn('code', [
            'header' => Mage::helper('giftcard')->__('Gift Card Code'),
            'align'  => 'left',
            'index'  => 'code',
            'filter_index' => 'gc.code',
        ]);

        $this->addColumn('action', [
            'header'  => Mage::helper('giftcard')->__('Action'),
            'align'   => 'left',
            'width'   => '100px',
            'index'   => 'action',
            'type'    => 'options',
            'options' => [
                Maho_Giftcard_Model_Giftcard::ACTION_CREATED => 'Created',
                Maho_Giftcard_Model_Giftcard::ACTION_USED => 'Used',
                Maho_Giftcard_Model_Giftcard::ACTION_ADJUSTED => 'Adjusted',
                Maho_Giftcard_Model_Giftcard::ACTION_REFUNDED => 'Refunded',
                Maho_Giftcard_Model_Giftcard::ACTION_EXPIRED => 'Expired',
            ],
        ]);

        $this->addColumn('base_amount', [
            'header'   => Mage::helper('giftcard')->__('Amount'),
            'align'    => 'right',
            'width'    => '100px',
            'index'    => 'base_amount',
            'renderer' => 'Maho_Giftcard_Block_Adminhtml_Giftcard_Renderer_Currency',
        ]);

        $this->addColumn('balance_before', [
            'header'   => Mage::helper('giftcard')->__('Balance Before'),
            'align'    => 'right',
            'width'    => '100px',
            'index'    => 'balance_before',
            'renderer' => 'Maho_Giftcard_Block_Adminhtml_Giftcard_Renderer_Currency',
        ]);

        $this->addColumn('balance_after', [
            'header'   => Mage::helper('giftcard')->__('Balance After'),
            'align'    => 'right',
            'width'    => '100px',
            'index'    => 'balance_after',
            'renderer' => 'Maho_Giftcard_Block_Adminhtml_Giftcard_Renderer_Currency',
        ]);

        $this->addColumn('order_increment_id', [
            'header' => Mage::helper('giftcard')->__('Order'),
            'align'  => 'left',
            'width'  => '120px',
            'index'  => 'order_increment_id',
            'renderer' => 'Maho_Giftcard_Block_Adminhtml_Giftcard_Renderer_Order',
            'filter_index' => 'so.increment_id',
        ]);

        $this->addColumn('recipient_email', [
            'header' => Mage::helper('giftcard')->__('Recipient Email'),
            'align'  => 'left',
            'index'  => 'recipient_email',
            'filter_index' => 'gc.recipient_email',
        ]);

        $this->addColumn('comment', [
            'header' => Mage::helper('giftcard')->__('Comment'),
            'align'  => 'left',
            'index'  => 'comment',
        ]);

        $this->addColumn('created_at', [
            'header' => Mage::helper('giftcard')->__('Date'),
            'align'  => 'left',
            'width'  => '160px',
            'index'  => 'created_at',
            'type'   => 'datetime',
            'filter_index' => 'main_table.created_at',
        ]);

        return parent::_prepareColumns();
    }

    #[\Override]
    public function getGridUrl()
    {
        return $this->getUrl('*/*/grid', ['_current' => true]);
    }

    #[\Override]
    public function getRowUrl($row)
    {
        // Link to the gift card edit page
        return $this->getUrl('*/giftcard/edit', ['id' => $row->getGiftcardId()]);
    }
}
