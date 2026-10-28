<?php

/**
 * Base controller for the admin pages that manage the attributes of one EAV entity type.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

declare(strict_types=1);

/**
 * A subclass gives the entity type, the ACL resource and the page titles. It declares each
 * action again with its #[Maho\Config\Route], because the route compiler skips abstract classes.
 */
abstract class Mage_Adminhtml_Controller_Eav_Attribute extends Mage_Adminhtml_Controller_Action
{
    protected ?Mage_Eav_Model_Entity_Type $entityType = null;

    protected string $gridContainerBlock = 'eav/adminhtml_attribute';

    /**
     * Return the code of the entity type that the controller manages, for example catalog_category.
     */
    abstract protected function getEntityTypeCode(): string;

    /**
     * Return the page titles, from the menu root to the grid page.
     *
     * @return list<string>
     */
    abstract protected function getPageTitles(): array;

    protected function getEntityType(): Mage_Eav_Model_Entity_Type
    {
        if ($this->entityType === null) {
            $this->entityType = Mage::getSingleton('eav/config')->getEntityType($this->getEntityTypeCode());
            Mage::unregister('current_entity_type');
            Mage::register('current_entity_type', $this->entityType);
        }
        return $this->entityType;
    }

    protected function createAttributeModel(): Mage_Eav_Model_Entity_Attribute
    {
        /** @var Mage_Eav_Model_Entity_Attribute $model */
        $model = Mage::getModel($this->getEntityType()->getAttributeModel());
        return $model->setEntityTypeId((int) $this->getEntityType()->getId());
    }

    /**
     * Return the HTML tags that an option label can keep when the attribute allows HTML on the storefront.
     *
     * @return list<string>
     */
    protected function getAllowedTags(): array
    {
        return [];
    }

    protected function _initAction(): static
    {
        $this->getEntityType();
        foreach ($this->getPageTitles() as $title) {
            $this->_title($title);
        }
        $this->loadLayout()->_setActiveMenu(static::ADMIN_RESOURCE);
        foreach ($this->getPageTitles() as $title) {
            $this->_addBreadcrumb($title, $title);
        }
        return $this;
    }

    public function indexAction(): void
    {
        $titles = $this->getPageTitles();
        $this->_initAction()
            ->_addContent($this->getLayout()->createBlock($this->gridContainerBlock)->setHeaderText(end($titles)))
            ->renderLayout();
    }

    public function newAction(): void
    {
        $this->_forward('edit');
    }

    public function editAction(): void
    {
        $id = (int) $this->getRequest()->getParam('attribute_id');
        $model = $this->createAttributeModel();
        if ($id) {
            $model->load($id);
            if (!$model->getId()) {
                $this->_getSession()->addError(Mage::helper('eav')->__('This attribute no longer exists.'));
                $this->_redirect('*/*/');
                return;
            }
            if ((int) $model->getEntityTypeId() !== (int) $this->getEntityType()->getId()) {
                $this->_getSession()->addError(Mage::helper('eav')->__('This attribute cannot be edited.'));
                $this->_redirect('*/*/');
                return;
            }
        }

        $data = $this->_getSession()->getAttributeData(true);
        if (!empty($data)) {
            $model->addData($data);
        }

        Mage::register('entity_attribute', $model);

        $this->_initAction();
        $label = $model->getFrontendLabel();
        $this->_title($id ? (string) (is_array($label) ? reset($label) : $label) : $this->__('New Attribute'));
        $this->prepareEditLayout();
        $this->renderLayout();
    }

    /**
     * Add the blocks of the edit page to the layout.
     */
    protected function prepareEditLayout(): void
    {
        $head = $this->getLayout()->getBlock('head');
        if ($head instanceof Mage_Page_Block_Html_Head) {
            $head->addJs('sortable.min.js')->addJs('mage/adminhtml/eav/attribute.js');
        }
        $this->_addLeft(
            $this->getLayout()->createBlock('eav/adminhtml_attribute_edit_tabs')
                ->setData('main_tab_block', $this->getMainTabBlock()),
        )->_addContent($this->getLayout()->createBlock('eav/adminhtml_attribute_edit'));
    }

