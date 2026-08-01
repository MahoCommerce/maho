<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Giftcard
 */

declare(strict_types=1);

/**
 * Card-scoped transaction history shown as the second tab on the gift
 * card edit page (registered via the adminhtml_giftcard_edit layout
 * handle's `<action method="addTab">`).
 *
 * Subclasses the existing standalone history grid (which lists every
 * card's history together for the Sales → Gift Card History page) and
 * scopes its collection to the currently-loaded gift card so the audit
 * trail lives next to the card it describes.
 *
 * Mass actions and row-click navigation are explicitly disabled — a row
 * in this view is a read-only audit-trail entry, not a launchpad to
 * another screen.
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
        // Distinct id from the standalone history grid so saved-session
        // filters / sort state don't bleed across the two views.
        $this->setId('giftcard_edit_history');
        $this->setUseAjax(true);
    }

    /**
     * Scope to the current gift card. Without a registered card we set a
     * zero-id filter so the table is empty rather than leaking every
     * history row.
     */
    #[\Override]
    protected function _prepareCollection()
    {
        $model = Mage::registry('current_giftcard');
        $cardId = $model && $model->getId() ? (int) $model->getId() : 0;

        $collection = Mage::getModel('giftcard/history')->getCollection()
            ->addFieldToFilter('giftcard_id', $cardId);

        $this->setCollection($collection);
        return Mage_Adminhtml_Block_Widget_Grid::_prepareCollection();
    }

    /**
     * Suppress the standalone grid's mass actions — this view is read-only.
     */
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
