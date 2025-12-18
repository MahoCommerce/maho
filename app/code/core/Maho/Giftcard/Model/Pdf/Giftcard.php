<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_Giftcard
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_Giftcard_Model_Pdf_Giftcard extends Mage_Sales_Model_Order_Pdf_Abstract
{
    use Mage_Core_Model_Pdf_Trait;

    public function __construct()
    {
        parent::__construct();
        $this->initDompdf();
    }

    /**
     * Get layout handle for PDF generation
     *
     * @return string
     */
    protected function _getLayoutHandle()
    {
        return 'maho_giftcard_pdf';
    }

    /**
     * Get block name in layout
     *
     * @return string
     */
    protected function _getBlockName()
    {
        return 'maho_giftcard.pdf';
    }

    /**
     * Generate PDF for gift cards
     */
    public function getPdf(array|Varien_Data_Collection $documents = []): string
    {
        // Convert to array if collection
        $giftcards = $documents instanceof Varien_Data_Collection ? $documents->getItems() : $documents;

        $this->_beforeGetPdf();

        try {
            // Create PDF block and set gift cards
            $block = Mage::app()->getLayout()->createBlock('maho_giftcard/pdf_giftcard');
            $block->setGiftcards($giftcards);

            // Generate HTML
            $html = $block->toHtml();

            // Generate PDF using DomPdf
            $pdfContent = $this->generatePdf($html, 'giftcard.pdf');

            $this->_afterGetPdf();

            return $pdfContent;

        } catch (Exception $e) {
            Mage::logException($e);
            throw new Mage_Core_Exception(
                Mage::helper('maho_giftcard')->__('Error generating gift card PDF: %s', $e->getMessage()),
            );
        }
    }
}