    /**
     * Return the block type of the properties tab.
     */
    protected function getMainTabBlock(): string
    {
        return 'eav/adminhtml_attribute_edit_tab_main';
    }

    public function validateAction(): void
    {
        $response = new \Maho\DataObject(['error' => false]);

        $attribute = $this->createAttributeModel()
            ->loadByCode($this->getEntityType()->getId(), (string) $this->getRequest()->getParam('attribute_code'));

        if ($attribute->getId() && !$this->getRequest()->getParam('attribute_id')) {
            $this->_getSession()->addError(Mage::helper('eav')->__('Attribute with the same code already exists'));
            $this->_initLayoutMessages('adminhtml/session');
            $response->setError(true);
            $response->setMessage($this->getLayout()->getMessagesBlock()->getGroupedHtml());
        }

        $this->getResponse()->setBodyJson($response);
    }

    public function saveAction(): void
    {
        $data = $this->getRequest()->getPost();
        if (!$data) {
            $this->_redirect('*/*/');
            return;
        }

        $session = $this->_getSession();
        $id = (int) $this->getRequest()->getParam('attribute_id');
        $model = $this->createAttributeModel();

        // The entity type, the storage and the models come from the server, never from the form
        unset(
            $data['attribute_id'],
            $data['entity_type_id'],
            $data['backend_type'],
            $data['backend_model'],
            $data['source_model'],
            $data['frontend_model'],
        );

        if (isset($data['attribute_code'])
            && !Mage::helper('core')->isValidRegex($data['attribute_code'], '/^(?!event$)[a-z][a-z_0-9]{1,254}$/')
        ) {
            $session->addError(Mage::helper('eav')->__('Attribute code is invalid. Please use only letters (a-z), numbers (0-9) or underscore(_) in this field, first character should be a letter. Do not use "event" for an attribute code.'));
            $this->_redirect('*/*/edit', ['attribute_id' => $id, '_current' => true]);
            return;
        }

        if ($id) {
            $model->load($id);
            if (!$model->getId()) {
                $session->addError(Mage::helper('eav')->__('This attribute no longer exists.'));
                $this->_redirect('*/*/');
                return;
            }
            if ((int) $model->getEntityTypeId() !== (int) $this->getEntityType()->getId()) {
                $session->addError(Mage::helper('eav')->__('This attribute cannot be updated.'));
                $this->_redirect('*/*/');
                return;
            }
            $data['backend_model'] = $model->getBackendModel();
            $data['attribute_code'] = $model->getAttributeCode();
            $data['is_user_defined'] = $model->getIsUserDefined();
            $data['frontend_input'] = $model->getFrontendInput();
        } else {
            $input = (string) ($data['frontend_input'] ?? '');
            if (!$this->isValidInputType($input, $model)) {
                $session->addError(Mage::helper('eav')->__('Input type "%s" not found in the input types list.', $input));
                $session->setAttributeData($data);
                $this->_redirect('*/*/edit', ['_current' => true]);
                return;
            }
            $data = array_merge($data, $this->getInputTypeModels($input, $model));
        }

        if (!$model->getBackendType() && $model->getIsUserDefined() !== false) {
            $data['backend_type'] = $model->getBackendTypeByInput($data['frontend_input']);
        }

        $defaultValueField = $model->getDefaultValueByInput($data['frontend_input']);
        if ($defaultValueField) {
            $data['default_value'] = $this->getRequest()->getParam($defaultValueField);
        }

        $data = $this->prepareSaveData($this->filterPostData($data), $model);
        $model->addData($data);

        if (!$id) {
            $model->setEntityTypeId((int) $this->getEntityType()->getId());
            $model->setIsUserDefined(true);
        }

        $setId = (int) $this->getRequest()->getParam('set');
        $groupId = (int) $this->getRequest()->getParam('group');
        if ($setId && $groupId) {
            $model->setAttributeSetId($setId);
            $model->setAttributeGroupId($groupId);
        }

        try {
            $model->save();
            $this->afterSave($model, $data);
        } catch (Exception $e) {
            $session->addError($e->getMessage());
            $session->setAttributeData($data);
            $this->_redirect('*/*/edit', ['attribute_id' => $id, '_current' => true]);
            return;
        }

        $session->addSuccess($this->getSavedMessage());
        // Attribute labels are in the translation cache
        Mage::app()->cleanCache([Mage_Core_Model_Translate::CACHE_TAG]);
        $session->setAttributeData(false);
        $this->redirectAfterSave($model);
    }

