<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

declare(strict_types=1);

class Mage_Adminhtml_Catalog_Product_SetController extends Mage_Adminhtml_Controller_Eav_Set
{
    /**
     * ACL resource
     * @see Mage_Adminhtml_Controller_Action::_isAllowed()
     */
    public const ADMIN_RESOURCE = 'catalog/attributes/sets';

    #[\Override]
    protected string $toolbarBlock = 'adminhtml/catalog_product_attribute_set_toolbar_main';

    #[\Override]
    protected string $gridBlock = 'adminhtml/catalog_product_attribute_set_grid';

    #[\Override]
    protected string $addBlock = 'adminhtml/catalog_product_attribute_set_toolbar_add';

    #[\Override]
    protected string $editBlock = 'adminhtml/catalog_product_attribute_set_main';

    #[\Override]
    protected function getEntityTypeCode(): string
    {
        return Mage_Catalog_Model_Product::ENTITY;
    }

    #[\Override]
    protected function getPageTitles(): array
    {
        return [$this->__('Catalog'), $this->__('Attributes'), $this->__('Manage Attribute Sets')];
    }

    /**
     * The products of a set are deleted with it, through a foreign key.
     */
    #[\Override]
    protected function beforeSetDelete(Mage_Eav_Model_Entity_Attribute_Set $attributeSet): void {}

    #[\Override]
    #[Maho\Config\Route('/admin/catalog_product_set/index')]
    public function indexAction(): void
    {
        parent::indexAction();
    }

    #[\Override]
    #[Maho\Config\Route('/admin/catalog_product_set/edit')]
    public function editAction(): void
    {
        parent::editAction();
    }

    #[\Override]
    #[Maho\Config\Route('/admin/catalog_product_set/setGrid')]
    public function setGridAction(): void
    {
        parent::setGridAction();
    }

    #[\Override]
    #[Maho\Config\Route('/admin/catalog_product_set/save')]
    public function saveAction(): void
    {
        parent::saveAction();
    }

    #[\Override]
    #[Maho\Config\Route('/admin/catalog_product_set/add')]
    public function addAction(): void
    {
        parent::addAction();
    }

    #[\Override]
    #[Maho\Config\Route('/admin/catalog_product_set/delete')]
    public function deleteAction(): void
    {
        parent::deleteAction();
    }
}
