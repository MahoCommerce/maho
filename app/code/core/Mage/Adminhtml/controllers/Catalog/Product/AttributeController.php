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
    public const XML_PATH_ALLOWED_TAGS = 'system/catalog/frontend/allowed_html_tags_list';

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
    protected function getAllowedTags(): array
    {
        return explode(',', (string) Mage::getStoreConfig(self::XML_PATH_ALLOWED_TAGS));
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
     * Product input types also come from the config and from the event adminhtml_product_attribute_types.
     */
    #[\Override]
    protected function isValidInputType(string $input, Mage_Eav_Model_Entity_Attribute $model): bool
    {
        /** @var Mage_Eav_Model_Adminhtml_System_Config_Source_Inputtype_Validator $validator */
        $validator = Mage::getModel('eav/adminhtml_system_config_source_inputtype_validator');
        if ($validator->isValid($input)) {
            return true;
        }
        foreach ($validator->getMessages() as $message) {
            $this->_getSession()->addError($message);
        }
        return false;
    }

    #[\Override]
    protected function getInputTypeModels(string $input, Mage_Eav_Model_Entity_Attribute $model): array
    {
        /** @var Mage_Catalog_Helper_Product $helper */
        $helper = Mage::helper('catalog/product');
        return [
            'source_model' => $helper->getAttributeSourceModelByInputType($input),
            'backend_model' => $helper->getAttributeBackendModelByInputType($input),
            'frontend_model' => $helper->getAttributeFrontendModelByInputType($input),
        ];
    }

    #[\Override]
    protected function prepareSaveData(array $data, Mage_Eav_Model_Entity_Attribute $model): array
    {
        $data['is_configurable'] ??= 0;
        $data['is_filterable'] ??= 0;
        $data['is_filterable_multiple'] ??= 0;
        $data['is_filterable_in_search'] ??= 0;
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
