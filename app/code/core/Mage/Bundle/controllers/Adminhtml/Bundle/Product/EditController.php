<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Bundle
 */

class Mage_Bundle_Adminhtml_Bundle_Product_EditController extends Mage_Adminhtml_Catalog_ProductController
{
    #[\Override]
    protected function _construct()
    {
        $this->setUsedModuleName('Mage_Bundle');
    }

    #[Maho\Config\Route('/admin/bundle_product_edit/form')]
    public function formAction(): void
    {
        $product = $this->_initProduct();
        $this->getResponse()->setBody(
            $this->getLayout()->createBlock('bundle/adminhtml_catalog_product_edit_tab_bundle', 'admin.product.bundle.items')
                ->setProductId($product->getId())
                ->toHtml(),
        );
    }
}
