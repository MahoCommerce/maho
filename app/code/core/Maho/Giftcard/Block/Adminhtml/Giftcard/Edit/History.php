<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Giftcard
 */

declare(strict_types=1);

/**
 * Read-only, card-scoped variant of the standalone history grid, shown
 * as the Transaction History tab on the gift card edit page.
 */
class Maho_Giftcard_Block_Adminhtml_Giftcard_Edit_History extends Maho_Giftcard_Block_Adminhtml_Giftcard_History_Grid implements Mage_Adminhtml_Block_Widget_Tab_Interface
{
    #[\Override]
    public function getTabLabel(): string
    {
        return Mage::helper('giftcard')->__('Transaction History');
    }

    #[\Override]
    public function getTabTitle(): string
    {
        return Mage::helper('giftcard')->__('Transaction History');
    }

    #[\Override]
    public function canShowTab(): bool
    {
        $model = Mage::registry('current_giftcard');
        return $model !== null && $model->getId() !== null;
    }

    #[\Override]
    public function isHidden(): bool
    {
        return false;
    }

    public function __construct()
    {
        parent::__construct();
        // Distinct id so saved grid state does not bleed across the two views
        $this->setId('giftcard_edit_history');
        $this->setUseAjax(true);
    }

    /**
     * Without a registered card, filter on id 0 so the table is empty
     * rather than listing every history row.
     */
    #[\Override]
    protected function _prepareCollection()
    {
        $model = Mage::registry('current_giftcard');
        $cardId = $model && $model->getId() ? (int) $model->getId() : 0;

        $collection = $this->_createHistoryCollection()
            ->addFieldToFilter('main_table.giftcard_id', $cardId);

        $this->setCollection($collection);
        return Mage_Adminhtml_Block_Widget_Grid::_prepareCollection();
    }

    #[\Override]
    protected function _prepareMassaction()
    {
        return $this;
    }

    #[\Override]
    public function getRowUrl($row)
    {
        return '';
    }

    #[\Override]
    public function getGridUrl()
    {
        $model = Mage::registry('current_giftcard');
        return $this->getUrl('*/*/historyGrid', [
            'id' => $model ? $model->getId() : 0,
        ]);
    }
}
