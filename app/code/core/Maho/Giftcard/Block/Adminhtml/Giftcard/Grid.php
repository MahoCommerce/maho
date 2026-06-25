<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Giftcard
 */

declare(strict_types=1);

class Maho_Giftcard_Block_Adminhtml_Giftcard_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('giftcardGrid');
        $this->setDefaultSort('giftcard_id');
        $this->setSaveParametersInSession(true);
        $this->setUseAjax(true);
    }

    #[\Override]
    protected function _prepareCollection()
    {
        $collection = Mage::getModel('giftcard/giftcard')->getCollection();

        // Aggregate the website associations from the junction so the grid
        // shows / sorts / filters on a single GROUP_CONCAT column. LEFT JOIN
        // so cards that haven't been backfilled yet (or were stripped of all
        // associations by a botched edit) still appear in the listing — they
        // render as "—" and the operator can re-scope them from the edit page.
        $select = $collection->getSelect();
        $junction = $collection->getTable('giftcard/website');
        $select->joinLeft(
            ['gw' => $junction],
            'gw.giftcard_id = main_table.giftcard_id',
            ['website_ids' => new Maho\Db\Expr('GROUP_CONCAT(DISTINCT gw.website_id ORDER BY gw.website_id ASC)')],
        )->group('main_table.giftcard_id');

        $this->setCollection($collection);
        return parent::_prepareCollection();
    }

    #[\Override]
    protected function _prepareColumns()
    {
        $this->addColumn('giftcard_id', [
            'header' => Mage::helper('giftcard')->__('ID'),
            'align'  => 'right',
            'width'  => '50px',
            'index'  => 'giftcard_id',
        ]);

        $this->addColumn('code', [
            'header' => Mage::helper('giftcard')->__('Code'),
            'align'  => 'left',
            'index'  => 'code',
        ]);

        $this->addColumn('status', [
            'header'  => Mage::helper('giftcard')->__('Status'),
            'align'   => 'left',
            'width'   => '80px',
            'index'   => 'status',
            'type'    => 'options',
            'options' => [
                Maho_Giftcard_Model_Giftcard::STATUS_ACTIVE => 'Active',
                Maho_Giftcard_Model_Giftcard::STATUS_USED => 'Used',
                Maho_Giftcard_Model_Giftcard::STATUS_EXPIRED => 'Expired',
                Maho_Giftcard_Model_Giftcard::STATUS_DISABLED => 'Disabled',
            ],
        ]);

        if (!Mage::app()->isSingleStoreMode()) {
            // Multi-website column backed by the giftcard_website junction
            // (see _prepareCollection). The GROUP_CONCAT'd value renders as a
            // comma-separated list of website names; the filter is FIND_IN_SET
            // against the same expression so the operator can scope the grid
            // to "cards valid on website N".
            $this->addColumn('website_ids', [
                'header'                    => Mage::helper('giftcard')->__('Websites'),
                'align'                     => 'left',
                'width'                     => '160px',
                'index'                     => 'website_ids',
                'type'                      => 'options',
                'options'                   => Mage::getSingleton('adminhtml/system_store')->getWebsiteOptionHash(),
                'sortable'                  => false,
                'renderer'                  => Maho_Giftcard_Block_Adminhtml_Giftcard_Renderer_Websites::class,
                'filter_condition_callback' => [$this, '_filterWebsiteCondition'],
            ]);
        }

        $this->addColumn('balance', [
            'header'   => Mage::helper('giftcard')->__('Balance'),
            'align'    => 'right',
            'width'    => '100px',
            'index'    => 'balance',
            'renderer' => 'Maho_Giftcard_Block_Adminhtml_Giftcard_Renderer_Currency',
        ]);

        $this->addColumn('initial_balance', [
            'header'   => Mage::helper('giftcard')->__('Initial Balance'),
            'align'    => 'right',
            'width'    => '100px',
            'index'    => 'initial_balance',
            'renderer' => 'Maho_Giftcard_Block_Adminhtml_Giftcard_Renderer_Currency',
        ]);

        $this->addColumn('recipient_email', [
            'header' => Mage::helper('giftcard')->__('Recipient Email'),
            'align'  => 'left',
            'index'  => 'recipient_email',
        ]);

        $this->addColumn('purchase_order_id', [
            'header' => Mage::helper('giftcard')->__('Order #'),
            'align'  => 'right',
            'width'  => '100px',
            'index'  => 'purchase_order_id',
            'renderer' => 'Maho_Giftcard_Block_Adminhtml_Giftcard_Renderer_Order',
        ]);

        $this->addColumn('expires_at', [
            'header' => Mage::helper('giftcard')->__('Expires'),
            'align'  => 'left',
            'width'  => '120px',
            'index'  => 'expires_at',
            'type'   => 'datetime',
        ]);

        $this->addColumn('created_at', [
            'header' => Mage::helper('giftcard')->__('Created'),
            'align'  => 'left',
            'width'  => '120px',
            'index'  => 'created_at',
            'type'   => 'datetime',
        ]);

        $this->addColumn('action', [
            'header'    => Mage::helper('giftcard')->__('Action'),
            'width'     => '100',
            'type'      => 'action',
            'getter'    => 'getId',
            'actions'   => [
                [
                    'caption' => Mage::helper('giftcard')->__('Edit'),
                    'url'     => ['base' => '*/*/edit'],
                    'field'   => 'id',
                ],
            ],
            'filter'    => false,
            'sortable'  => false,
            'index'     => 'stores',
            'is_system' => true,
        ]);

        return parent::_prepareColumns();
    }

    #[\Override]
    protected function _prepareMassaction()
    {
        $this->setMassactionIdField('giftcard_id');
        $this->getMassactionBlock()->setFormFieldName('giftcard');

        $this->getMassactionBlock()->addItem('delete', [
            'label'   => Mage::helper('giftcard')->__('Delete'),
            'url'     => $this->getUrl('*/*/massDelete'),
            'confirm' => Mage::helper('giftcard')->__('Are you sure?'),
        ]);

        $statuses = [
            Maho_Giftcard_Model_Giftcard::STATUS_ACTIVE => 'Active',
            Maho_Giftcard_Model_Giftcard::STATUS_DISABLED => 'Disabled',
        ];

        $this->getMassactionBlock()->addItem('status', [
            'label'      => Mage::helper('giftcard')->__('Change status'),
            'url'        => $this->getUrl('*/*/massStatus', ['_current' => true]),
            'additional' => [
                'visibility' => [
                    'name'   => 'status',
                    'type'   => 'select',
                    'class'  => 'required-entry',
                    'label'  => Mage::helper('giftcard')->__('Status'),
                    'values' => $statuses,
                ],
            ],
        ]);

        $this->getMassactionBlock()->addItem('print_pdf', [
            'label' => Mage::helper('giftcard')->__('Print PDF'),
            'url'   => $this->getUrl('*/giftcard_print/massPdf'),
        ]);

        return $this;
    }

    #[\Override]
    public function getGridUrl()
    {
        return $this->getUrl('*/*/grid', ['_current' => true]);
    }

    #[\Override]
    public function getRowUrl($row)
    {
        return $this->getUrl('*/*/edit', ['id' => $row->getId()]);
    }

    /**
     * Filter the grid by membership in the giftcard_website junction.
     *
     * The column dropdown sends a single website_id; we translate to a
     * FIND_IN_SET against the GROUP_CONCAT'd alias built in _prepareCollection
     * (HAVING because the alias is computed, not a raw column reference).
     *
     * @param Maho_Giftcard_Model_Resource_Giftcard_Collection $collection
     * @param Mage_Adminhtml_Block_Widget_Grid_Column $column
     */
    protected function _filterWebsiteCondition($collection, $column): void
    {
        $value = $column->getFilter()->getValue();
        if ($value === null || $value === '') {
            return;
        }
        $collection->getSelect()->having(
            new Maho\Db\Expr(sprintf('FIND_IN_SET(%d, website_ids) > 0', (int) $value)),
        );
    }
}
