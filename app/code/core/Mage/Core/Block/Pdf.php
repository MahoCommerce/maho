<?php

/**
 * Maho
 *
 * @package    Mage_Core
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Core_Block_Pdf extends Mage_Core_Block_Template
{
    use Mage_Core_Model_Pdf_Trait;

    public function __construct()
    {
        parent::__construct();
        $this->initDompdf();
    }

    public function renderPdf(): string
    {
        $html = $this->toHtml();
        return $this->generatePdf($html);
    }

    public function getStore(): Mage_Core_Model_Store
    {
        return Mage::app()->getStore();
    }

    #[\Override]
    protected function _toHtml(): string
    {
        $html = parent::_toHtml();
        return $this->wrapHtmlDocument($html);
    }

    /**
     * Get logo URL for PDF generation
     * First tries PDF-specific logo, then falls back to default store logo
     * Returns base64 data URL for embedding
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
     * Process logo file, converting all images to base64 data URLs
     * SVG files get fill="none" attribute for better PDF rendering
     */
    protected function processLogoFile(string $logoPath): string
    {
        $extension = strtolower(pathinfo($logoPath, PATHINFO_EXTENSION));
        $content = file_get_contents($logoPath);

        if (!$content) {
            return 'file://' . $logoPath;
        }

        if ($extension === 'svg') {
            $processedContent = str_replace('<svg ', '<svg fill="none" ', $content);
            return 'data:image/svg+xml;base64,' . base64_encode($processedContent);
        }

        $mimeType = null;
        if (function_exists('finfo_buffer')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $mimeType = finfo_buffer($finfo, $content);
                finfo_close($finfo);
            }
        }

        if (!$mimeType && function_exists('mime_content_type')) {
            $mimeType = mime_content_type($logoPath);
        }

        if ($mimeType && str_starts_with($mimeType, 'image/')) {
            return 'data:' . $mimeType . ';base64,' . base64_encode($content);
        }

        return 'file://' . $logoPath;
    }
}
