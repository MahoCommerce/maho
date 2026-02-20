<?php

/**
 * Maho
 *
 * @package    Mage_Downloadable
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2025 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Downloadable_Adminhtml_Downloadable_Product_EditController extends Mage_Adminhtml_Catalog_ProductController
{
    #[\Override]
    protected function _construct()
    {
        $this->setUsedModuleName('Mage_Downloadable');
    }

    /**
     * Load downloadable tab fieldsets
     */
    public function formAction(): void
    {
        $this->_initProduct();
        $this->getResponse()->setBody(
            $this->getLayout()->createBlock('downloadable/adminhtml_catalog_product_edit_tab_downloadable', 'admin.product.downloadable.information')
                ->toHtml(),
        );
    }

    /**
     * Download process
     *
     * @param string $resource
     * @param string $resourceType
     */
    protected function _processDownload($resource, $resourceType)
    {
        /** @var Mage_Downloadable_Helper_Download $helper */
        $helper = Mage::helper('downloadable/download');

        $helper->setResource($resource, $resourceType);

        $fileName       = $helper->getFilename();
        $contentType    = $helper->getContentType();

        $this->getResponse()
            ->setHttpResponseCode(200)
            ->setHeader('Pragma', 'public', true)
            ->setHeader('Cache-Control', 'must-revalidate, post-check=0, pre-check=0', true)
            ->setHeader('Content-type', $contentType, true);

        if ($fileSize = $helper->getFilesize()) {
            $this->getResponse()
                ->setHeader('Content-Length', $fileSize);
        }

        if ($contentDisposition = $helper->getContentDisposition()) {
            $this->getResponse()
                ->setHeader('Content-Disposition', $contentDisposition . '; filename=' . $fileName);
        }

        $this->getResponse()
            ->clearBody();
        $this->getResponse()
            ->sendHeaders();

        $helper->output();
    }

    /**
     * Download link action
     */
    public function linkAction(): void
    {
        $linkId = $this->getRequest()->getParam('id', 0);
        $linkType = $this->getRequest()->getParam('type', 'link');
        $resourceType = $this->getRequest()->getParam('resource_type');

        switch ($linkType) {
            case 'samples':
                $link = Mage::getModel('downloadable/sample')->load($linkId);
                $linkUrl = $link->getSampleUrl();
                $linkFile = $link->getSampleFile();
                $basePath = $link->getBasePath();
                $resourceType ??= $link->getSampleType();
                break;
            case 'link_samples':
                $link = Mage::getModel('downloadable/link')->load($linkId);
                $linkUrl = $link->getSampleUrl();
                $linkFile = $link->getSampleFile();
                $basePath = $link->getBaseSamplePath();
                $resourceType ??= $link->getSampleType();
                break;
            case 'link':
            default:
                $link = Mage::getModel('downloadable/link')->load($linkId);
                $linkUrl = $link->getLinkUrl();
                $linkFile = $link->getLinkFile();
                $basePath = $link->getBasePath();
                $resourceType ??= $link->getLinkType();
                break;
        }

        if ($link->getId()) {
            if ($resourceType === Mage_Downloadable_Helper_Download::LINK_TYPE_URL) {
                $resource = $linkUrl;
            } elseif ($resourceType === Mage_Downloadable_Helper_Download::LINK_TYPE_FILE) {
                $resource = Mage::helper('downloadable/file')->getFilePath($basePath, $linkFile);
            } else {
                $resource = '';
            }
            try {
                $this->_processDownload($resource, $resourceType);
            } catch (Mage_Core_Exception $e) {
                $this->_getSession()->addError(
                    Mage::helper('downloadable')->__('An error occurred while getting the requested content.'),
                );
            }
        }
        exit(0);
    }
}
