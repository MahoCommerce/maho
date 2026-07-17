<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Tag
 */

class Mage_Tag_ProductController extends Mage_Core_Controller_Front_Action
{
    #[Maho\Config\Route('/tag/product/list/{tagId}', name: 'tag.product.list', methods: ['GET'], requirements: ['tagId' => '\d+'])]
    public function listAction(): void
    {
        $tagId = $this->getRequest()->getParam('tagId');
        $tag = Mage::getModel('tag/tag')->load($tagId);

        if (!$tag->getId() || !$tag->isAvailableInStore()) {
            $this->_forward('404');
            return;
        }
        Mage::register('current_tag', $tag);

        $this->loadLayout();
        $this->_initLayoutMessages('checkout/session');
        $this->_initLayoutMessages('tag/session');
        $this->renderLayout();
    }
}
