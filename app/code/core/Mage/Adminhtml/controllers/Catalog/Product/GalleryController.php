<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Catalog_Product_GalleryController extends Mage_Adminhtml_Controller_Action
{
    /**
     * ACL resource
     * @see Mage_Adminhtml_Controller_Action::_isAllowed()
     */
    public const ADMIN_RESOURCE = 'catalog/products';

    #[Maho\Config\Route('/admin/catalog_product_gallery/upload')]
    public function uploadAction(): void
    {
        try {
            $uploader = Mage::getModel('core/file_uploader', 'image');
            $uploader->setAllowedExtensions(\Maho\Io\File::ALLOWED_IMAGES_EXTENSIONS);
            $uploader->addValidateCallback(
                'catalog_product_image',
                Mage::helper('catalog/image'),
                'validateUploadFile',
            );
            $uploader->setAllowRenameFiles(true);
            $uploader->setFilesDispersion(true);
            $uploader->addValidateCallback(
                Mage_Core_Model_File_Validator_Image::NAME,
                Mage::getModel('core/file_validator_image'),
                'validate',
            );
            $result = $uploader->save(
                Mage::getSingleton('catalog/product_media_config')->getBaseTmpMediaPath(),
            );

            Mage::dispatchEvent('catalog_product_gallery_upload_image_after', [
                'result' => $result,
                'action' => $this,
            ]);

            $result['url'] = Mage::getSingleton('catalog/product_media_config')->getTmpMediaUrl($result['file']);
            $result['file'] = $result['file'] . '.tmp';

            $this->getResponse()->setBodyJson($result);
        } catch (Exception $e) {
            $this->getResponse()->setBodyJson(['error' => true, 'message' => $e->getMessage()]);
        }
    }
}
