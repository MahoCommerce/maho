<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Eav
 */

declare(strict_types=1);

/**
 * The editor of the groups and the attributes of an attribute set.
 *
 * It reads the set from the registry key current_attribute_set, and the entity type id from entityType.
 */
class Mage_Eav_Block_Adminhtml_Attribute_Set_Edit extends Mage_Adminhtml_Block_Template
{
    #[\Override]
    protected function _construct()
    {
        Mage::helper('core/js')->addTranslateData([
            'Cannot unassign system attribute',
            'Cannot delete group. Please move system attributes to another group and try again.',
            'The records of this set will move to the default set. Delete the set?',
        ], 'eav');

        $this->setTemplate('eav/attribute/set/edit.phtml');
        $this->setIsReadOnly(false);
    }

    #[\Override]
    protected function _prepareLayout()
    {
        $this->setChild(
            'edit_set_form',
            $this->getLayout()->createBlock('eav/adminhtml_attribute_set_edit_formset'),
        );

        $this->setChild(
            'delete_group_button',
            $this->getLayout()->createBlock('adminhtml/widget_button')->setData([
                'id'        => 'delete-group-button',
                'label'     => Mage::helper('eav')->__('Delete'),
                'class'     => 'delete',
                'disabled'  => true,
            ]),
        );

        $this->setChild(
            'rename_button',
            $this->getLayout()->createBlock('adminhtml/widget_button')->setData([
                'id'        => 'rename-group-button',
                'label'     => Mage::helper('eav')->__('Rename'),
                'disabled'  => true,
            ]),
        );

        $this->setChild(
            'add_group_button',
            $this->getLayout()->createBlock('adminhtml/widget_button')->setData([
                'id'        => 'add-group-button',
                'label'     => Mage::helper('eav')->__('Add'),
                'class'     => 'add',
            ]),
        );

        $this->setChild(
            'back_button',
            $this->getLayout()->createBlock('adminhtml/widget_button')->setData([
                'label'     => Mage::helper('eav')->__('Back'),
                'onclick'   => Mage::helper('core/js')->getSetLocationJs($this->getUrl('*/*/')),
                'class'     => 'back',
            ]),
        );

        $this->setChild(
            'reset_button',
            $this->getLayout()->createBlock('adminhtml/widget_button')->setData([
                'label'     => Mage::helper('eav')->__('Reset'),
                'onclick'   => 'window.location.reload()',
            ]),
        );

        $this->setChild(
            'save_button',
            $this->getLayout()->createBlock('adminhtml/widget_button')->setData([
                'id'        => 'save-button',
                'label'     => Mage::helper('eav')->__('Save'),
                'class'     => 'save',
            ]),
        );

        $this->setChild(
            'delete_button',
            $this->getLayout()->createBlock('adminhtml/widget_button')->setData([
                'id'        => 'delete-button',
                'label'     => Mage::helper('eav')->__('Delete'),
                'class'     => 'delete',
            ]),
        );

        return parent::_prepareLayout();
    }

    /**
     * Retrieve Attribute Set Edit Form HTML
     *
     * @return string
     */
    public function getSetFormHtml()
    {
        if ($this->getIsReadOnly()) {
            $this->getChild('edit_set_form')->setIsReadOnly(true);
        }
        return $this->getChildHtml('edit_set_form');
    }

    /**
     * Retrieve Block Header Text
     *
     * @return string
     */
    protected function _getHeader()
    {
        return Mage::helper('eav')->__("Edit Attribute Set '%s'", $this->_getAttributeSet()->getAttributeSetName());
    }

    /**
     * Retrieve Attribute Set Save URL
     */
    public function getSaveUrl(): string
    {
        return $this->getUrl('*/*/save', ['id' => $this->_getSetId()]);
    }

    /**
     * Retrieve Attribute Set Delete URL
     */
    public function getDeleteUrl(): string
    {
        return $this->getUrl('*/*/delete', ['id' => $this->_getSetId()]);
    }

    /**
     * Retrieve Attribute Set Group Tree as JSON format
     *
     * @return string
     */
    public function getGroupTreeJson()
    {
        $items = [];
        $setId = $this->_getSetId();

        /** @var Mage_Eav_Model_Resource_Entity_Attribute_Group_Collection $groups */
        $groups = Mage::getModel('eav/entity_attribute_group')
            ->getResourceCollection()
            ->setAttributeSetFilter($setId)
            ->setSortOrder()
            ->load();

        $configurable = $this->_getConfigurableAttributeIds();

        /** @var Mage_Eav_Model_Entity_Attribute_Group $node */
        foreach ($groups as $node) {
            $item = [
                'text'      => $node->getAttributeGroupName(),
                'id'        => $node->getAttributeGroupId(),
                'type'      => 'folder',
                'allowDrop' => true,
                'allowDrag' => true,
            ];

            $nodeChildren = $this->_getAttributeCollection()
                ->setAttributeGroupFilter($node->getId())
                ->load();

            if ($nodeChildren->getSize() > 0) {
                $item['children'] = [];
                /** @var Mage_Eav_Model_Entity_Attribute $child */
                foreach ($nodeChildren->getItems() as $child) {
                    $isUserDefined  = (bool) $child->getIsUserDefined();
                    $isConfigurable = in_array($child->getAttributeId(), $configurable);

                    $icon = match (true) {
                        !$isUserDefined => 'system-leaf',
                        $isConfigurable => 'configurable',
                        default => 'leaf',
                    };

                    $attr = [
                        'text'              => $child->getAttributeCode(),
                        'id'                => $child->getAttributeId(),
                        'cls'               => $icon,
                        'allowDrop'         => false,
                        'allowDrag'         => true,
                        'selectable'        => false,
                        'is_user_defined'   => $isUserDefined,
                        'is_configurable'   => $isConfigurable,
                        'entity_id'         => $child->getEntityAttributeId(),
                    ];

                    $item['children'][] = $attr;
                }
            }

            $items[] = $item;
        }

        return Mage::helper('core')->jsonEncode($items);
    }

