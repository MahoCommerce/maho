<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
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
            $mount = Mage::getStorage('media');
            $path = \Maho\Io::getPathWithinMount($mount, 'sales/store/logo', $logoFile);
            if ($path !== null && $mount->fileExists($path)) {
                $url = $this->processLogoContent($mount->read($path), strtolower(pathinfo($path, PATHINFO_EXTENSION)));
                if ($url !== null) {
                    return $url;
                }
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
        $content = file_get_contents($logoPath);
        if (!$content) {
            return 'file://' . $logoPath;
        }

        $url = $this->processLogoContent($content, strtolower(pathinfo($logoPath, PATHINFO_EXTENSION)));
        if ($url !== null) {
            return $url;
        }

        $mimeType = mime_content_type($logoPath);
        if ($mimeType && str_starts_with($mimeType, 'image/')) {
            return 'data:' . $mimeType . ';base64,' . base64_encode($content);
        }

        return 'file://' . $logoPath;
    }

    /**
     * The base64 data URL of logo content, or null when the content is not an image
     */
    protected function processLogoContent(string $content, string $extension): ?string
    {
        if ($extension === 'svg') {
            $processedContent = str_replace('<svg ', '<svg fill="none" ', $content);
            return 'data:image/svg+xml;base64,' . base64_encode($processedContent);
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = $finfo ? finfo_buffer($finfo, $content) : false;
        if ($mimeType && str_starts_with($mimeType, 'image/')) {
            return 'data:' . $mimeType . ';base64,' . base64_encode($content);
        }

        return null;
    }
}
