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
    public function directiveAction(): void
    {
        try {
            $directive = $this->getRequest()->getParam('___directive');
            $directive = Mage::helper('core')->urlDecode($directive);
            $path = Mage::getModel('cms/adminhtml_template_filter')->filter($directive);

            $allowedStreamWrappers = Mage::helper('cms')->getAllowedStreamWrappers();
            if (!Mage::getModel('core/file_validator_streamWrapper', $allowedStreamWrappers)->validate($path)) {
                Mage::throwException(Mage::helper('core')->__('Invalid stream.'));
            }

            $image = Maho::getImageManager()->read($path)->encode();

            $this->getResponse()
                ->setHttpResponseCode(200)
                ->setHeader('Content-type', $image->mediaType(), true);

        } catch (Exception $e) {
            Mage::logException($e);
            $this->getResponse()
                ->setHttpResponseCode(500);
        }

        $this->getResponse()->clearBody();
        $this->getResponse()->sendHeaders();

        if (isset($image)) {
            print $image;
        }
        exit(0);
    }
}
