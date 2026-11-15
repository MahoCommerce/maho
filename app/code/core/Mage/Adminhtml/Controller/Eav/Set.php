<?php

/**
 * Base controller for the admin pages that manage the attribute sets of one EAV entity type.
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
abstract class Mage_Adminhtml_Controller_Eav_Set extends Mage_Adminhtml_Controller_Action
{
    protected ?Mage_Eav_Model_Entity_Type $entityType = null;

    protected string $toolbarBlock = 'eav/adminhtml_attribute_set_toolbar_main';

    protected string $gridBlock = 'eav/adminhtml_attribute_set_grid';

    protected string $addBlock = 'eav/adminhtml_attribute_set_toolbar_add';

    protected string $editBlock = 'eav/adminhtml_attribute_set_edit';

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
            // The set blocks read the entity type id from this key
            Mage::unregister('entityType');
            Mage::register('entityType', (int) $this->entityType->getId());
        }
        return $this->entityType;
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

    /**
     * Return false when every record of the type uses the default set. The pages then edit that set,
     * and cannot add or delete a set.
     */
    protected function canManageSets(): bool
    {
        return true;
    }

    public function indexAction(): void
    {
        if (!$this->canManageSets()) {
            $this->_redirect('*/*/edit', ['id' => (int) $this->getEntityType()->getDefaultAttributeSetId()]);
            return;
        }
        $titles = $this->getPageTitles();
        $this->_initAction()
            ->_addContent($this->getLayout()->createBlock($this->toolbarBlock)->setHeaderText(end($titles)))
            ->_addContent($this->getLayout()->createBlock($this->gridBlock))
            ->renderLayout();
    }

    public function setGridAction(): void
    {
        $this->getEntityType();
        $this->getResponse()->setBody(
            $this->getLayout()->createBlock($this->gridBlock)->toHtml(),
        );
    }

    public function addAction(): void
    {
        if (!$this->canManageSets()) {
            $this->_redirect('*/*/index');
            return;
        }
        $this->_initAction()->_title($this->__('New Set'));
        $this->_addContent($this->getLayout()->createBlock($this->addBlock));
        $this->renderLayout();
    }

    public function editAction(): void
    {
        $attributeSet = $this->loadAttributeSet((int) $this->getRequest()->getParam('id'));
        if (!$attributeSet) {
            $this->_redirect('*/*/index');
            return;
        }

        Mage::register('current_attribute_set', $attributeSet);

        $this->_initAction()->_title($attributeSet->getAttributeSetName());
        $head = $this->getLayout()->getBlock('head');
        if ($head instanceof Mage_Page_Block_Html_Head) {
            $head->addJs('sortable.min.js')->addJs('maho-tree.js')->addJs('mage/adminhtml/eav/set.js');
        }
        $this->_addContent($this->getLayout()->createBlock($this->editBlock));
        $this->renderLayout();
    }

    /**
     * Load a set of the entity type of the controller, or return null.
     */
    protected function loadAttributeSet(int $setId): ?Mage_Eav_Model_Entity_Attribute_Set
    {
        try {
            return $this->setService()->getById($setId, (string) $this->getEntityType()->getEntityTypeCode());
        } catch (Mage_Core_Exception_NoSuchEntity) {
            return null;
        }
    }

    /**
     * Save a set.
     *
     * [POST] Create a set from another set, then redirect to its edit page.
     * [AJAX] Save the groups and the attributes of a set.
     */
    public function saveAction(): void
    {
        $isNewSet = $this->getRequest()->getParam('gotoEdit', false) == '1';
        $service = $this->setService();
        $entityTypeCode = (string) $this->getEntityType()->getEntityTypeCode();

        try {
            if ($isNewSet) {
                $set = $service->newSet($entityTypeCode)
                    ->setAttributeSetName((string) $this->getRequest()->getParam('attribute_set_name'));
                $service->save($set, (int) $this->getRequest()->getParam('skeleton_set'));
                $this->_getSession()->addSuccess(Mage::helper('eav')->__('The attribute set has been saved.'));
                $this->_redirect('*/*/edit', ['id' => $set->getId()]);
                return;
            }

            $set = $service->getById((int) $this->getRequest()->getParam('id'), $entityTypeCode);
            $tree = Mage::helper('core')->jsonDecode((string) $this->getRequest()->getPost('data'));
            if (!$this->getRequest()->getPost('confirmed')) {
                $question = Mage::helper('eav')->getSetRemoveQuestion($set, $service->getRemovedAttributes($set, $tree));
                if ($question !== null) {
                    $this->getResponse()->setBodyJson(['confirm' => $question]);
                    return;
                }
            }
            $service->saveTree($set, $tree);
            $this->_getSession()->addSuccess(Mage::helper('eav')->__('The attribute set has been saved.'));
            $this->getResponse()->setBodyJson(['url' => $this->getUrl('*/*/')]);
            return;
        } catch (Mage_Core_Exception_Input $e) {
            $errors = array_column($e->getErrors(), 'message');
        } catch (Mage_Core_Exception $e) {
            $errors = [$e->getMessage()];
        } catch (Throwable $e) {
            Mage::logException($e);
            $errors = [Mage::helper('eav')->__('An error occurred while saving the attribute set.')];
        }

        if ($isNewSet) {
            foreach ($errors as $error) {
                $this->_getSession()->addError($error);
            }
            $this->_redirect('*/*/add');
        } else {
            $this->getResponse()->setBodyJson(['error' => true, 'message' => implode("\n", $errors)]);
        }
    }

    public function deleteAction(): void
    {
        $service = $this->setService();
        try {
            $service->delete($service->getById(
                (int) $this->getRequest()->getParam('id'),
                (string) $this->getEntityType()->getEntityTypeCode(),
            ));
            $this->_getSession()->addSuccess($this->__('The attribute set has been removed.'));
            $this->getResponse()->setRedirect($this->getUrl('*/*/'));
            return;
        } catch (Mage_Core_Exception $e) {
            $this->_getSession()->addError($e->getMessage());
        } catch (Throwable $e) {
            Mage::logException($e);
            $this->_getSession()->addError($this->__('An error occurred while deleting this set.'));
        }
        $this->_redirectReferer();
    }

    protected function setService(): Mage_Eav_Service_Attribute_Set
    {
        return Mage::getService('eav/attribute_set');
    }

    /**
     * @throws Mage_Core_Exception_Conflict
     */
    #[\Deprecated('Mage_Eav_Service_Attribute_Set::saveTree() makes this check')]
    protected function checkSystemAttributes(Mage_Eav_Model_Entity_Attribute_Set $set, array $data): void
    {
        $this->setService()->getRemovedAttributes($set, $data);
    }

    /**
     * @return list<Mage_Eav_Model_Entity_Attribute_Abstract>
     */
    #[\Deprecated('Use Mage_Eav_Service_Attribute_Set::getRemovedAttributes()')]
    protected function getRemovedAttributes(Mage_Eav_Model_Entity_Attribute_Set $set, array $data): array
    {
        return $this->setService()->getRemovedAttributes($set, $data);
    }

    #[\Deprecated('Mage_Eav_Service_Attribute_Set::delete() moves the records of the set')]
    protected function beforeSetDelete(Mage_Eav_Model_Entity_Attribute_Set $attributeSet): void {}
}
