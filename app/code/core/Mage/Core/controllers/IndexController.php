<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

class Mage_Core_IndexController extends Mage_Core_Controller_Front_Action
{
    #[Maho\Config\Route('/core', name: 'core.index')]
    public function indexAction(): void
    {
        $this->_forward('noRoute');
    }

    /**
     * The signed resize URL of earlier releases. Sent emails and cached pages still hold it,
     * so it redirects to the product image file of the nearest width.
     */
    #[Maho\Config\Route('/core/index/resize', name: 'core.index.resize', methods: ['GET'])]
    public function resizeAction(): void
    {
        $t = (string) $this->getRequest()->getParam('t', '');
        $s = (string) $this->getRequest()->getParam('s', '');

        if ($t === '' || $s === '') {
            $this->getResponse()->setHttpResponseCode(400);
            return;
        }

        $params = Maho::verifyImageResizeRequest($t, $s, Mage::getEncryptionKeyAsHex());
        if ($params === null) {
            $this->getResponse()->setHttpResponseCode(403);
            return;
        }

        $file = $params['_sourceFile'] ?? null;
        $set = is_string($file) ? Mage::helper('core/image')->init(Mage_Catalog_Model_Product_Image_Provider::ENTITY, $file) : null;
        if ($set === null) {
            $this->getResponse()->setHttpResponseCode(404);
            return;
        }

        $size = max((int) ($params['_width'] ?? 0), (int) ($params['_height'] ?? 0));
        $this->getResponse()->setRedirect($set->getUrl($size), 301);
    }
}
