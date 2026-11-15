<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_Permissions_User_Edit_Tab_Roles extends Mage_Adminhtml_Block_Widget_Grid
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('permissionsUserRolesGrid');
        $this->setDefaultSort('sort_order');
        $this->setDefaultDir('asc');
        //$this->setDefaultFilter(array('assigned_user_role'=>1));
        $this->setTitle(Mage::helper('adminhtml')->__('User Roles Information'));
        $this->setUseAjax();
    }

    #[\Override]
    protected function _addColumnFilterToCollection($column)
    {
        if ($column->getId() == 'assigned_user_role') {
            $userRoles = $this->_getSelectedRoles();
            if (empty($userRoles)) {
                $userRoles = 0;
            }
            if ($column->getFilter()->getValue()) {
                $this->getCollection()->addFieldToFilter('role_id', ['in' => $userRoles]);
            } else {
                if ($userRoles) {
                    $this->getCollection()->addFieldToFilter('role_id', ['nin' => $userRoles]);
                }
            }
        } else {
            parent::_addColumnFilterToCollection($column);
        }
        return $this;
    }

    #[\Override]
    protected function _prepareCollection()
    {
        $collection = Mage::getResourceModel('admin/role_collection');
        $collection->setRolesFilter();
        $this->setCollection($collection);
        return parent::_prepareCollection();
    }

    #[\Override]
    protected function _prepareColumns()
    {
        $this->addColumn('assigned_user_role', [
            'header_css_class' => 'a-center',
            'header'    => Mage::helper('adminhtml')->__('Assigned'),
            'type'      => 'radio',
            'html_name' => 'roles[]',
            'values'    => $this->_getSelectedRoles(),
            'align'     => 'center',
            'index'     => 'role_id',
        ]);

        /*$this->addColumn('role_id', array(
            'header'    =>Mage::helper('adminhtml')->__('Role ID'),
            'index'     =>'role_id',
            'align'     => 'right',
            'width'    => '50px'
        ));*/

        $this->addColumn('role_name', [
            'header'    => Mage::helper('adminhtml')->__('Role Name'),
            'index'     => 'role_name',
        ]);

        return parent::_prepareColumns();
    }

    #[\Override]
    public function getGridUrl()
    {
        return $this->getUrl('*/*/rolesGrid', ['user_id' => Mage::registry('permissions_user')->getUserId()]);
    }

    public function _getSelectedRoles(): array
    {
        if ($this->getRequest()->getParam('user_roles') != '') {
            return (array) $this->getRequest()->getParam('user_roles');
        }
        /** @var Mage_Admin_Model_User $user */
        $user = Mage::registry('permissions_user');
        //checking if we have this data and we
        //don't need load it through resource model
        if ($user->hasData('roles')) {
            return (array) $user->getData('roles');
        }
        return $user->getRoles();
    }

    /**
     * Selected role IDs as a JSON object with the IDs as keys, for the grid script
     */
    public function getSelectedRolesAsJson(): string
    {
        return Mage::helper('core')->jsonEncode((object) array_fill_keys($this->_getSelectedRoles(), 0));
    }
}
