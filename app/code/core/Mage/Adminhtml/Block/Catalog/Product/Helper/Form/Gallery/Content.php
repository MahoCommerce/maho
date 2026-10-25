<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_Catalog_Product_Helper_Form_Gallery_Content extends Mage_Adminhtml_Block_Widget
{
    /**
     * Type of uploader block
     *
     * @var string
     */
    protected $_uploaderType = 'adminhtml/uploader_multiple';

    public function __construct()
    {
        parent::__construct();
        $this->setTemplate('catalog/product/helper/gallery.phtml');
    }

    #[\Override]
    protected function _prepareLayout()
    {
        $this->setChild(
            'uploader',
            $this->getLayout()->createBlock($this->_uploaderType),
        );

        $this->getUploader()->getUploaderConfig()
            ->setFileParameterName('image')
            ->setTarget(Mage::getModel('adminhtml/url')->getUrl(
                '*/catalog_product_gallery/upload',
                ['_query' => false],
            ));

        $this->getUploader()->getMiscConfig()->setAutoUpload();

        $browseConfig = $this->getUploader()->getButtonConfig();
        $browseConfig
            ->setAttributes([
                'accept' => $browseConfig->getMimeTypesByExtensions(\Maho\Io\File::ALLOWED_IMAGES_EXTENSIONS),
            ]);

        Mage::dispatchEvent('catalog_product_gallery_prepare_layout', ['block' => $this]);

        return parent::_prepareLayout();
    }

    /**
     * Retrieve uploader block
     *
     * @return Mage_Adminhtml_Block_Uploader_Multiple
     */
    public function getUploader()
    {
        return $this->getChild('uploader');
    }

    /**
     * Retrieve uploader block html
     *
     * @return string
     */
    public function getUploaderHtml()
    {
        return $this->getChildHtml('uploader');
    }

    /**
     * @return string
     */
    public function getJsObjectName()
    {
        return $this->getHtmlId() . 'JsObject';
    }

    /**
     * @return string
     */
    public function getAddImagesButton()
    {
        return $this->getButtonHtml(
            Mage::helper('catalog')->__('Add New Images'),
            $this->getJsObjectName() . '.showUploader()',
            'add',
            $this->getHtmlId() . '_add_images_button',
        );
    }

    /**
     * @return string
     */
    public function getImagesJson()
    {
        if (is_array($this->getElement()->getValue())) {
            $value = $this->getElement()->getValue();
            if (count($value['images']) > 0) {
                // The admin store has no resized files, so the thumbnail comes from a store view
                $product = $this->getElement()->getDataObject();
                $storeId = (int) ($product?->getStoreId() ?: Mage::app()->getDefaultStoreView()?->getId());
                foreach ($value['images'] as &$image) {
                    $image['url'] = Mage::getSingleton('catalog/product_media_config')
                                        ->getMediaUrl($image['file']);
                    // The smallest stored width for 64px at a scale factor of 2
                    $image['thumbnail_url'] = Mage::helper('core/image')
                        ->init(Mage_Catalog_Model_Product_Image_Provider::ENTITY, $image['file'], $storeId)
                        ?->getUrl(128);
                }
                return Mage::helper('core')->jsonEncode($value['images']);
            }
        }
        return '[]';
    }

    /**
     * @return string
     */
    public function getImagesValuesJson()
    {
        $values = [];
        foreach ($this->getMediaAttributes() as $attribute) {
            /** @var Mage_Eav_Model_Entity_Attribute $attribute */
            $attributeCode = $attribute->getAttributeCode();
            $values[$attributeCode] = $this->getElement()->getDataObject()->getData($attributeCode);
        }
        return Mage::helper('core')->jsonEncode($values);
    }

    /**
     * @return array
     */
    public function getImageTypes()
    {
        $imageTypes = [];
        foreach ($this->getMediaAttributes() as $attribute) {
            /** @var Mage_Eav_Model_Entity_Attribute $attribute */
            $imageTypes[$attribute->getAttributeCode()] = [
                'label' => $attribute->getFrontend()->getLabel() . ' '
                         . Mage::helper('catalog')->__($this->getElement()->getScopeLabel($attribute)),
                'field' => $this->getElement()->getAttributeFieldName($attribute),
            ];
        }
        return $imageTypes;
    }

    /**
     * @return bool
     */
    public function hasUseDefault()
    {
        foreach ($this->getMediaAttributes() as $attribute) {
            if ($this->getElement()->canDisplayUseDefault($attribute)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array
     */
    public function getMediaAttributes()
    {
        return $this->getElement()->getDataObject()->getMediaAttributes();
    }

    /**
     * @return string
     */
    public function getImageTypesJson()
    {
        return Mage::helper('core')->jsonEncode($this->getImageTypes());
    }
}
