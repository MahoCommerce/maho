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
     * Send the file that a shopper uploaded for a file option of a cart item. The secret key of the file in the
     * URL grants the access, so that the admin order view and a headless storefront can link to the file.
     */
    #[Maho\Config\Route('/sales/download/downloadCustomOption', name: 'sales.download.downloadcustomoption', methods: ['GET'])]
    public function downloadCustomOptionAction(): void
    {
        /** @var Mage_Catalog_Service_Product_Option $service */
        $service = Mage::getService('catalog/product_option');
        try {
            $file = $service->openUploadedFile(
                (int) $this->getRequest()->getParam('id'),
                (string) $this->getRequest()->getParam('key'),
            );
        } catch (Mage_Core_Exception) {
            $this->_forward('noRoute');
            return;
        }

        $this->_prepareDownloadResponse($file['title'], [
            'value' => $file['stream'],
            'type'  => 'stream',
        ], contentLength: $file['size']);
    }
}
