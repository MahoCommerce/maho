<?php

/**
 * Maho
 *
 * @package    Mage_Api2
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Block for rendering attributes tree list tab
 *
 * @method Mage_Api2_Model_Acl_Global_Role getRole()
 * @method $this setRole(Mage_Api2_Model_Acl_Global_Role $role)
 */
class Mage_Api2_Block_Adminhtml_Attribute_Tab_Resource extends Mage_Adminhtml_Block_Widget_Form implements Mage_Adminhtml_Block_Widget_Tab_Interface
{
    /**
     * @var Mage_Api2_Model_Acl_Global_Rule_Tree
     */
    protected $_treeModel = false;

    public function __construct()
    {
        parent::__construct();

        $this->setId('api2_attribute_section_resources')
                ->setData('default_dir', Varien_Db_Select::SQL_ASC)
                ->setData('default_sort', 'sort_order')
                ->setData('title', $this->__('Attribute Rules Information'))
                ->setData('use_ajax', true);

        $this->_treeModel = Mage::getModel(
            'api2/acl_global_rule_tree',
            ['type' => Mage_Api2_Model_Acl_Global_Rule_Tree::TYPE_ATTRIBUTE],
        );

        /** @var Mage_Api2_Model_Acl_Filter_Attribute_ResourcePermission $permissions */
        $permissions = Mage::getModel('api2/acl_filter_attribute_resourcePermission');
        $permissions->setFilterValue($this->getRequest()->getParam('type'));
        $this->_treeModel->setResourcesPermissions($permissions->getResourcesPermissions())
            ->setHasEntityOnlyAttributes($permissions->getHasEntityOnlyAttributes());
    }

    /**
     * Get Json Representation of Resource Tree
     *
     * @return string
     */
    public function getResTreeJson()
    {
        /** @var Mage_Core_Helper_Data $helper */
        $helper = Mage::helper('core');
        return $helper->jsonEncode($this->_treeModel->getTreeResources());
    }

    /**
     * Check if everything is allowed
     *
     * @return bool
     */
    public function getEverythingAllowed()
    {
        return $this->_treeModel->getEverythingAllowed();
    }

    /**
     * Check if tree has entity only attributes
     *
     * @return bool
     */
    public function hasEntityOnlyAttributes()
    {
        return $this->_treeModel->getHasEntityOnlyAttributes();
    }

    /**
     * Get tab label
     *
     * @return string
     */
    #[\Override]
    public function getTabLabel()
    {
        return $this->__('ACL Attribute Rules');
    }

    /**
     * Get tab title
     *
     * @return string
     */
    #[\Override]
    public function getTabTitle()
    {
        return $this->getTabLabel();
    }

    /**
     * Whether tab is available
     *
     * @return bool
     */
    #[\Override]
    public function canShowTab()
    {
        return true;
    }

    /**
     * Whether tab is hidden
     *
     * @return bool
     */
    #[\Override]
    public function isHidden()
    {
        return false;
    }
}
