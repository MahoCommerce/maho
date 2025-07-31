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
            // Use getSkinUrl to get the correct path, same as frontend
            /** @var string $logoUrl */
            $logoUrl = Mage::getDesign()->getSkinUrl($storeLogo, ['_store' => $this->getStore()]);

            // Convert URL to file path for dompdf
            // Try both secure and unsecure base URLs to find the correct path
            $baseUrls = [
                Mage::getStoreConfig('web/secure/base_url', $this->getStore()),
                Mage::getStoreConfig('web/unsecure/base_url', $this->getStore()),
            ];

            foreach ($baseUrls as $baseUrl) {
                if (is_string($baseUrl) && str_starts_with($logoUrl, $baseUrl)) {
                    $logoPath = str_replace($baseUrl, Mage::getBaseDir() . DS, $logoUrl);
                    $logoPath = str_replace('/', DS, $logoPath);

                    if (file_exists($logoPath) && is_readable($logoPath)) {
                        return 'file://' . $logoPath;
                    }
                }
            }
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
