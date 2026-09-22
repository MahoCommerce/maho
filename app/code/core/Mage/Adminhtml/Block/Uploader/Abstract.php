<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

declare(strict_types=1);

abstract class Mage_Adminhtml_Block_Uploader_Abstract extends Mage_Adminhtml_Block_Widget
{
    public const DEFAULT_BROWSE_BUTTON_ID_SUFFIX = 'browse';

    #[\Override]
    protected $_template = 'media/uploader.phtml';

    protected ?Mage_Adminhtml_Model_Uploader_Config_Uploader $_uploaderConfig = null;

    protected ?Mage_Adminhtml_Model_Uploader_Config_Browsebutton $_browseButtonConfig = null;

    protected ?Mage_Adminhtml_Model_Uploader_Config_Misc $_miscConfig = null;

    /** @var array<string, string|list<string>> */
    protected array $_idsMapping = [];

    public function __construct()
    {
        parent::__construct();
        $this->setId($this->getId() . '_Uploader');
    }

    /**
     * Build the settings that public/js/mage/adminhtml/uploader/instance.js reads.
     */
    public function getJsonConfig(): string
    {
        return $this->helper('core')->jsonEncode([
            'uploaderConfig'    => $this->getUploaderConfig()->getData(),
            'elementIds'        => $this->_getElementIdsMapping(),
            'browseConfig'      => $this->getButtonConfig()->getData(),
            'miscConfig'        => $this->getMiscConfig()->getData(),
        ]);
    }

    /**
     * @return array<string, string|list<string>>
     */
    protected function _getElementIdsMapping(): array
    {
        return $this->_idsMapping;
    }

    /**
     * @param array<string, string|list<string>> $additionalButtons
     */
    protected function _addElementIdsMapping(array $additionalButtons = []): static
    {
        $this->_idsMapping = array_merge($this->_idsMapping, $additionalButtons);

        return $this;
    }

    #[\Override]
    protected function _prepareLayout()
    {
        $this->setChild(
            'browse_button',
            $this->getLayout()->createBlock('adminhtml/widget_button')
                ->addData([
                    'id'            => $this->getElementId(self::DEFAULT_BROWSE_BUTTON_ID_SUFFIX),
                    'label'         => Mage::helper('adminhtml')->__('Browse Files...'),
                    'type'          => 'button',
                ]),
        );

        $this->setChild(
            'delete_button',
            $this->getLayout()->createBlock('adminhtml/widget_button')
                ->addData([
                    'id'      => '{{id}}',
                    'class'   => 'delete',
                    'type'    => 'button',
                    'label'   => Mage::helper('adminhtml')->__('Remove'),
                ]),
        );

        $this->_addElementIdsMapping([
            'container'         => $this->getHtmlId(),
            'templateFile'      => $this->getElementId('template'),
            'browse'            => $this->_prepareElementsIds([self::DEFAULT_BROWSE_BUTTON_ID_SUFFIX]),
        ]);

        return parent::_prepareLayout();
    }

    public function getBrowseButtonHtml(): string
    {
        return $this->getChildHtml('browse_button');
    }

    public function getDeleteButtonHtml(): string
    {
        return $this->getChildHtml('delete_button');
    }

    public function getMiscConfig(): Mage_Adminhtml_Model_Uploader_Config_Misc
    {
        $this->_miscConfig ??= Mage::getModel('adminhtml/uploader_config_misc');
        return $this->_miscConfig;
    }

    public function getUploaderConfig(): Mage_Adminhtml_Model_Uploader_Config_Uploader
    {
        $this->_uploaderConfig ??= Mage::getModel('adminhtml/uploader_config_uploader');
        return $this->_uploaderConfig;
    }

    public function getButtonConfig(): Mage_Adminhtml_Model_Uploader_Config_Browsebutton
    {
        $this->_browseButtonConfig ??= Mage::getModel('adminhtml/uploader_config_browsebutton');
        return $this->_browseButtonConfig;
    }

    public function getElementId(string $suffix): string
    {
        return $this->getHtmlId() . '-' . $suffix;
    }

    /**
     * @param list<string> $targets
     * @return list<string>
     */
    protected function _prepareElementsIds(array $targets): array
    {
        return array_map($this->getElementId(...), array_unique(array_values($targets)));
    }
}
