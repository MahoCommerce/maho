<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_MediaCleaner
 */

declare(strict_types=1);

class Maho_MediaCleaner_Block_Adminhtml_Mediacleaner extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    public function __construct()
    {
        $this->_blockGroup = 'mediacleaner';
        $this->_controller = 'adminhtml_mediacleaner';
        $this->_headerText = Mage::helper('mediacleaner')->__('Media Cleaner');

        parent::__construct();
        $this->setTemplate('mediacleaner/mediacleaner.phtml');
        $this->_removeButton('add');

        $helper = Mage::helper('mediacleaner');
        $this->_addButton('reset', [
            'label'   => $helper->__('Reset Results'),
            'onclick' => "setLocation('{$this->getActionUrl('reset')}')",
        ]);
    }

    /**
     * Dropdowns rendered in the header, in display order (Scan, then Flush).
     */
    public function getDropdowns(): array
    {
        $helper = Mage::helper('mediacleaner');

        return [
            [
                'label' => $helper->__('Scan'),
                'items' => $this->buildItems([
                    'syncproduct'      => $helper->__('Products'),
                    'synccategory'     => $helper->__('Categories'),
                    'syncwysiwyg'      => $helper->__('WYSIWYG'),
                    'syncproductcache' => $helper->__('Product Cache'),
                ]),
            ],
            [
                'label' => $helper->__('Flush'),
                'items' => $this->buildItems([
                    'flushmediatmp'        => 'media/tmp',
                    'flushmediaimport'     => 'media/import',
                    'flushvarexport'       => 'var/export',
                    'flushvarimportexport' => 'var/importexport',
                ]),
            ],
        ];
    }

    protected function buildItems(array $actionLabels): array
    {
        $items = [];
        foreach ($actionLabels as $action => $label) {
            $items[] = ['label' => $label, 'url' => $this->getActionUrl($action)];
        }
        return $items;
    }

    protected function getActionUrl(string $action): string
    {
        return $this->getUrl('*/*/' . $action, [
            'form_key' => Mage::getSingleton('core/session')->getFormKey(),
        ]);
    }
}
