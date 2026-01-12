<?php

/**
 * Maho
 *
 * @package    Mage_Core
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Common PDF functionality trait for both blocks and models
 */
trait Mage_Core_Model_Pdf_Trait
{
    protected ?Dompdf $dompdf = null;
    protected ?Options $dompdfOptions = null;

    protected function initDompdf(): void
    {
        if (!$this->dompdf) {
            $this->dompdfOptions = new Options();

            // Configure dompdf options from config
            $config = Mage::getStoreConfig('sales_pdf/dompdf') ?: [];

            // Performance optimizations
            $this->dompdfOptions->set('enable_font_subsetting', $config['enable_font_subsetting'] ?? true);
            $this->dompdfOptions->set('enable_remote', $config['enable_remote'] ?? false);
            $this->dompdfOptions->set('enable_css_float', $config['enable_css_float'] ?? true);
            $this->dompdfOptions->set('enable_html5_parser', $config['enable_html5_parser'] ?? true);

            // PDF quality settings
            $this->dompdfOptions->set('pdf_backend', $config['pdf_backend'] ?? 'CPDF');
            $this->dompdfOptions->set('default_media_type', $config['default_media_type'] ?? 'print');
            $this->dompdfOptions->set('default_paper_size', $config['default_paper_size'] ?? 'a4');
            $this->dompdfOptions->set('default_paper_orientation', $config['default_paper_orientation'] ?? 'portrait');
            $this->dompdfOptions->set('default_font', $config['default_font'] ?? 'Helvetica');
            $this->dompdfOptions->set('dpi', (int) ($config['dpi'] ?? 96));
            $this->dompdfOptions->set('font_height_ratio', (float) ($config['font_height_ratio'] ?? 1.1));

            // Security settings
            $this->dompdfOptions->set('is_php_enabled', $config['is_php_enabled'] ?? false);
            $this->dompdfOptions->set('is_javascript_enabled', $config['is_javascript_enabled'] ?? false);
            $this->dompdfOptions->set('is_html5_parser_enabled', $config['is_html5_parser_enabled'] ?? true);
            $this->dompdfOptions->set('is_font_subsetting_enabled', $config['is_font_subsetting_enabled'] ?? true);

            // Debug settings
            $this->dompdfOptions->set('debug_png', $config['debug_png'] ?? false);
            $this->dompdfOptions->set('debug_keep_temp', $config['debug_keep_temp'] ?? false);

            // Set paths - use DomPDF's built-in fonts from vendor
            $this->dompdfOptions->set('temp_dir', Mage::getBaseDir('var') . DS . 'tmp');
            $this->dompdfOptions->set('chroot', Mage::getBaseDir());
            $this->dompdfOptions->set('log_output_file', Mage::getBaseDir('var') . DS . 'log' . DS . 'dompdf.log');

            $this->dompdf = new Dompdf($this->dompdfOptions);
        }
    }

    public function getDompdf(): Dompdf
    {
        $this->initDompdf();
        return $this->dompdf;
    }

    public function generatePdf(string $html, string $filename = 'document.pdf'): string
    {
        try {
            $this->initDompdf();

            if (empty($html)) {
                throw new Exception('Empty HTML content provided for PDF generation');
            }

            $this->dompdf->loadHtml($html);
            $this->dompdf->setPaper(
                $this->dompdfOptions->get('default_paper_size'),
                $this->dompdfOptions->get('default_paper_orientation'),
            );
            $this->dompdf->render();

            $output = $this->dompdf->output();

            if (empty($output)) {
                throw new Exception('PDF generation failed - empty output');
            }

            return $output;

        } catch (Exception $e) {
            Mage::logException($e);
            throw new Mage_Core_Exception(
                Mage::helper('core')->__('Error generating PDF: %s', $e->getMessage()),
            );
        }
    }

    protected function getCssContent(): string
    {
        // Ensure we're in adminhtml design area for CSS loading
        $originalArea = Mage::getDesign()->getArea();
        Mage::getDesign()->setArea('adminhtml');

        try {
            $cssPath = Mage::getDesign()->getTemplateFilename('sales/order/pdf/pdf.css', [
                '_type' => 'template',
                '_package' => Mage::getDesign()->getPackageName(),
                '_theme' => Mage::getDesign()->getTheme('template'),
            ]);

            if (file_exists($cssPath)) {
                return file_get_contents($cssPath);
            }

            // Return empty string if CSS file doesn't exist
            return '';
        } finally {
            // Restore original area
            if ($originalArea !== 'adminhtml') {
                Mage::getDesign()->setArea($originalArea);
            }
        }
    }

    protected function wrapHtmlDocument(string $html): string
    {
        $css = $this->getCssContent();

        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>' . $css . '</style>
</head>
<body class="pdf-document">
' . $html . '
</body>
</html>';
    }

    public function formatPrice(float $price, ?string $currency = null): string
    {
        if ($currency) {
            return Mage::helper('core')->formatPrice($price, false);
        }
        return Mage::helper('core')->formatPrice($price);
    }
}
