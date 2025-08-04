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
     * The logo will be returned as base64 data URL
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
                return $this->processLogoFile($logoPath);
            }
        }

        // Fallback to the main store logo using Magento's fallback mechanism
        $storeLogo = Mage::getStoreConfig('design/header/logo_src', $this->getStore());
        if (is_string($storeLogo) && $storeLogo !== '') {
            // Use Magento's design fallback to find the logo file
            $logoPath = Mage::getDesign()->getFilename($storeLogo, [
                '_type' => 'skin',
                '_default' => false,
            ]);

            if ($logoPath && file_exists($logoPath) && is_readable($logoPath)) {
                return $this->processLogoFile($logoPath);
            }
        }

        return null;
    }

    /**
     * Process logo file, converting all images to base64 data URLs for better PDF security
     * SVG files get special treatment with fill="none" attribute
     */
    protected function processLogoFile(string $logoPath): string
    {
        $extension = strtolower(pathinfo($logoPath, PATHINFO_EXTENSION));
        $content = file_get_contents($logoPath);

        if (!$content) {
            return 'file://' . $logoPath; // Fallback
        }

        if ($extension === 'svg') {
            // Add fill="none" to SVG root element for better PDF rendering
            $processedContent = str_replace('<svg ', '<svg fill="none" ', $content);
            return 'data:image/svg+xml;base64,' . base64_encode($processedContent);
        }

        // Get MIME type using PHP's built-in functions
        $mimeType = null;

        // Try finfo first (most reliable)
        if (function_exists('finfo_buffer')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $mimeType = finfo_buffer($finfo, $content);
                finfo_close($finfo);
            }
        }

        // Fallback to mime_content_type if finfo failed
        if (!$mimeType && function_exists('mime_content_type')) {
            $mimeType = mime_content_type($logoPath);
        }

        // Return base64 encoded image if we got a valid MIME type
        if ($mimeType && str_starts_with($mimeType, 'image/')) {
            return 'data:' . $mimeType . ';base64,' . base64_encode($content);
        }

        // Fallback for unknown file types
        return 'file://' . $logoPath;
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
