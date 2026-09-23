<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2018-2025 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
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
        $isFirst = true;

        // The PDF templates live in the admin design. Outside the admin (API, emails sent
        // from the storefront or cron) the storefront package finds none, and the PDF
        // comes out blank, so switch the whole design, not only the area.
        $restoreDesign = $this->useAdminDesign();

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
                    if (!$isFirst) {
                        $html .= '<div style="page-break-before: always;"></div>';
                    }
                    $html .= $blockHtml;
                    $isFirst = false;
                }

                // Clear block reference for memory management
                unset($block);

                if ($document->getStoreId()) {
                    Mage::app()->getLocale()->revert();
                }

                // Memory management for large document sets
                gc_collect_cycles();
            }

            // pdf.css is in the admin design too.
            return $this->wrapHtmlDocument($html);
        } finally {
            $restoreDesign();
        }
    }

    /**
     * Switch to the admin design package and theme, as Mage_Adminhtml_Controller_Action::preDispatch()
     * does, and return a callback that restores the previous design.
     */
    protected function useAdminDesign(): \Closure
    {
        $design = Mage::getDesign();
        $area = $design->getArea();
        $package = $design->getPackageName();
        $themes = [];
        foreach (['layout', 'template', 'skin', 'locale'] as $type) {
            $themes[$type] = $design->getTheme($type);
        }

        $design->setArea('adminhtml')
            ->setPackageName((string) Mage::getConfig()->getNode('stores/admin/design/package/name'))
            ->setTheme((string) Mage::getConfig()->getNode('stores/admin/design/theme/openmage'));
        foreach (array_keys($themes) as $type) {
            if ($value = (string) Mage::getConfig()->getNode("stores/admin/design/theme/{$type}")) {
                $design->setTheme($type, $value);
            }
        }

        return function () use ($design, $area, $package, $themes): void {
            $design->setArea($area)->setPackageName($package);
            foreach ($themes as $type => $theme) {
                $design->setTheme($type, $theme);
            }
        };
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
        usort($totals, $this->_sortTotalsList(...));
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

}
