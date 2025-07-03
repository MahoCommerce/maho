<?php

/**
 * Maho
 *
 * @package    Mage_Usa
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2025 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * DHL International (API v1.4) Label Creation
 *
 * Now uses dompdf with HTML/CSS templates instead of coordinate-based drawing
 */
class Mage_Usa_Model_Shipping_Carrier_Dhl_Label_Pdf extends Mage_Core_Block_Template
{
    /**
     * Label Information
     *
     * @var SimpleXMLElement
     */
    protected $_info;

    /**
     * Shipment Request
     *
     * @var Mage_Shipping_Model_Shipment_Request
     */
    protected $_request;

    /**
     * Dhl International Label Creation Class constructor
     */
    public function __construct(array $arguments = [])
    {
        parent::__construct();

        if (isset($arguments['info'])) {
            $this->_info = $arguments['info'];
        }
        if (isset($arguments['request'])) {
            $this->_request = $arguments['request'];
        }

        $this->setTemplate('usa/dhl/label.phtml');
    }

    /**
     * Create Label using HTML/CSS and dompdf
     *
     * @return string
     */
    public function render()
    {
        try {
            // Generate HTML from template
            $html = $this->toHtml();

            if (empty($html)) {
                throw new Exception('Failed to generate HTML for DHL label');
            }

            // Generate PDF using dompdf
            return $this->_generatePdfFromHtml($html);

        } catch (Exception $e) {
            Mage::logException($e);
            throw new Mage_Core_Exception(
                Mage::helper('usa')->__('Error generating DHL label: %s', $e->getMessage()),
            );
        }
    }

    /**
     * Get label information for template
     *
     * @return SimpleXMLElement
     */
    public function getLabelInfo()
    {
        return $this->_info;
    }

    /**
     * Get shipment request for template
     *
     * @return Mage_Shipping_Model_Shipment_Request
     */
    public function getShipmentRequest()
    {
        return $this->_request;
    }

    /**
     * Format date for display
     *
     * @param string $date
     * @return string
     */
    #[\Override]
    public function formatDate($date)
    {
        return Mage::helper('core')->formatDate($date, 'medium', false);
    }

    /**
     * Generate PDF from HTML using dompdf
     *
     * @param string $html
     * @return string
     */
    protected function _generatePdfFromHtml($html)
    {
        $options = new Options();

        // Configure for shipping label requirements
        $options->set('enable_font_subsetting', true);
        $options->set('enable_remote', false);
        $options->set('enable_css_float', true);
        $options->set('enable_html5_parser', true);
        $options->set('default_media_type', 'print');
        $options->set('default_paper_size', 'a4');
        $options->set('default_paper_orientation', 'landscape');
        $options->set('default_font', 'Arial');
        $options->set('dpi', 96);
        $options->set('is_php_enabled', false);
        $options->set('is_javascript_enabled', false);

        // Set paths
        $options->set('temp_dir', Mage::getBaseDir('var') . DS . 'tmp');
        $options->set('font_dir', Mage::getBaseDir('lib') . DS . 'dompdf' . DS . 'fonts');
        $options->set('font_cache', Mage::getBaseDir('var') . DS . 'cache' . DS . 'dompdf');
        $options->set('chroot', Mage::getBaseDir());

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('a4', 'landscape');
        $dompdf->render();

        return $dompdf->output();
    }
}
