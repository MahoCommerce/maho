<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2025 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Downloadable
 */

class Mage_Downloadable_DownloadController extends Mage_Core_Controller_Front_Action
{
    /**
     * Return core session object
     *
     * @return Mage_Core_Model_Session
     */
    protected function _getSession()
    {
        return Mage::getSingleton('core/session');
    }

    /**
     * Return customer session object
     *
     * @return Mage_Customer_Model_Session
     */
    protected function _getCustomerSession()
    {
        return Mage::getSingleton('customer/session');
    }

    /**
     * @param string $resource
     * @param string $resourceType
     * @throws Exception
     */
    protected function _processDownload($resource, $resourceType)
    {
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

        session_write_close();
        $helper->output();
    }

    /**
     * Download sample action
     */
    #[Maho\Config\Route('/downloadable/download/sample/{sample_id}', name: 'downloadable.download.sample', methods: ['GET'], requirements: ['sample_id' => '\d+'])]
    public function sampleAction()
    {
        $sampleId = $this->getRequest()->getParam('sample_id', 0);
        $sample = Mage::getModel('downloadable/sample')->load($sampleId);
        if ($sample->getId()
            && Mage::helper('catalog/product')
                ->getProduct((int) $sample->getProductId(), Mage::app()->getStore()->getId(), 'id')
                ->isAvailable()
        ) {
            $resource = '';
            $resourceType = '';
            if ($sample->getSampleType() == Mage_Downloadable_Helper_Download::LINK_TYPE_URL) {
                $resource = $sample->getSampleUrl();
                $resourceType = Mage_Downloadable_Helper_Download::LINK_TYPE_URL;
            } elseif ($sample->getSampleType() == Mage_Downloadable_Helper_Download::LINK_TYPE_FILE) {
                $resource = Mage::helper('downloadable/file')->getFilePath(
                    Mage_Downloadable_Model_Sample::getBasePath(),
                    $sample->getSampleFile(),
                );
                $resourceType = Mage_Downloadable_Helper_Download::LINK_TYPE_FILE;
            }
            try {
                $this->_processDownload($resource, $resourceType);
                exit(0);
            } catch (Mage_Core_Exception) {
                $this->_getSession()->addError(Mage::helper('downloadable')->__('Sorry, there was an error getting requested content. Please contact the store owner.'));
            }
        }
        return $this->_redirectReferer();
    }

    /**
     * Download link's sample action
     */
    #[Maho\Config\Route('/downloadable/download/linkSample/{link_id}', name: 'downloadable.download.linksample', methods: ['GET'], requirements: ['link_id' => '\d+'])]
    public function linkSampleAction()
    {
        $linkId = $this->getRequest()->getParam('link_id', 0);
        $link = Mage::getModel('downloadable/link')->load($linkId);
        if ($link->getId()
            && Mage::helper('catalog/product')
                ->getProduct((int) $link->getProductId(), Mage::app()->getStore()->getId(), 'id')
                ->isAvailable()
        ) {
            $resource = '';
            $resourceType = '';
            if ($link->getSampleType() == Mage_Downloadable_Helper_Download::LINK_TYPE_URL) {
                $resource = $link->getSampleUrl();
                $resourceType = Mage_Downloadable_Helper_Download::LINK_TYPE_URL;
            } elseif ($link->getSampleType() == Mage_Downloadable_Helper_Download::LINK_TYPE_FILE) {
                $resource = Mage::helper('downloadable/file')->getFilePath(
                    Mage_Downloadable_Model_Link::getBaseSamplePath(),
                    $link->getSampleFile(),
                );
                $resourceType = Mage_Downloadable_Helper_Download::LINK_TYPE_FILE;
            }
            try {
                $this->_processDownload($resource, $resourceType);
                exit(0);
            } catch (Mage_Core_Exception) {
                $this->_getCustomerSession()->addError(Mage::helper('downloadable')->__('Sorry, there was an error getting requested content. Please contact the store owner.'));
            }
        }
        return $this->_redirectReferer();
    }

