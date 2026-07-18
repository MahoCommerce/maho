<?php

/**
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_Permissions_Tab_Userroles extends Mage_Adminhtml_Block_Widget_Tabs
{
    public function __construct()
    {
        parent::__construct();

        $uid = $this->getRequest()->getParam('id', false);
        $uid = empty($uid) ? 0 : $uid;
        $roles = Mage::getModel('admin/roles')
            ->getCollection()
            ->load();

        $userRoles = Mage::getModel('admin/roles')
            ->getUsersCollection()
            ->setUserFilter($uid)
            ->load();

        $this->setTemplate('permissions/userroles.phtml')
            ->assign('roles', $roles)
            ->assign('user_roles', $userRoles);
    }
}
