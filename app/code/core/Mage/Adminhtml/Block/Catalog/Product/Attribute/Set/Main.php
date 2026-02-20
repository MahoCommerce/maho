<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_Block_Catalog_Product_Attribute_Set_Main extends Mage_Adminhtml_Block_Template
{
    #[\Override]
    protected function _construct()
    {
        Mage::helper('core/js')->addTranslateData([
            'All products of this set will be deleted! Type "confirm" to proceed.',
            'Cannot delete group. Please move configurable attributes to another group and try again.',
            'Cannot unassign configurable attribute',
        ], 'catalog');

        $this->setTemplate('catalog/product/attribute/set/main.phtml');
        $this->setIsReadOnly(false);
    }

    #[\Override]
    protected function _prepareLayout()
    {
        $this->setChild(
            'edit_set_form',
            $this->getLayout()->createBlock('adminhtml/catalog_product_attribute_set_main_formset'),
        );

        $this->setChild(
            'delete_group_button',
            $this->getLayout()->createBlock('adminhtml/widget_button')->setData([
                'id'        => 'delete-group-button',
                'label'     => Mage::helper('catalog')->__('Delete'),
                'class'     => 'delete',
                'disabled'  => true,
            ]),
        );

        $this->setChild(
            'rename_button',
            $this->getLayout()->createBlock('adminhtml/widget_button')->setData([
                'id'        => 'rename-group-button',
                'label'     => Mage::helper('catalog')->__('Rename'),
                'disabled'  => true,
            ]),
        );

        $this->setChild(
            'add_group_button',
            $this->getLayout()->createBlock('adminhtml/widget_button')->setData([
                'id'        => 'add-group-button',
                'label'     => Mage::helper('catalog')->__('Add'),
                'class'     => 'add',
            ]),
        );

        $this->setChild(
            'back_button',
            $this->getLayout()->createBlock('adminhtml/widget_button')->setData([
                'label'     => Mage::helper('catalog')->__('Back'),
                'onclick'   => Mage::helper('core/js')->getSetLocationJs($this->getUrl('*/*/')),
                'class'     => 'back',
            ]),
        );

        $this->setChild(
            'reset_button',
            $this->getLayout()->createBlock('adminhtml/widget_button')->setData([
                'label'     => Mage::helper('catalog')->__('Reset'),
                'onclick'   => 'window.location.reload()',
            ]),
        );

        $this->setChild(
            'save_button',
            $this->getLayout()->createBlock('adminhtml/widget_button')->setData([
                'id'        => 'save-button',
                'label'     => Mage::helper('catalog')->__('Save Attribute Set'),
                'class'     => 'save',
            ]),
        );

        $this->setChild(
            'delete_button',
            $this->getLayout()->createBlock('adminhtml/widget_button')->setData([
                'id'        => 'delete-button',
                'label'     => Mage::helper('catalog')->__('Delete Attribute Set'),
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
        return Mage::helper('catalog')->__("Edit Attribute Set '%s'", $this->_getAttributeSet()->getAttributeSetName());
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

        $configurable = Mage::getResourceModel('catalog/product_type_configurable_attribute')
            ->getUsedAttributes($setId);

        /** @var Mage_Eav_Model_Entity_Attribute_Group $node */
        foreach ($groups as $node) {
            $item = [
                'text'      => $node->getAttributeGroupName(),
                'id'        => $node->getAttributeGroupId(),
                'type'      => 'folder',
                'allowDrop' => true,
                'allowDrag' => true,
            ];

            $nodeChildren = Mage::getResourceModel('catalog/product_attribute_collection')
                ->setAttributeGroupFilter($node->getId())
                ->addVisibleFilter()
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

        $collection = Mage::getResourceModel('catalog/product_attribute_collection')
            ->setAttributeSetFilter($setId)
            ->load();

        $attributesIds = ['0'];
        /** @var Mage_Eav_Model_Entity_Attribute $item */
        foreach ($collection->getItems() as $item) {
            $attributesIds[] = $item->getAttributeId();
        }

        $attributes = Mage::getResourceModel('catalog/product_attribute_collection')
            ->setAttributesExcludeFilter($attributesIds)
            ->addVisibleFilter()
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
                'text'      => Mage::helper('catalog')->__('Empty'),
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

    #[\Override]
    protected function _toHtml()
    {
        Mage::dispatchEvent('adminhtml_catalog_product_attribute_set_main_html_before', ['block' => $this]);
        return parent::_toHtml();
    }
}
