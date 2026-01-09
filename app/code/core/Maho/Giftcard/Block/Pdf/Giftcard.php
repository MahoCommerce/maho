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
 * Gift Card PDF Block
 *
 * Renders gift cards as PDF using the store's frontend theme.
 * Template and CSS are loaded from frontend theme with fallback to base/default.
 */
class Maho_Giftcard_Block_Pdf_Giftcard extends Mage_Core_Block_Pdf
{
    protected ?Mage_Core_Model_Store $_store = null;
    protected ?string $_originalArea = null;
    protected ?string $_originalPackage = null;
    protected ?string $_originalTheme = null;

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
    public function setGiftcards(array $giftcards): self
    {
        $this->setData('giftcards', $giftcards);

        // Auto-detect store from first gift card if not already set
        if (!$this->_store && !empty($giftcards)) {
            $firstCard = reset($giftcards);
            if ($firstCard->getStoreId()) {
                $this->setStore(Mage::app()->getStore($firstCard->getStoreId()));
            }
        }

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
     * Set the store for theme resolution
     *
     * @return $this
     */
    public function setStore(Mage_Core_Model_Store $store): self
    {
        $this->_store = $store;
        return $this;
    }

    /**
     * Get the store for theme resolution
     */
    #[\Override]
    public function getStore(): Mage_Core_Model_Store
    {
        return $this->_store ?? Mage::app()->getStore();
    }

    /**
     * Get store URL for the gift card
     */
    public function getStoreUrl(): string
    {
        return $this->getStore()->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB);
    }

    /**
     * Format price using store's currency
     */
    #[\Override]
    public function formatPrice(float $price, ?string $currencyCode = null): string
    {
        return Mage::app()->getLocale()->formatCurrency($price, $currencyCode);
    }

    /**
     * Get CSS content from frontend theme with fallback
     */
    #[\Override]
    protected function getCssContent(): string
    {
        $cssPath = Mage::getDesign()->getTemplateFilename('maho/giftcard/pdf/giftcard.css');

        if ($cssPath && file_exists($cssPath)) {
            return file_get_contents($cssPath);
        }

        return '';
    }

    /**
     * Render HTML with store's frontend theme context
     */
    #[\Override]
    protected function _toHtml(): string
    {
        $this->_switchToFrontendTheme();

        try {
            // Skip Mage_Core_Block_Pdf::_toHtml() which would wrap the HTML
            // Our template provides a complete HTML document
            return Mage_Core_Block_Template::_toHtml();
        } finally {
            $this->_restoreOriginalTheme();
        }
    }

    /**
     * Override parent to not wrap HTML - template provides full document
     */
    #[\Override]
    public function renderPdf(): string
    {
        $this->_switchToFrontendTheme();

        try {
            // Get HTML directly without wrapping
            $html = Mage_Core_Block_Template::_toHtml();
            return $this->generatePdf($html);
        } finally {
            $this->_restoreOriginalTheme();
        }
    }

    /**
     * Switch design context to store's frontend theme
     */
    protected function _switchToFrontendTheme(): void
    {
        $design = Mage::getDesign();
        $store = $this->getStore();

        // Save original design settings
        $this->_originalArea = $design->getArea();
        $this->_originalPackage = $design->getPackageName();
        $this->_originalTheme = $design->getTheme('template');

        // Switch to frontend area
        $design->setArea('frontend');

        // Apply store's theme settings
        $package = Mage::getStoreConfig('design/package/name', $store) ?: 'base';
        $theme = Mage::getStoreConfig('design/theme/template', $store) ?: 'default';

        $design->setPackageName($package);
        $design->setTheme('template', $theme);
        $design->setTheme('skin', $theme);
        $design->setTheme('layout', $theme);
    }

    /**
     * Restore original design context
     */
    protected function _restoreOriginalTheme(): void
    {
        if ($this->_originalArea === null) {
            return;
        }

        $design = Mage::getDesign();
        $design->setArea($this->_originalArea);
        $design->setPackageName($this->_originalPackage);
        $design->setTheme('template', $this->_originalTheme);
        $design->setTheme('skin', $this->_originalTheme);
        $design->setTheme('layout', $this->_originalTheme);

        $this->_originalArea = null;
        $this->_originalPackage = null;
        $this->_originalTheme = null;
    }
}
