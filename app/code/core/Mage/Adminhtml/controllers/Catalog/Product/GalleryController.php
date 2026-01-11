<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_Catalog_Product_GalleryController extends Mage_Adminhtml_Controller_Action
{
    /**
     * ACL resource
     * @see Mage_Adminhtml_Controller_Action::_isAllowed()
     */
    public const ADMIN_RESOURCE = 'catalog/products';

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
