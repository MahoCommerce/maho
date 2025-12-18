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

class Maho_Giftcard_Block_Pdf_Giftcard extends Mage_Core_Block_Pdf
{
    #[\Override]
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('maho/giftcard/pdf/giftcard.phtml');
    }

    /**
     * Set gift cards for PDF generation
     *
     * @return $this
     */
    public function setGiftcards(array $giftcards)
    {
        $this->setData('giftcards', $giftcards);
        return $this;
    }

    /**
     * Get gift cards
     */
    public function getGiftcards(): array
    {
        return $this->getData('giftcards') ?: [];
    }

    /**
     * Get gift card helper
     *
     * @return Maho_Giftcard_Helper_Data
     */
    public function getGiftcardHelper()
    {
        return Mage::helper('giftcard');
    }

    /**
     * Get store name
     */
    public function getStoreName(): string
    {
        return Mage::getStoreConfig('general/store_information/name') ?: 'Store';
    }

    /**
     * Get logo URL
     */
    public function getLogoUrl(): string
    {
        $logoPath = Mage::getStoreConfig('sales/identity/logo');
        if ($logoPath) {
            return Mage::getBaseDir('media') . DS . 'sales' . DS . 'store' . DS . 'logo' . DS . $logoPath;
        }
        return '';
    }
}