    protected function isValidInputType(string $input, Mage_Eav_Model_Entity_Attribute $model): bool
    {
        return isset($model->getInputTypes()[$input]);
    }

    /**
     * Return the backend, source and frontend models of an input type.
     *
     * @return array<string, string|null>
     */
    protected function getInputTypeModels(string $input, Mage_Eav_Model_Entity_Attribute $model): array
    {
        $type = $model->getInputTypes()[$input] ?? [];
        return [
            'backend_model' => $type['backend_model'] ?? null,
            'source_model' => $type['source_model'] ?? null,
            'frontend_model' => $type['frontend_model'] ?? null,
        ];
    }

    /**
     * Set the values that a subclass needs before the attribute is saved.
     */
    protected function prepareSaveData(array $data, Mage_Eav_Model_Entity_Attribute $model): array
    {
        return $data;
    }

    /**
     * Save the values that the attribute model does not save itself.
     */
    protected function afterSave(Mage_Eav_Model_Entity_Attribute $model, array $data): void {}

    protected function getSavedMessage(): string
    {
        return Mage::helper('eav')->__('The attribute has been saved.');
    }

    protected function getDeletedMessage(): string
    {
        return Mage::helper('eav')->__('The attribute has been deleted.');
    }

    protected function redirectAfterSave(Mage_Eav_Model_Entity_Attribute $model): void
    {
        if ($this->getRequest()->getParam('back', false)) {
            $this->_redirect('*/*/edit', ['attribute_id' => $model->getId(), '_current' => true]);
            return;
        }
        $this->_redirect('*/*/');
    }

    /**
     * Strip the HTML tags from the labels and the option labels.
     */
    protected function filterPostData(array $data): array
    {
        $helper = Mage::helper('core');
        $data['frontend_label'] = (array) ($data['frontend_label'] ?? []);
        foreach ($data['frontend_label'] as &$label) {
            if ($label) {
                $label = $helper->stripTags($label);
            }
        }
        unset($label);

        if (!empty($data['option']['value']) && is_array($data['option']['value'])) {
            $allowedTags = !empty($data['is_html_allowed_on_front']) && $this->getAllowedTags()
                ? sprintf('<%s>', implode('><', $this->getAllowedTags())) : null;
            foreach ($data['option']['value'] as $key => $values) {
                foreach ($values as $storeId => $storeLabel) {
                    $data['option']['value'][$key][$storeId] = $helper->stripTags($storeLabel, $allowedTags);
                }
            }
        }
        return $data;
    }

    public function deleteAction(): void
    {
        $id = (int) $this->getRequest()->getParam('attribute_id');
        if (!$id) {
            $this->_getSession()->addError(Mage::helper('eav')->__('Unable to find an attribute to delete.'));
            $this->_redirect('*/*/');
            return;
        }

        $model = $this->createAttributeModel()->load($id);
        if ((int) $model->getEntityTypeId() !== (int) $this->getEntityType()->getId() || !$model->getIsUserDefined()) {
            $this->_getSession()->addError(Mage::helper('eav')->__('This attribute cannot be deleted.'));
            $this->_redirect('*/*/');
            return;
        }

        try {
            $model->delete();
            $this->_getSession()->addSuccess($this->getDeletedMessage());
            $this->_redirect('*/*/');
        } catch (Exception $e) {
            $this->_getSession()->addError($e->getMessage());
            $this->_redirect('*/*/edit', ['attribute_id' => $id]);
        }
    }
}
