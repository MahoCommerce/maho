<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Directory
 */

declare(strict_types=1);

class Mage_Directory_Block_Adminhtml_Region_Edit_Tab_Translations_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('translationsGrid');
        $this->setDefaultSort('locale');
        $this->setUseAjax(true);
    }

    #[\Override]
    protected function _prepareCollection(): self
    {
        $region = Mage::registry('current_region');
        $collection = $region->getTranslationCollection();
        $this->setCollection($collection);
        return parent::_prepareCollection();
    }

    #[\Override]
    protected function _prepareColumns(): self
    {
        $this->addColumn('locale', [
            'header' => Mage::helper('directory')->__('Locale'),
            'width' => '100px',
            'index' => 'locale',
            'type' => 'text',
        ]);

        $this->addColumn('name', [
            'header' => Mage::helper('directory')->__('Localized Name'),
            'index' => 'name',
            'type' => 'text',
        ]);

        return parent::_prepareColumns();
    }

    #[\Override]
    protected function _prepareMassaction(): self
    {
        $this->setMassactionIdField('id');
        $this->getMassactionBlock()->setFormFieldName('locale_id');
        $this->getMassactionBlock()->setUseAjax(true);
        $this->getMassactionBlock()->setHideFormElement(true);

        $this->getMassactionBlock()->addItem('delete', [
            'label' => Mage::helper('adminhtml')->__('Delete'),
            'url' => $this->getUrl('*/*/translationMassDelete', ['_current' => true]),
            'confirm' => Mage::helper('directory')->__('Are you sure you want to delete the selected region names?'),
            'complete' => 'DirectoryEditForm.refreshGrid',
        ]);

        return $this;
    }

    #[\Override]
    public function getGridUrl()
    {
        return $this->getUrl('*/*/translationGrid', ['_current' => true]);
    }

    #[\Override]
    public function getRowUrl($row)
    {
        return '';
    }
}
