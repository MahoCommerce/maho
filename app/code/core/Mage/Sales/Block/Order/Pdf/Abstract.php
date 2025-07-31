<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Sales
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

abstract class Mage_Sales_Block_Order_Pdf_Abstract extends Mage_Core_Block_Template
{
    protected ?Mage_Sales_Model_Order $_order = null;

    /**
     * Get store from the order
     */
    public function getStore(): Mage_Core_Model_Store
    {
        return $this->_order ? $this->_order->getStore() : Mage::app()->getStore();
    }

    /**
     * Get logo URL for PDF generation
     * First tries PDF-specific logo, then falls back to default store logo
     *
     * @return string|null File URL suitable for dompdf
     */
    public function getLogoUrl(): ?string
    {
        // First, try the PDF-specific logo
        $logoFile = Mage::getStoreConfig('sales/identity/logo', $this->getStore());
        if (is_string($logoFile) && $logoFile !== '') {
            $logoPath = Mage::getBaseDir('media') . DS . 'sales' . DS . 'store' . DS . 'logo' . DS . $logoFile;
            if (file_exists($logoPath) && is_readable($logoPath)) {
                return 'file://' . $logoPath;
            }
        }

        // Fallback to the main store logo using the same logic as frontend
        $storeLogo = Mage::getStoreConfig('design/header/logo_src', $this->getStore());
        if (is_string($storeLogo) && $storeLogo !== '') {
            return Mage::getDesign()->getSkinUrl($storeLogo, ['_store' => $this->getStore()]);
        }

        return null;
    }

    /**
     * Get store address from configuration
     */
    public function getStoreAddress(): string
    {
        $address = Mage::getStoreConfig('sales/identity/address', $this->getStore());
        return is_string($address) ? $address : '';
    }
}
