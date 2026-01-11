<?php

/**
 * Maho
 *
 * @package    Mage_Sales
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2018-2025 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Sales_Model_Order_Pdf_Shipment_Packaging extends Mage_Sales_Model_Order_Pdf_Abstract
{
    protected int $y = 800;

    /**
     * Get layout handle for this PDF type
     *
     * @return string
     */
    #[\Override]
    protected function _getLayoutHandle()
    {
        return 'sales_pdf_shipment_packaging';
    }

    /**
     * Get block name in layout
     *
     * @return string
     */
    #[\Override]
    protected function _getBlockName()
    {
        return 'sales.pdf.shipment.packaging';
    }

    /**
     * Get block class name for direct instantiation
     */
    #[\Override]
    protected function _getBlockClass(): string
    {
        return 'Mage_Sales_Block_Order_Pdf_Shipment_Packaging';
    }

    /**
     * Format pdf file
     *
     * @param array|\Maho\Data\Collection $shipments Array or collection of shipments
     */
    #[\Override]
    public function getPdf(array|\Maho\Data\Collection $shipments = []): string
    {
        $this->_beforeGetPdf();
        $this->_initRenderer('shipment');

        // Handle collections
        if ($shipments instanceof \Maho\Data\Collection) {
            $shipments = $shipments->getItems();
        }

        if (empty($shipments)) {
            return '';
        }

        $html = $this->_renderDocumentsHtml($shipments);
        $pdf = $this->generatePdf($html);

        $this->_afterGetPdf();
        return $pdf;
    }

    /**
     * @param mixed $page
     * @return $this
     * @deprecated No longer used with HTML/CSS approach
     */
    protected function _drawHeaderBlock($page = null): self
    {
        // Legacy method - no longer used with HTML/CSS approach
        return $this;
    }

    /**
     * @param mixed $page
     * @return $this
     * @deprecated No longer used with HTML/CSS approach
     */
    protected function _drawPackageBlock($page = null): self
    {
        // Legacy method - no longer used with HTML/CSS approach
        return $this;
    }

    /**
     * Set packaging block for custom packaging data
     *
     * @return $this
     */
    public function setPackageShippingBlock(mixed $block): self
    {
        $this->setData('package_shipping_block', $block);
        return $this;
    }

    /**
     * Get packaging block
     */
    public function getPackageShippingBlock(): mixed
    {
        return $this->getData('package_shipping_block');
    }
}
