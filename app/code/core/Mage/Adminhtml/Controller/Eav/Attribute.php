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
 * The pages under System > Attributes use one subclass for every entity type.
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
        return $this->getAttributeService()->newAttribute((string) $this->getEntityType()->getEntityTypeCode());
    }

    protected function getAttributeService(): Mage_Eav_Service_Attribute
    {
        /** @var Mage_Eav_Service_Attribute */
        return Mage::getService('eav/attribute');
    }

    /**
     * Load the attribute $id of the entity type of the page.
     *
     * @throws Mage_Core_Exception_NoSuchEntity
     */
    protected function loadAttribute(int $id): Mage_Eav_Model_Entity_Attribute
    {
        return $this->getAttributeService()->getById($id, (string) $this->getEntityType()->getEntityTypeCode());
    }

    /**
     * Return the path of the menu entry that the pages mark as active.
     */
    protected function getActiveMenu(): string
    {
        return static::ADMIN_RESOURCE;
    }

    protected function _initAction(): static
    {
        $this->getEntityType();
        foreach ($this->getPageTitles() as $title) {
            $this->_title($title);
        }
        $this->loadLayout()->_setActiveMenu($this->getActiveMenu());
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
        try {
            $model = $id ? $this->loadAttribute($id) : $this->createAttributeModel();
        } catch (Mage_Core_Exception_NoSuchEntity $e) {
            $this->_getSession()->addError($e->getMessage());
            $this->_redirect('*/*/');
            return;
        }
        if ($id && !$this->canEditAttribute($model)) {
            $this->_getSession()->addError(Mage::helper('eav')->__('This attribute cannot be edited.'));
            $this->_redirect('*/*/');
            return;
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
     * Return true when the edit and save actions accept $model, a saved attribute of the entity type.
     */
    protected function canEditAttribute(Mage_Eav_Model_Entity_Attribute $model): bool
    {
        return true;
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
        $errors = [];
        try {
            $this->getAttributeService()->validate($this->buildAttribute($this->getRequest()->getPost()));
        } catch (Mage_Core_Exception_Input $e) {
            $errors = array_column($e->getErrors(), 'message');
        } catch (Mage_Core_Exception $e) {
            $errors = [$e->getMessage()];
        }

        if ($errors !== []) {
            foreach ($errors as $error) {
                $this->_getSession()->addError($error);
            }
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
        try {
            $model = $this->buildAttribute($data);
            $this->getAttributeService()->save($model);
            $model->saveAdminData($data);
        } catch (Mage_Core_Exception_NoSuchEntity|Mage_Core_Exception_Forbidden $e) {
            $session->addError($e->getMessage());
            $this->_redirect('*/*/');
            return;
        } catch (Mage_Core_Exception $e) {
            if ($e instanceof Mage_Core_Exception_Input) {
                foreach ($e->getErrors() as $error) {
                    $session->addError($error['message']);
                }
            } else {
                $session->addError($e->getMessage());
            }
            $session->setAttributeData($data);
            $this->_redirect('*/*/edit', ['attribute_id' => $id, '_current' => true]);
            return;
        } catch (Throwable $e) {
            Mage::logException($e);
            $session->addError(Mage::helper('eav')->__('Unable to save the attribute.'));
            $session->setAttributeData($data);
            $this->_redirect('*/*/edit', ['attribute_id' => $id, '_current' => true]);
            return;
        }

        $session->addSuccess($this->getSavedMessage());
        $session->setAttributeData(false);
        $this->redirectAfterSave($model);
    }

    /**
     * Return the attribute of the attribute_id parameter, or a new attribute, with the values of the posted form.
     *
     * @throws Mage_Core_Exception_NoSuchEntity when the attribute is not an attribute of the entity type of the page
     * @throws Mage_Core_Exception_Forbidden when the page does not manage the attribute
     */
    protected function buildAttribute(array $data): Mage_Eav_Model_Entity_Attribute
    {
        $id = (int) $this->getRequest()->getParam('attribute_id');
        $model = $id ? $this->loadAttribute($id) : $this->createAttributeModel();
        if ($id && !$this->canEditAttribute($model)) {
            throw new Mage_Core_Exception_Forbidden(Mage::helper('eav')->__('This attribute cannot be updated.'));
        }

        // The entity type, the storage and the models come from the server, never from the form
        unset(
            $data['attribute_id'],
            $data['attribute_set_id'],
            $data['attribute_group_id'],
            $data['entity_type_id'],
            $data['backend_type'],
            $data['backend_table'],
            $data['backend_model'],
            $data['source_model'],
            $data['frontend_model'],
            $data['attribute_model'],
            $data['data_model'],
            $data['is_user_defined'],
        );

        $input = $id ? (string) $model->getFrontendInput() : (string) ($data['frontend_input'] ?? '');
        $defaultValueField = $model->getDefaultValueByInput($input);
        if ($defaultValueField) {
            $data['default_value'] = $this->getRequest()->getParam($defaultValueField);
        }
        $data['frontend_label'] = (array) ($data['frontend_label'] ?? []);

        $model->addData($this->prepareSaveData($model->prepareAdminData($data), $model));

        $setId = (int) $this->getRequest()->getParam('set');
        $groupId = (int) $this->getRequest()->getParam('group');
        if ($setId && $groupId
            && (int) Mage::getModel('eav/entity_attribute_set')->load($setId)->getEntityTypeId() === (int) $this->getEntityType()->getId()
            && (int) Mage::getModel('eav/entity_attribute_group')->load($groupId)->getAttributeSetId() === $setId
        ) {
            $model->setAttributeSetId($setId);
            $model->setAttributeGroupId($groupId);
        }
        return $model;
    }

    /**
     * Set the values that a subclass needs before the attribute is saved.
     */
    protected function prepareSaveData(array $data, Mage_Eav_Model_Entity_Attribute $model): array
    {
        return $data;
    }

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

    public function deleteAction(): void
    {
        $id = (int) $this->getRequest()->getParam('attribute_id');
        if (!$id) {
            $this->_getSession()->addError(Mage::helper('eav')->__('Unable to find an attribute to delete.'));
            $this->_redirect('*/*/');
            return;
        }

        try {
            $this->getAttributeService()->delete($this->loadAttribute($id));
        } catch (Mage_Core_Exception_NoSuchEntity $e) {
            $this->_getSession()->addError($e->getMessage());
            $this->_redirect('*/*/');
            return;
        } catch (Mage_Core_Exception $e) {
            $this->_getSession()->addError($e->getMessage());
            $this->_redirect('*/*/edit', ['attribute_id' => $id]);
            return;
        } catch (Throwable $e) {
            Mage::logException($e);
            $this->_getSession()->addError(Mage::helper('eav')->__('Unable to delete the attribute.'));
            $this->_redirect('*/*/edit', ['attribute_id' => $id]);
            return;
        }
        $this->_getSession()->addSuccess($this->getDeletedMessage());
        $this->_redirect('*/*/');
    }
}
