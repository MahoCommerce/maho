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
     * so it records the variant and redirects to the cache URL, which the image route serves.
     */
    #[Maho\Config\Route('/core/index/resize', name: 'core.index.resize', methods: ['GET'])]
    public function resizeAction(): void
    {
        $t = $this->getRequest()->getParam('t', '');
        $s = $this->getRequest()->getParam('s', '');

        if ($t === '' || $s === '') {
            $this->getResponse()->setHttpResponseCode(400);
            return;
        }

        $params = Maho::verifyImageResizeRequest($t, $s, Mage::getEncryptionKeyAsHex());
        if ($params === null) {
            $this->getResponse()->setHttpResponseCode(403);
            return;
        }

        if (!isset($params['_sourceFile']) || !is_string($params['_sourceFile'])) {
            $this->getResponse()->setHttpResponseCode(400);
            return;
        }

        /** @var Mage_Catalog_Model_Product_Image $model */
        $model = Mage::getModel('catalog/product_image');
        $model->setTransformParams($params)->setBaseFile($params['_sourceFile']);
        if ($model->getCacheKey() !== null) {
            Mage::getSingleton('catalog/product_image_variant')->register($model);
        }

        $this->getResponse()->setRedirect($model->getUrl(), 301);
    }
}
