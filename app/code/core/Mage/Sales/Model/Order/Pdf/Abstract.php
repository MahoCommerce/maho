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

abstract class Mage_Sales_Model_Order_Pdf_Abstract extends \Maho\DataObject
{
    use Mage_Core_Model_Pdf_Trait;

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
     * Retrieve PDF
     */
    abstract public function getPdf(array|\Maho\Data\Collection $documents = []): string;

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
     * Generate PDF from HTML (public wrapper for external use)
     *
     * @param string $html
     * @return string
     */
    public function generatePdfFromHtml($html)
    {
        return $this->generatePdf($html);
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

        return $this->wrapHtmlDocument($html);
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
