<?php

/**
 * Maho
 *
 * @package    Mage_Core
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

use Dompdf\Dompdf;
use Dompdf\Options;

class Mage_Core_Block_Pdf extends Mage_Core_Block_Template
{
    protected $_dompdf;
    protected $_options;

    public function __construct()
    {
        parent::__construct();
        $this->_initDompdf();
    }

    protected function _initDompdf(): void
    {
        $this->_options = new Options();

        // Configure dompdf options from config
        $config = Mage::getStoreConfig('sales_pdf/dompdf');

        $this->_options->set('enable_font_subsetting', $config['enable_font_subsetting'] ?? true);
        $this->_options->set('enable_remote', $config['enable_remote'] ?? false);
        $this->_options->set('enable_css_float', $config['enable_css_float'] ?? true);
        $this->_options->set('enable_html5_parser', $config['enable_html5_parser'] ?? true);
        $this->_options->set('debug_png', $config['debug_png'] ?? false);
        $this->_options->set('debug_keep_temp', $config['debug_keep_temp'] ?? false);
        $this->_options->set('pdf_backend', $config['pdf_backend'] ?? 'CPDF');
        $this->_options->set('default_media_type', $config['default_media_type'] ?? 'screen');
        $this->_options->set('default_paper_size', $config['default_paper_size'] ?? 'a4');
        $this->_options->set('default_paper_orientation', 'portrait');
        $this->_options->set('default_font', $config['default_font'] ?? 'DejaVu Sans');
        $this->_options->set('dpi', $config['dpi'] ?? 96);
        $this->_options->set('font_height_ratio', $config['font_height_ratio'] ?? 1.1);
        $this->_options->set('is_php_enabled', $config['is_php_enabled'] ?? false);
        $this->_options->set('is_javascript_enabled', $config['is_javascript_enabled'] ?? false);
        $this->_options->set('is_html5_parser_enabled', $config['is_html5_parser_enabled'] ?? true);
        $this->_options->set('is_font_subsetting_enabled', $config['is_font_subsetting_enabled'] ?? true);

        // Set paths
        $this->_options->set('temp_dir', Mage::getBaseDir('var') . DS . 'tmp');
        $this->_options->set('font_dir', Mage::getBaseDir('lib') . DS . 'dompdf' . DS . 'fonts');
        $this->_options->set('font_cache', Mage::getBaseDir('var') . DS . 'cache' . DS . 'dompdf');
        $this->_options->set('chroot', Mage::getBaseDir());
        $this->_options->set('log_output_file', Mage::getBaseDir('var') . DS . 'log' . DS . 'dompdf.log');

        $this->_dompdf = new Dompdf($this->_options);
    }

    public function getDompdf(): Dompdf
    {
        return $this->_dompdf;
    }

    public function generatePdf(string $html, string $filename = 'document.pdf'): string
    {
        $this->_dompdf->loadHtml($html);
        $this->_dompdf->setPaper($this->_options->get('default_paper_size'), 'portrait');
        $this->_dompdf->render();

        return $this->_dompdf->output();
    }

    public function renderPdf(): string
    {
        $html = $this->toHtml();
        return $this->generatePdf($html);
    }

    public function formatPrice(float $price, ?string $currency = null): string
    {
        if ($currency) {
            return Mage::helper('core')->formatPrice($price, false);
        }
        return Mage::helper('core')->formatPrice($price);
    }

    public function getStore(): Mage_Core_Model_Store
    {
        return Mage::app()->getStore();
    }

    protected function _toHtml(): string
    {
        // Include PDF CSS
        $css = $this->_getCssContent();
        $html = parent::_toHtml();

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

    protected function _getCssContent(): string
    {
        $cssPath = Mage::getDesign()->getTemplateFilename('sales/order/pdf/styles/pdf.css', [
            '_type' => 'template',
            '_package' => Mage::getDesign()->getPackageName(),
            '_theme' => Mage::getDesign()->getTheme('template'),
        ]);

        if (file_exists($cssPath)) {
            return file_get_contents($cssPath);
        }

        return $this->_getDefaultCss();
    }

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
        ';
    }
}