    /**
     * Retrieve Unused in Attribute Set Attribute Tree as JSON
     *
     * @return string
     */
    public function getAttributeTreeJson()
    {
        $items = [];
        $setId = $this->_getSetId();

        $collection = $this->_getAttributeCollection()
            ->setAttributeSetFilter($setId)
            ->load();

        $attributesIds = ['0'];
        /** @var Mage_Eav_Model_Entity_Attribute $item */
        foreach ($collection->getItems() as $item) {
            $attributesIds[] = $item->getAttributeId();
        }

        $attributes = $this->_getAttributeCollection()
            ->setAttributesExcludeFilter($attributesIds)
            ->setOrder('attribute_code', 'asc')
            ->load();

        foreach ($attributes as $child) {
            $attr = [
                'text'              => $child->getAttributeCode(),
                'id'                => $child->getAttributeId(),
                'cls'               => 'leaf',
                'allowDrop'         => false,
                'allowDrag'         => true,
                'leaf'              => true,
                'is_user_defined'   => $child->getIsUserDefined(),
                'is_configurable'   => false,
                'entity_id'         => $child->getEntityId(),
            ];

            $items[] = $attr;
        }

        if (count($items) == 0) {
            $items[] = [
                'text'      => Mage::helper('eav')->__('Empty'),
                'id'        => 'empty',
                'cls'       => 'folder',
                'allowDrop' => false,
                'allowDrag' => false,
            ];
        }

        return Mage::helper('core')->jsonEncode($items);
    }

    /**
     * Retrieve Back Button HTML
     *
     * @return string
     */
    public function getBackButtonHtml()
    {
        return $this->getChildHtml('back_button');
    }

    /**
     * Retrieve Reset Button HTML
     *
     * @return string
     */
    public function getResetButtonHtml()
    {
        if ($this->getIsReadOnly()) {
            return '';
        }
        return $this->getChildHtml('reset_button');
    }

    /**
     * Retrieve Save Button HTML
     *
     * @return string
     */
    public function getSaveButtonHtml()
    {
        if ($this->getIsReadOnly()) {
            return '';
        }
        return $this->getChildHtml('save_button');
    }

    /**
     * Retrieve Delete Button HTML
     *
     * @return string
     */
    public function getDeleteButtonHtml()
    {
        if ($this->getIsCurrentSetDefault() || $this->getIsReadOnly()) {
            return '';
        }
        return $this->getChildHtml('delete_button');
    }

    /**
     * Retrieve Delete Group Button HTML
     *
     * @return string
     */
    public function getDeleteGroupButton()
    {
        return $this->getChildHtml('delete_group_button');
    }

    /**
     * Retrieve Add New Group Button HTML
     *
     * @return string
     */
    public function getAddGroupButton()
    {
        return $this->getChildHtml('add_group_button');
    }

    /**
     * Retrieve Rename Button HTML
     *
     * @return string
     */
    public function getRenameButton()
    {
        return $this->getChildHtml('rename_button');
    }

    /**
     * Retrieve current Attribute Set object
     *
     * @return Mage_Eav_Model_Entity_Attribute_Set
     */
    protected function _getAttributeSet()
    {
        return Mage::registry('current_attribute_set');
    }

    /**
     * Retrieve current attribute set Id
     *
     * @return int
     */
    protected function _getSetId()
    {
        return $this->_getAttributeSet()->getId();
    }

    /**
     * Check Current Attribute Set is a default
     *
     * @return bool
     */
    public function getIsCurrentSetDefault()
    {
        $isDefault = $this->getData('is_current_set_default');
        if (is_null($isDefault)) {
            $defaultSetId = Mage::getSingleton('eav/config')
                ->getEntityType(Mage::registry('entityType'))
                ->getDefaultAttributeSetId();
            $isDefault = $this->_getSetId() == $defaultSetId;
            $this->setData('is_current_set_default', $isDefault);
        }
        return $isDefault;
    }

    /**
     * Return a new collection of the attributes that the editor shows.
     */
    protected function _getAttributeCollection(): Mage_Eav_Model_Resource_Entity_Attribute_Collection
    {
        /** @var Mage_Eav_Model_Entity_Type $entityType */
        $entityType = Mage::getSingleton('eav/config')->getEntityType(Mage::registry('entityType'));
        return Mage::helper('eav')->getManagedAttributeCollection($entityType);
    }

    /**
     * Return the ids of the attributes that configurable products of the set use.
     *
     * @return list<int|string>
     */
    protected function _getConfigurableAttributeIds(): array
    {
        return [];
    }
}
