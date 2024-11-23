<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Eav
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Adminhtml Attribute Set Main Block
 *
 * @category    Mage
 * @package     Mage_Eav
 */
class Mage_Eav_Block_Adminhtml_Attribute_Set_Edit extends Mage_Adminhtml_Block_Template
{
    protected Mage_Eav_Model_Entity_Type $entityType;
    protected Mage_Eav_Model_Entity_Attribute_Set $attributeSet;

    protected function __construct()
    {
        $this->entityType = Mage::registry('entity_type');
        $this->attributeSet = Mage::registry('attribute_set');
        $this->setTemplate('eav/attribute/set/main.phtml');
    }

    /**
     * Prepare Global Layout
     *
     * @return $this
     */
    #[\Override]
    protected function _prepareLayout()
    {
        $setId = $this->_getSetId();

        $this->setChild(
            'delete_group_button',
            $this->getLayout()->createBlock('adminhtml/widget_button')->setData([
                'label'     => Mage::helper('eav')->__('Delete Selected Group'),
                'onclick'   => 'editSet.submit();',
                'class'     => 'delete'
            ])
        );

        $this->setChild(
            'add_group_button',
            $this->getLayout()->createBlock('adminhtml/widget_button')->setData([
                'label'     => Mage::helper('eav')->__('Add New'),
                'onclick'   => 'editSet.addGroup();',
                'class'     => 'add'
            ])
        );

        $this->setChild(
            'back_button',
            $this->getLayout()->createBlock('adminhtml/widget_button')->setData([
                'label'     => Mage::helper('eav')->__('Back'),
                'onclick'   => Mage::helper('core/js')->getSetLocationJs($this->getUrl('*/*/')),
                'class'     => 'back'
            ])
        );

        $this->setChild(
            'reset_button',
            $this->getLayout()->createBlock('adminhtml/widget_button')->setData([
                'label'     => Mage::helper('eav')->__('Reset'),
                'onclick'   => 'window.location.reload()'
            ])
        );

        $this->setChild(
            'save_button',
            $this->getLayout()->createBlock('adminhtml/widget_button')->setData([
                'label'     => Mage::helper('eav')->__('Save Attribute Set'),
                'onclick'   => 'editSet.save();',
                'class'     => 'save'
            ])
        );

        $this->setChild(
            'delete_button',
            $this->getLayout()->createBlock('adminhtml/widget_button')->setData([
                'label'     => Mage::helper('eav')->__('Delete Attribute Set'),
                'onclick'   => Mage::helper('core/js')->getDeleteConfirmJs(
                    $this->getUrlSecure('*/*/delete', ['id' => $setId]),
                    Mage::helper('eav')->__('Are you sure you want to delete this attribute set?')
                ),
                'class'     => 'delete'
            ])
        );

        $this->setChild(
            'rename_button',
            $this->getLayout()->createBlock('adminhtml/widget_button')->setData([
                'label'     => Mage::helper('eav')->__('New Set Name'),
                'onclick'   => 'editSet.rename()'
            ])
        );

        return parent::_prepareLayout();
    }

    /**
     * Retrieve Attribute Set Group Tree HTML
     *
     * @return string
     */
    public function getGroupTreeHtml()
    {
        return $this->getChildHtml('group_tree');
    }