    /**
     * Download link action
     *
     * @return Mage_Core_Controller_Varien_Action|void
     */
    #[Maho\Config\Route('/downloadable/download/link/{id}', name: 'downloadable.download.link', methods: ['GET'])]
    public function linkAction()
    {
        // The hash is the only credential on a shareable link, and hashes issued before the
        // random ones were derived from a timestamp, so cap the guesses against them.
        $limit = (int) Mage::getStoreConfig('system/rate_limit/downloadable_link');
        $limiter = Mage::helper('core')->rateLimiter(
            'downloadable_link',
            $limit,
            3600,
            \Maho\Security\RateLimitScope::Ip,
        );
        if ($limiter->tooManyAttempts()) {
            $this->_getCustomerSession()->addNotice(Mage::helper('downloadable')->__('Too Soon: You are trying to perform this operation too frequently. Please wait a few seconds and try again.'));
            return $this->_redirect('*/customer/products');
        }

        $id = $this->getRequest()->getParam('id', 0);
        $linkPurchasedItem = Mage::getModel('downloadable/link_purchased_item')->load($id, 'link_hash');
        if (!$linkPurchasedItem->getId()) {
            $limiter->hit();
            $this->_getCustomerSession()->addNotice(Mage::helper('downloadable')->__('Requested link does not exist.'));
            return $this->_redirect('*/customer/products');
        }
        if (!Mage::helper('downloadable')->getIsShareable($linkPurchasedItem)) {
            $customerId = $this->_getCustomerSession()->getCustomerId();
            if (!$customerId) {
                $product = Mage::getModel('catalog/product')->load($linkPurchasedItem->getProductId());
                if ($product->getId()) {
                    $notice = Mage::helper('downloadable')->__('Please log in to download your product or purchase <a href="%s">%s</a>.', $product->getProductUrl(), $product->getName());
                } else {
                    $notice = Mage::helper('downloadable')->__('Please log in to download your product.');
                }
                $this->_getCustomerSession()->addNotice($notice);
                $this->_getCustomerSession()->authenticate($this);
                $this->_getCustomerSession()->setBeforeAuthUrl(
                    Mage::getUrl('downloadable/customer/products/'),
                    ['_secure' => true],
                );
                return;
            }
            $linkPurchased = Mage::getModel('downloadable/link_purchased')->load($linkPurchasedItem->getPurchasedId());
            if ($linkPurchased->getCustomerId() != $customerId) {
                $this->_getCustomerSession()->addNotice(Mage::helper('downloadable')->__('Requested link does not exist.'));
                return $this->_redirect('*/customer/products');
            }
        }
        $status = $linkPurchasedItem->getStatus();
        $itemResource = $linkPurchasedItem->getResource();
        if ($status == Mage_Downloadable_Model_Link_Purchased_Item::LINK_STATUS_AVAILABLE
            && !$itemResource->reserveDownload((int) $linkPurchasedItem->getId())
        ) {
            $status = Mage_Downloadable_Model_Link_Purchased_Item::LINK_STATUS_EXPIRED;
        }
        if ($status == Mage_Downloadable_Model_Link_Purchased_Item::LINK_STATUS_AVAILABLE) {
            $resource = '';
            $resourceType = '';
            if ($linkPurchasedItem->getLinkType() == Mage_Downloadable_Helper_Download::LINK_TYPE_URL) {
                $resource = $linkPurchasedItem->getLinkUrl();
                $resourceType = Mage_Downloadable_Helper_Download::LINK_TYPE_URL;
            } elseif ($linkPurchasedItem->getLinkType() == Mage_Downloadable_Helper_Download::LINK_TYPE_FILE) {
                $resource = Mage::helper('downloadable/file')->getFilePath(
                    Mage_Downloadable_Model_Link::getBasePath(),
                    $linkPurchasedItem->getLinkFile(),
                );
                $resourceType = Mage_Downloadable_Helper_Download::LINK_TYPE_FILE;
            }
            try {
                // Keep the script alive when the customer closes the connection,
                // so a download that never arrived is given back below.
                ignore_user_abort(true);
                $this->_processDownload($resource, $resourceType);
                if (connection_aborted()) {
                    $itemResource->releaseDownload((int) $linkPurchasedItem->getId());
                }
                exit(0);
            } catch (Exception) {
                $itemResource->releaseDownload((int) $linkPurchasedItem->getId());
                $this->_getCustomerSession()->addError(
                    Mage::helper('downloadable')->__('An error occurred while getting the requested content. Please contact the store owner.'),
                );
            }
        } elseif ($status == Mage_Downloadable_Model_Link_Purchased_Item::LINK_STATUS_EXPIRED) {
            $this->_getCustomerSession()->addNotice(Mage::helper('downloadable')->__('The link has expired.'));
        } elseif ($status == Mage_Downloadable_Model_Link_Purchased_Item::LINK_STATUS_PENDING
            || $status == Mage_Downloadable_Model_Link_Purchased_Item::LINK_STATUS_PAYMENT_REVIEW
        ) {
            $this->_getCustomerSession()->addNotice(Mage::helper('downloadable')->__('The link is not available.'));
        } else {
            $this->_getCustomerSession()->addError(
                Mage::helper('downloadable')->__('An error occurred while getting the requested content. Please contact the store owner.'),
            );
        }
        return $this->_redirect('*/customer/products');
    }
}
