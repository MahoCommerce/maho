<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Giftcard
 */

declare(strict_types=1);

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
    #[\Override]
    protected function _getLayoutHandle()
    {
        return 'giftcard_pdf';
    }

    /**
     * Get block name in layout
     *
     * @return string
     */
    #[\Override]
    protected function _getBlockName()
    {
        return 'giftcard.pdf';
    }

    /**
     * Generate PDF for gift cards
     */
    #[\Override]
    public function getPdf(array|Maho\Data\Collection $documents = []): string
    {
        // Convert to array if collection
        $giftcards = $documents instanceof Maho\Data\Collection ? $documents->getItems() : $documents;

        $this->_beforeGetPdf();

        try {
            // Create PDF block and set gift cards
            $block = Mage::app()->getLayout()->createBlock('giftcard/pdf_giftcard');
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
                Mage::helper('giftcard')->__('Error generating gift card PDF: %s', $e->getMessage()),
            );
        }
    }
}
