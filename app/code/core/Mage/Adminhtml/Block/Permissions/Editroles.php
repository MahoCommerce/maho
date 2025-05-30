<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_Block_Permissions_Editroles extends Mage_Adminhtml_Block_Widget_Tabs
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('role_info_tabs');
        $this->setDestElementId('role_edit_form');
        $this->setTitle(Mage::helper('adminhtml')->__('Role Information'));
    }

    #[\Override]
    protected function _prepareLayout()
    {
        $role = Mage::registry('current_role');

        $this->addTab('info', $this->getLayout()->createBlock('adminhtml/permissions_tab_roleinfo')->setRole($role)->setActive(true));
        $this->addTab('account', $this->getLayout()->createBlock('adminhtml/permissions_tab_rolesedit', 'adminhtml.permissions.tab.rolesedit'));

        if ($role->getId()) {
            $this->addTab('roles', [
                'label'     => Mage::helper('adminhtml')->__('Role Users'),
                'title'     => Mage::helper('adminhtml')->__('Role Users'),
                'content'   => $this->getLayout()->createBlock('adminhtml/permissions_tab_rolesusers', 'role.users.grid')->toHtml(),
            ]);
        }

        return parent::_prepareLayout();
    }
}
