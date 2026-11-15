<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2021-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

declare(strict_types=1);

class Mage_Adminhtml_Catalog_Product_AttributeController extends Mage_Adminhtml_Controller_Eav_Attribute
{
    /**
     * ACL resource
     * @see Mage_Adminhtml_Controller_Action::_isAllowed()
     */
    public const ADMIN_RESOURCE = 'catalog/attributes/attributes';

    /**
     * List of tags from setting
     */
    public const XML_PATH_ALLOWED_TAGS = Mage_Catalog_Model_Resource_Eav_Attribute::XML_PATH_ALLOWED_HTML_TAGS;

    #[\Override]
    protected string $gridContainerBlock = 'adminhtml/catalog_product_attribute';

    #[\Override]
    protected function getEntityTypeCode(): string
    {
        return Mage_Catalog_Model_Product::ENTITY;
    }

    #[\Override]
    protected function getPageTitles(): array
    {
        return [$this->__('Catalog'), $this->__('Attributes'), $this->__('Manage Attributes')];
    }

    #[\Override]
    protected function _initAction(): static
    {
        if (!$this->getRequest()->getParam('popup')) {
            return parent::_initAction();
        }
        foreach ($this->getPageTitles() as $title) {
            $this->_title($title);
        }
        $this->loadLayout('popup');
        return $this;
    }

    /**
     * The layout handle adminhtml_catalog_product_attribute_edit holds the blocks of the edit page.
     */
    #[\Override]
    protected function prepareEditLayout(): void
    {
        $this->getLayout()->getBlock('attribute_edit_js')
            ->setIsPopup((bool) $this->getRequest()->getParam('popup'));
    }

    /**
     * A select that the form hides posts nothing, so it saves No. A locked field is disabled and keeps its value.
     */
    #[\Override]
    protected function prepareSaveData(array $data, Mage_Eav_Model_Entity_Attribute $model): array
    {
        $locked = $model->getLockedFields();
        foreach (['is_configurable', 'is_filterable', 'is_filterable_multiple', 'is_filterable_in_search'] as $field) {
            if (!in_array($field, $locked, true)) {
                $data[$field] ??= 0;
            }
        }
        $data['apply_to'] ??= [];
        return $data;
    }

    #[\Override]
    protected function getSavedMessage(): string
    {
        return Mage::helper('catalog')->__('The product attribute has been saved.');
    }

    #[\Override]
    protected function getDeletedMessage(): string
    {
        return Mage::helper('catalog')->__('The product attribute has been deleted.');
    }

    #[\Override]
    protected function redirectAfterSave(Mage_Eav_Model_Entity_Attribute $model): void
    {
        if ($this->getRequest()->getParam('popup')) {
            $this->_redirect('adminhtml/catalog_product/addAttribute', [
                'id' => $this->getRequest()->getParam('product'),
                'attribute' => $model->getId(),
                '_current' => true,
            ]);
            return;
        }
        parent::redirectAfterSave($model);
    }

    #[\Override]
    #[Maho\Config\Route('/admin/catalog_product_attribute/index')]
    public function indexAction(): void
    {
        parent::indexAction();
    }

    #[\Override]
    #[Maho\Config\Route('/admin/catalog_product_attribute/new')]
    public function newAction(): void
    {
        parent::newAction();
    }

    #[\Override]
    #[Maho\Config\Route('/admin/catalog_product_attribute/edit')]
    public function editAction(): void
    {
        parent::editAction();
    }

    #[\Override]
    #[Maho\Config\Route('/admin/catalog_product_attribute/validate')]
    public function validateAction(): void
    {
        parent::validateAction();
    }

    #[\Override]
    #[Maho\Config\Route('/admin/catalog_product_attribute/save')]
    public function saveAction(): void
    {
        parent::saveAction();
    }

    #[\Override]
    #[Maho\Config\Route('/admin/catalog_product_attribute/delete')]
    public function deleteAction(): void
    {
        parent::deleteAction();
    }
}