    /**
     * Retrieve Attribute Set Edit Form HTML
     *
     * @return string
     */
    public function getSetFormHtml()
    {
        return $this->getChildHtml('set_form');
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
     *
     * @return string
     */
    public function getMoveUrl()
    {
        return $this->getUrl('*/*/save', ['id' => $this->_getSetId()]);
    }

    /**
     * Retrieve Attribute Set Group Save URL
     *
     * @return string
     */
    public function getGroupUrl()
    {
        return $this->getUrl('*/*/save', ['id' => $this->_getSetId()]);
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

        /* @var $groups Mage_Eav_Model_Mysql4_Entity_Attribute_Group_Collection */
        $groups = Mage::getModel('eav/entity_attribute_group')
            ->getResourceCollection()
            ->setAttributeSetFilter($setId)
            ->setSortOrder()
            ->load();

        // Get global/eav_attributes/$entityType/$attributeCode/hidden config.xml nodes
        $hiddenAttributes = Mage::helper('eav')->getHiddenAttributes($this->entityType->getEntityTypeCode());

        /* @var $node Mage_Eav_Model_Entity_Attribute_Group */
        foreach ($groups as $node) {
            $item = [];
            $item['text']       = $node->getAttributeGroupName();
            $item['id']         = $node->getAttributeGroupId();
            $item['cls']        = 'folder';
            $item['allowDrop']  = true;
            $item['allowDrag']  = true;

            /** @var Mage_Eav_Model_Entity_Attribute $nodeChildren */
            $nodeChildren = Mage::getResourceModel($this->entityType->getEntityAttributeCollection());
            $nodeChildren->setEntityTypeFilter($this->entityType->getEntityTypeId())
                         ->setNotCodeFilter($hiddenAttributes)
                         ->setAttributeGroupFilter($node->getId())
                         ->load();

            if ($nodeChildren->getSize() > 0) {
                $item['children'] = [];
                foreach ($nodeChildren->getItems() as $child) {
                    /* @var $child Mage_Eav_Model_Entity_Attribute */
                    $attr = [
                        'text'              => $child->getAttributeCode(),
                        'id'                => $child->getAttributeId(),
                        'cls'               => (!$child->getIsUserDefined()) ? 'system-leaf' : 'leaf',
                        'allowDrop'         => false,
                        'allowDrag'         => true,
                        'leaf'              => true,
                        'is_user_defined'   => $child->getIsUserDefined(),
                        'entity_id'         => $child->getEntityAttributeId()
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

        /** @var Mage_Eav_Model_Resource_Entity_Attribute_Collection $collection */
        $collection = Mage::getResourceModel($this->entityType->getEntityAttributeCollection());
        $collection->setEntityTypeFilter($this->entityType->getEntityTypeId())
                   ->setAttributeSetFilter($setId)
                   ->load();

        $attributesIds = ['0'];
        /* @var $item Mage_Eav_Model_Entity_Attribute */
        foreach ($collection->getItems() as $item) {
            $attributesIds[] = $item->getAttributeId();
        }

        /** @var Mage_Eav_Model_Resource_Entity_Attribute_Collection $attributes */
        $attributes = Mage::getResourceModel($this->entityType->getEntityAttributeCollection());
        $attributes->setEntityTypeFilter($this->entityType->getEntityTypeId())
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
                'entity_id'         => $child->getEntityId()
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
        return $this->getChildHtml('reset_button');
    }

    /**
     * Retrieve Save Button HTML
     *
     * @return string
     */
    public function getSaveButtonHtml()
    {
        return $this->getChildHtml('save_button');
    }

    /**
     * Retrieve Delete Button HTML
     *
     * @return string
     */
    public function getDeleteButtonHtml()
    {
        if ($this->getIsCurrentSetDefault()) {
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
        return $this->attributeSet;
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
            $defaultSetId = $this->entityType->getDefaultAttributeSetId();
            $isDefault = $this->_getSetId() == $defaultSetId;
            $this->setData('is_current_set_default', $isDefault);
        }
        return $isDefault;
    }

    /**
     * Retrieve current Attribute Set object
     *
     * @deprecated use _getAttributeSet
     * @return Mage_Eav_Model_Entity_Attribute_Set
     */
    protected function _getSetData()
    {
        return $this->_getAttributeSet();
    }

    /**
     * Prepare HTML
     *
     * @return string
     */
    #[\Override]
    protected function _toHtml()
    {
        $entityTypeCode = $this->entityType->getEntityTypeCode();
        Mage::dispatchEvent("adminhtml_{$entityTypeCode}_attribute_set_main_html_before", ['block' => $this]);
        return parent::_toHtml();
    }
}
