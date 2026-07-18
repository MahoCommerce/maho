<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_CatalogSearch
 */

class Mage_CatalogSearch_AjaxController extends Mage_Core_Controller_Front_Action
{
    #[Maho\Config\Route('/catalogsearch/ajax/suggest', name: 'catalogsearch.ajax.suggest', methods: ['GET'])]
    public function suggestAction(): void
    {
        if (!$this->getRequest()->getParam('q', false)) {
            $this->getResponse()->setRedirect(Mage::getSingleton('core/url')->getBaseUrl());
        }

        $this->getResponse()->setBody(
            $this->getLayout()->createBlock('catalogsearch/autocomplete')
                ->setTemplate('catalogsearch/suggest.phtml')
                ->toHtml(),
        );
    }
}
