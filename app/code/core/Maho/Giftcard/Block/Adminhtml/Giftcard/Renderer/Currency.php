<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Giftcard
 */

declare(strict_types=1);

/**
 * Grid column renderer for gift card currency amounts
 * Displays amount with the gift card's currency code
 */
class Maho_Giftcard_Block_Adminhtml_Giftcard_Renderer_Currency extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract
{
    /**
     * Render the currency amount with the gift card's currency
     */
    #[\Override]
    public function render(Maho\DataObject $row)
    {
        $index = $this->getColumn()->getIndex();
        $value = $row->getData($index);

        if ($value === null || $value === '') {
            return '';
        }

        $currencyCode = $this->_getCurrencyCode($row);

        return Mage::app()->getLocale()->formatCurrency($value, $currencyCode);
    }

    /**
     * Get currency code for the gift card row.
     *
     * Grid collections carry the card's website associations either as the
     * aggregated `website_ids` CSV (main grid GROUP_CONCAT) or as a single
     * representative `website_id` (history grid subquery). All associated
     * websites share one base currency (enforced on save), so the first id
     * of either form is a valid currency source.
     */
    protected function _getCurrencyCode(Maho\DataObject $row): string
    {
        $websiteId = $row->getData('website_id');
        if (!$websiteId && ($csv = (string) $row->getData('website_ids')) !== '') {
            $websiteId = (int) explode(',', $csv)[0];
        }
        if ($websiteId) {
            try {
                return Mage::app()->getWebsite($websiteId)->getBaseCurrencyCode();
            } catch (Exception) {
                // Fall through to default
            }
        }

        return Mage::app()->getBaseCurrencyCode();
    }
}
