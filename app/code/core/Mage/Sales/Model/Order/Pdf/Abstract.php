<?php

/**
 * Maho
 *
 * @package    Mage_Sales
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2018-2025 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

use Dompdf\Dompdf;
use Dompdf\Options;

abstract class Mage_Sales_Model_Order_Pdf_Abstract extends Varien_Object
{
    /**
     * Item renderers with render type key
     *
     * model    => the model name
     * renderer => the renderer model
     *
     * @var array
     */
    protected $_renderers = [];

    /**
     * Predefined constants
     */
    public const XML_PATH_SALES_PDF_INVOICE_PUT_ORDER_ID       = 'sales_pdf/invoice/put_order_id';
    public const XML_PATH_SALES_PDF_SHIPMENT_PUT_ORDER_ID      = 'sales_pdf/shipment/put_order_id';
    public const XML_PATH_SALES_PDF_CREDITMEMO_PUT_ORDER_ID    = 'sales_pdf/creditmemo/put_order_id';

    /**
     * Default total model
     *
     * @var string
     */
    protected $_defaultTotalModel = 'sales/order_pdf_total_default';

    /**
     * Layout instance
     */
    protected ?Mage_Core_Model_Layout $_layout = null;

    /**
     * dompdf instance
     */
    protected ?Dompdf $_dompdf = null;

    /**
     * dompdf options
     */
    protected ?Options $_dompdfOptions = null;

    /**
     * Retrieve PDF
     *
     * @return string
     */
    abstract public function getPdf();

    /**
     * Get layout handle for this PDF type
     *
     * @return string
     */
    abstract protected function _getLayoutHandle();

    /**
     * Get block name in layout
     *
     * @return string
     */
    abstract protected function _getBlockName();

    /**
     * Initialize layout
     */
    protected function _getLayout(): Mage_Core_Model_Layout
    {
        if (!$this->_layout) {
            // Ensure we're using adminhtml design area for PDF layouts
            $originalArea = Mage::getDesign()->getArea();
            Mage::getDesign()->setArea('adminhtml');

            $this->_layout = Mage::getSingleton('core/layout');

            // Restore original area if it was different
            if ($originalArea !== 'adminhtml') {
                Mage::getDesign()->setArea($originalArea);
            }
        }
        return $this->_layout;
    }

    /**
     * Initialize dompdf
     *
     * @return void
     */
    protected function _initDompdf()
    {
        if (!$this->_dompdf) {
            $this->_dompdfOptions = new Options();

            // Configure dompdf options from config with improved defaults
            $config = Mage::getStoreConfig('sales_pdf/dompdf') ?: [];

            // Performance optimizations
            $this->_dompdfOptions->set('enable_font_subsetting', $config['enable_font_subsetting'] ?? true);
            $this->_dompdfOptions->set('enable_remote', $config['enable_remote'] ?? false); // Security: disabled by default
            $this->_dompdfOptions->set('enable_css_float', $config['enable_css_float'] ?? true);
            $this->_dompdfOptions->set('enable_html5_parser', $config['enable_html5_parser'] ?? true);

            // Debug options (disabled in production)
            $this->_dompdfOptions->set('debug_png', $config['debug_png'] ?? false);
            $this->_dompdfOptions->set('debug_keep_temp', $config['debug_keep_temp'] ?? false);

            // PDF quality settings
            $this->_dompdfOptions->set('pdf_backend', $config['pdf_backend'] ?? 'CPDF');
            $this->_dompdfOptions->set('default_media_type', $config['default_media_type'] ?? 'print'); // Changed to 'print' for better PDF output
            $this->_dompdfOptions->set('default_paper_size', $config['default_paper_size'] ?? 'a4');
            $this->_dompdfOptions->set('default_paper_orientation', $config['default_paper_orientation'] ?? 'portrait');
            $this->_dompdfOptions->set('default_font', $config['default_font'] ?? 'DejaVu Sans');
            $this->_dompdfOptions->set('dpi', (int) ($config['dpi'] ?? 96));
            $this->_dompdfOptions->set('font_height_ratio', (float) ($config['font_height_ratio'] ?? 1.1));

            // Security settings
            $this->_dompdfOptions->set('is_php_enabled', $config['is_php_enabled'] ?? false);
            $this->_dompdfOptions->set('is_javascript_enabled', $config['is_javascript_enabled'] ?? false);
            $this->_dompdfOptions->set('is_html5_parser_enabled', $config['is_html5_parser_enabled'] ?? true);
            $this->_dompdfOptions->set('is_font_subsetting_enabled', $config['is_font_subsetting_enabled'] ?? true);

            // Set paths
            $this->_dompdfOptions->set('temp_dir', Mage::getBaseDir('var') . DS . 'tmp');
            $this->_dompdfOptions->set('font_dir', Mage::getBaseDir('lib') . DS . 'dompdf' . DS . 'fonts');
            $this->_dompdfOptions->set('font_cache', Mage::getBaseDir('var') . DS . 'cache' . DS . 'dompdf');
            $this->_dompdfOptions->set('chroot', Mage::getBaseDir());
            $this->_dompdfOptions->set('log_output_file', Mage::getBaseDir('var') . DS . 'log' . DS . 'dompdf.log');

            $this->_dompdf = new Dompdf($this->_dompdfOptions);
        }
    }

    /**
     * Generate PDF from HTML (public wrapper for external use)
     *
     * @param string $html
     * @return string
     */
    public function generatePdfFromHtml($html)
    {
        return $this->_generatePdfFromHtml($html);
    }

    /**
     * Generate PDF from HTML
     *
     * @param string $html
     * @return string
     */
    protected function _generatePdfFromHtml($html)
    {
        try {
            $this->_initDompdf();

            if (empty($html)) {
                throw new Exception('Empty HTML content provided for PDF generation');
            }

            $this->_dompdf->loadHtml($html);
            $this->_dompdf->setPaper($this->_dompdfOptions->get('default_paper_size'), 'portrait');
            $this->_dompdf->render();

            $output = $this->_dompdf->output();

            if (empty($output)) {
                throw new Exception('PDF generation failed - empty output');
            }

            return $output;

        } catch (Exception $e) {
            Mage::logException($e);
            throw new Mage_Core_Exception(
                Mage::helper('sales')->__('Error generating PDF: %s', $e->getMessage()),
            );
        }
    }

    /**
     * Render documents to HTML using layout/templates
     */
    protected function _renderDocumentsHtml(array $documents): string
    {
        if (empty($documents)) {
            return '';
        }

        $html = '';

        // Set adminhtml design area for template/block loading
        $originalArea = Mage::getDesign()->getArea();
        Mage::getDesign()->setArea('adminhtml');

        try {
            foreach ($documents as $document) {
                if ($document->getStoreId()) {
                    Mage::app()->getLocale()->emulate($document->getStoreId());
                    Mage::app()->setCurrentStore($document->getStoreId());
                }

                // Create block directly instead of using layout
                $blockClass = $this->_getBlockClass();
                $block = new $blockClass();

                $block->setDocument($document);
                $block->setOrder($document->getOrder());
                $blockHtml = $block->toHtml();

                if (!empty($blockHtml)) {
                    $html .= $blockHtml;
                }

                // Clear block reference for memory management
                unset($block);

                if ($document->getStoreId()) {
                    Mage::app()->getLocale()->revert();
                }

                // Memory management for large document sets
                if (function_exists('gc_collect_cycles')) {
                    gc_collect_cycles();
                }
            }
        } finally {
            // Restore original area even if exceptions occur
            if ($originalArea !== 'adminhtml') {
                Mage::getDesign()->setArea($originalArea);
            }
        }

        return $this->_wrapHtmlDocument($html);
    }

    /**
     * Get block class name for direct instantiation
     */
    protected function _getBlockClass(): string
    {
        // Default implementation - subclasses should override
        return 'Mage_Core_Block_Template';
    }

    /**
     * Wrap HTML content with document structure
     */
    protected function _wrapHtmlDocument(string $html): string
    {
        $css = $this->_getCssContent();

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

    /**
     * Get CSS content for PDF
     */
    protected function _getCssContent(): string
    {
        // Ensure we're in adminhtml design area for CSS loading
        $originalArea = Mage::getDesign()->getArea();
        Mage::getDesign()->setArea('adminhtml');

        $cssPath = Mage::getDesign()->getTemplateFilename('sales/order/pdf/styles/pdf.css', [
            '_type' => 'template',
            '_package' => Mage::getDesign()->getPackageName(),
            '_theme' => Mage::getDesign()->getTheme('template'),
        ]);

        // Restore original area
        if ($originalArea !== 'adminhtml') {
            Mage::getDesign()->setArea($originalArea);
        }

        if (file_exists($cssPath)) {
            return file_get_contents($cssPath);
        }

        return $this->_getDefaultCss();
    }

    /**
     * Get default CSS
     */
    protected function _getDefaultCss(): string
    {
        return '
@page {
    size: A4;
    margin: 15mm;
    font-family: "DejaVu Sans", sans-serif;
}

.pdf-document {
    width: 100%;
    font-size: 10pt;
    color: #000;
    font-family: "DejaVu Sans", sans-serif;
}

.pdf-table-header {
    background-color: #EDECEC;
    border: 0.5pt solid #808080;
    padding: 5pt;
    font-weight: bold;
}

.pdf-items-table {
    width: 100%;
    table-layout: fixed;
    border-collapse: collapse;
    margin-bottom: 20pt;
}

.pdf-items-table th,
.pdf-items-table td {
    padding: 2pt 5pt;
    vertical-align: top;
    border-bottom: 0.5pt solid #ccc;
}

.col-products { width: 45%; text-align: left; }
.col-sku { width: 12%; text-align: right; }
.col-price { width: 13%; text-align: right; }
.col-qty { width: 10%; text-align: right; }
.col-tax { width: 12%; text-align: right; }
.col-subtotal { width: 8%; text-align: right; }

.pdf-font-regular { font-size: 10pt; font-weight: normal; }
.pdf-font-bold { font-size: 10pt; font-weight: bold; }
.pdf-font-small { font-size: 7pt; }

.text-primary { color: #000000; }
.text-secondary { color: #808080; }
.bg-header { background-color: #EDECEC; }
.border-standard { border-color: #808080; }

.pdf-logo {
    text-align: left;
    margin-bottom: 20pt;
}

.pdf-addresses {
    display: table;
    width: 100%;
    margin-bottom: 20pt;
}

.pdf-address-billing,
.pdf-address-shipping {
    display: table-cell;
    width: 50%;
    vertical-align: top;
    padding-right: 10pt;
}

.pdf-totals {
    margin-top: 20pt;
    float: right;
    width: 40%;
}

.pdf-totals table {
    width: 100%;
}

.text-right { text-align: right; }
.text-left { text-align: left; }
.text-center { text-align: center; }

.page-break {
    page-break-after: always;
}
        ';
    }

    /**
     * Initialize renderer
     */
    protected function _initRenderer(string $type): void
    {
        $renderers = Mage::getConfig()->getNode('global/pdf/item_renderers/' . $type);
        if ($renderers) {
            foreach ($renderers->children() as $name => $renderer) {
                $this->_renderers[$name] = [
                    'model' => (string) $renderer,
                    'renderer' => null,
                ];
            }
        }
    }

    /**
     * Get item renderer
     */
    public function getItemRenderer(string $type): ?Mage_Core_Block_Abstract
    {
        if (!isset($this->_renderers[$type])) {
            $type = 'default';
        }

        if (!isset($this->_renderers[$type])) {
            return null;
        }

        if (!$this->_renderers[$type]['renderer']) {
            $this->_renderers[$type]['renderer'] = $this->_getLayout()->createBlock(
                $this->_renderers[$type]['model'],
            );
        }

        return $this->_renderers[$type]['renderer'];
    }

    /**
     * Get total list
     */
    protected function _getTotalsList(Mage_Sales_Model_Abstract $source): array
    {
        $totals = Mage::getConfig()->getNode('global/pdf/totals')->asArray();
        usort($totals, [$this, '_sortTotalsList']);
        $totalModels = [];
        foreach ($totals as $index => $totalInfo) {
            if (!empty($totalInfo['model'])) {
                $totalModel = Mage::getModel($totalInfo['model']);
                if ($totalModel instanceof Mage_Sales_Model_Order_Pdf_Total_Default) {
                    $totalInfo['model'] = $totalModel;
                } else {
                    Mage::throwException(
                        Mage::helper('sales')->__('PDF total model should extend Mage_Sales_Model_Order_Pdf_Total_Default'),
                    );
                }
            } else {
                $totalModel = Mage::getModel($this->_defaultTotalModel);
            }
            $totalModel->setData($totalInfo);
            $totalModels[] = $totalModel;
        }

        return $totalModels;
    }

    /**
     * Sort totals list
     */
    protected function _sortTotalsList(array $a, array $b): int
    {
        if (!isset($a['sort_order']) || !isset($b['sort_order'])) {
            return 0;
        }
        return $a['sort_order'] <=> $b['sort_order'];
    }

    /**
     * Before get PDF
     *
     * @return void
     */
    protected function _beforeGetPdf()
    {
        $translate = Mage::getSingleton('core/translate');
        /** @var Mage_Core_Model_Translate $translate */
        $translate->setTranslateInline(false);
    }

    /**
     * After get PDF
     *
     * @return void
     */
    protected function _afterGetPdf()
    {
        $translate = Mage::getSingleton('core/translate');
        /** @var Mage_Core_Model_Translate $translate */
        $translate->setTranslateInline(true);
    }

}
