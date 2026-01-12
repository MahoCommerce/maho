<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_Giftcard
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

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
     * Get currency code for the gift card row
     */
    protected function _getCurrencyCode(Maho\DataObject $row): string
    {
        $websiteId = $row->getData('website_id');
        if ($websiteId) {
            try {
                return Mage::app()->getWebsite($websiteId)->getBaseCurrencyCode();
            } catch (Exception $e) {
                // Fall through to default
            }
        }

        return Mage::app()->getBaseCurrencyCode();
    }
}
