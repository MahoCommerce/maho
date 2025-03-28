<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_Cms_WysiwygController extends Mage_Adminhtml_Controller_Action
{
    /**
     * ACL resource
     * @see Mage_Adminhtml_Controller_Action::_isAllowed()
     */
    public const ADMIN_RESOURCE = 'cms';

    /**
     * Template directives callback
     */
    public function directiveAction()
    {
        $directive = $this->getRequest()->getParam('___directive');
        $directive = Mage::helper('core')->urlDecode($directive);
        $url = Mage::getModel('cms/adminhtml_template_filter')->filter($directive);
        try {
            $allowedStreamWrappers = Mage::helper('cms')->getAllowedStreamWrappers();
            if (!Mage::getModel('core/file_validator_streamWrapper', $allowedStreamWrappers)->validate($url)) {
                Mage::throwException(Mage::helper('core')->__('Invalid stream.'));
            }

            $imageManager = \Intervention\Image\ImageManager::gd(
                autoOrientation: false,
                strip: true,
            );
            $image = $imageManager->read($url);
            $imageInfo = @getimagesize($url);
        } catch (Exception $e) {
            $imageManager = \Intervention\Image\ImageManager::gd(
                autoOrientation: false,
                strip: true,
            );
            $image = $imageManager->read(Mage::getSingleton('cms/wysiwyg_config')->getSkinImagePlaceholderPath());
            $imageInfo = @getimagesize(Mage::getSingleton('cms/wysiwyg_config')->getSkinImagePlaceholderPath());
        }

        $this->getResponse()->setHeader('Content-type', $imageInfo['mime']);
        $this->getResponse()->setBody($image->encode());
    }
}
