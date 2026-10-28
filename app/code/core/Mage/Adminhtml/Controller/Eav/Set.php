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

    public function indexAction(): void
    {
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
        if (!$setId) {
            return null;
        }
        /** @var Mage_Eav_Model_Entity_Attribute_Set $attributeSet */
        $attributeSet = Mage::getModel('eav/entity_attribute_set')->load($setId);
        if (!$attributeSet->getId() || (int) $attributeSet->getEntityTypeId() !== (int) $this->getEntityType()->getId()) {
            return null;
        }
        return $attributeSet;
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

        /** @var Mage_Eav_Model_Entity_Attribute_Set $model */
        $model = Mage::getModel('eav/entity_attribute_set')
            ->setEntityTypeId((int) $this->getEntityType()->getId());

        $helper = Mage::helper('adminhtml');

        try {
            if ($isNewSet) {
                $name = $helper->stripTags($this->getRequest()->getParam('attribute_set_name'));
                $model->setAttributeSetName(trim($name));
                $skeleton = $this->loadAttributeSet((int) $this->getRequest()->getParam('skeleton_set'));
                if (!$skeleton) {
                    Mage::throwException(Mage::helper('eav')->__('This attribute set no longer exists.'));
                }
            } else {
                $model = $this->loadAttributeSet((int) $this->getRequest()->getParam('id'));
                if (!$model) {
                    Mage::throwException(Mage::helper('eav')->__('This attribute set no longer exists.'));
                }
                $data = $this->filterSetData($model, Mage::helper('core')->jsonDecode($this->getRequest()->getPost('data')));
                $data['attribute_set_name'] = $helper->stripTags($data['attribute_set_name']);
                $model->organizeData($data);
            }

            $model->validate();
            if ($isNewSet) {
                $model->save();
                $model->initFromSkeleton($skeleton->getId());
            }
            $model->save();
            $this->_getSession()->addSuccess(Mage::helper('eav')->__('The attribute set has been saved.'));

            if ($isNewSet) {
                $this->_redirect('*/*/edit', ['id' => $model->getId()]);
            } else {
                $this->getResponse()->setBodyJson(['url' => $this->getUrl('*/*/')]);
            }
            return;
        } catch (Mage_Core_Exception $e) {
            $error = $e->getMessage();
        } catch (Exception $e) {
            $error = Mage::helper('eav')->__('An error occurred while saving the attribute set.');
            Mage::logException($e);
        }

        if ($isNewSet) {
            $this->_getSession()->addError($error);
            $this->_redirect('*/*/add');
        } else {
            $this->getResponse()->setBodyJson(['error' => true, 'message' => $error]);
        }
    }

    /**
     * Remove the groups and the attributes of other sets and of other entity types from the posted data.
     */
    protected function filterSetData(Mage_Eav_Model_Entity_Attribute_Set $set, array $data): array
    {
        $resource = Mage::getSingleton('core/resource');
        $adapter = $resource->getConnection('core_read');
        $ids = fn(string $table, string $column, string $field, int $value): array => array_map(intval(...), $adapter->fetchCol(
            $adapter->select()->from($resource->getTableName($table), [$column])->where("$field = ?", $value),
        ));
        $groupIds = $ids('eav/attribute_group', 'attribute_group_id', 'attribute_set_id', (int) $set->getId());
        $rowIds = $ids('eav/entity_attribute', 'entity_attribute_id', 'attribute_set_id', (int) $set->getId());
        $attributeIds = $ids('eav/attribute', 'attribute_id', 'entity_type_id', (int) $set->getEntityTypeId());
        $keep = fn(string $key, callable $check): array => array_values(array_filter((array) ($data[$key] ?? []), $check));

        // A group id that is not a number is a group that the editor added
        $data['groups'] = $keep('groups', fn($group) => !is_numeric($group[0] ?? null) || in_array((int) $group[0], $groupIds, true));
        $data['attributes'] = $keep('attributes', fn($attribute) => in_array((int) ($attribute[0] ?? 0), $attributeIds, true));
        $data['not_attributes'] = $keep('not_attributes', fn($id) => in_array((int) $id, $rowIds, true));
        $data['removeGroups'] = $keep('removeGroups', fn($id) => in_array((int) $id, $groupIds, true));
        return $data;
    }

    public function deleteAction(): void
    {
        $attributeSet = $this->loadAttributeSet((int) $this->getRequest()->getParam('id'));
        if (!$attributeSet || (int) $attributeSet->getId() === (int) $this->getEntityType()->getDefaultAttributeSetId()) {
            $this->_getSession()->addError($this->__('This attribute set cannot be deleted.'));
            $this->_redirectReferer();
            return;
        }

        // The records leave the set only when the set goes too
        $adapter = Mage::getSingleton('core/resource')->getConnection('core_write');
        $adapter->beginTransaction();
        try {
            $this->beforeSetDelete($attributeSet);
            $attributeSet->delete();
            $adapter->commit();
        } catch (Exception $e) {
            $adapter->rollBack();
            Mage::logException($e);
            $this->_getSession()->addError($this->__('An error occurred while deleting this set.'));
            $this->_redirectReferer();
            return;
        }
        $this->_getSession()->addSuccess($this->__('The attribute set has been removed.'));
        $this->getResponse()->setRedirect($this->getUrl('*/*/'));
    }

    /**
     * Move the records of a set to the default set before the set is deleted.
     *
     * Only the product table deletes its rows with the set, through a foreign key.
     */
    protected function beforeSetDelete(Mage_Eav_Model_Entity_Attribute_Set $attributeSet): void
    {
        $table = $this->getEntityType()->getEntityTable();
        if (!$table) {
            return;
        }
        $resource = Mage::getSingleton('core/resource');
        $table = $resource->getTableName($table);
        $adapter = $resource->getConnection('core_write');
        if (!$adapter->tableColumnExists($table, 'attribute_set_id')) {
            return;
        }
        $adapter->update(
            $table,
            ['attribute_set_id' => (int) $this->getEntityType()->getDefaultAttributeSetId()],
            ['attribute_set_id = ?' => (int) $attributeSet->getId()],
        );
    }
}
