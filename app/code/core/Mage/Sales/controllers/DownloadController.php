<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2025 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

class Mage_Sales_DownloadController extends Mage_Core_Controller_Front_Action
{
    /**
     * Custom options downloader
     *
     * @param mixed $info
     */
    protected function _downloadFileAction($info): void
    {
        $secretKey = $this->getRequest()->getParam('key');
        try {
            if (!isset($info['secret_key']) || !hash_equals($info['secret_key'], (string) $secretKey)) {
                throw new Exception();
            }

            $file = Mage::getModel('catalog/product_option_type_file')->openStoredFile($info);
            if ($file === null) {
                throw new Exception();
            }
            $this->_prepareDownloadResponse($info['title'], [
                'value' => $file['stream'],
                'type'  => 'stream',
            ], contentLength: $file['size']);
        } catch (Exception) {
            $this->_forward('noRoute');
        }
    }

    /**
     * Custom options download action
     */
    #[Maho\Config\Route('/sales/download/downloadCustomOption', name: 'sales.download.downloadcustomoption', methods: ['GET'])]
    public function downloadCustomOptionAction(): void
    {
        $quoteItemOptionId = $this->getRequest()->getParam('id');
        /** @var Mage_Sales_Model_Quote_Item_Option $option */
        $option = Mage::getModel('sales/quote_item_option')->load($quoteItemOptionId);

        if (!$option->getId()) {
            $this->_forward('noRoute');
            return;
        }

        // Verify access: session-based (logged-in customer or checkout session)
        // OR secret key validation (for admin view or headless/API flows without frontend sessions)
        $quoteItem = Mage::getModel('sales/quote_item')->load($option->getItemId());
        $quote = Mage::getModel('sales/quote')->load($quoteItem->getQuoteId());
        $customerSession = Mage::getSingleton('customer/session');
        $checkoutQuoteId = Mage::getSingleton('checkout/session')->getQuoteId();

        if ($quote->getCustomerId()) {
            $hasSessionAccess = $customerSession->isLoggedIn()
                && $quote->getCustomerId() == $customerSession->getCustomerId();
        } else {
            $hasSessionAccess = $quote->getId() == $checkoutQuoteId;
        }

        // Fallback: validate secret key from URL against the stored file data.
        // This allows downloads from admin order view and headless storefronts
        // where no frontend customer/checkout session exists.
        $hasKeyAccess = false;
        if (!$hasSessionAccess) {
            $requestKey = $this->getRequest()->getParam('key');
            if ($requestKey) {
                try {
                    $info = Mage::helper('core/unserializeArray')->unserialize($option->getValue());
                    $hasKeyAccess = isset($info['secret_key']) && hash_equals($info['secret_key'], $requestKey);
                } catch (Exception) {
                    // Invalid data — deny access
                }
            }
        }

        if (!$hasSessionAccess && !$hasKeyAccess) {
            $this->_forward('noRoute');
            return;
        }

        $optionId = null;
        if (str_starts_with($option->getCode(), Mage_Catalog_Model_Product_Type_Abstract::OPTION_PREFIX)) {
            $optionId = str_replace(Mage_Catalog_Model_Product_Type_Abstract::OPTION_PREFIX, '', $option->getCode());
            if (!is_numeric($optionId)) {
                $optionId = null;
            }
        }
        $productOption = null;
        if ($optionId) {
            /** @var Mage_Catalog_Model_Product_Option $productOption */
            $productOption = Mage::getModel('catalog/product_option')->load($optionId);
        }
        if (!$productOption || !$productOption->getId()
            || $productOption->getProductId() != $option->getProductId() || $productOption->getType() != 'file'
        ) {
            $this->_forward('noRoute');
            return;
        }

        try {
            $info = Mage::helper('core/unserializeArray')->unserialize($option->getValue());
            $this->_downloadFileAction($info);
        } catch (Exception) {
            $this->_forward('noRoute');
        }
        exit(0);
    }
}
