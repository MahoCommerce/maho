<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_Block_Api_Tab_Userroles extends Mage_Adminhtml_Block_Widget_Tabs
{
    public function __construct()
    {
        parent::__construct();

        $uid = $this->getRequest()->getParam('id', false);
        $uid = empty($uid) ? 0 : $uid;
        $roles = Mage::getModel('api/roles')
            ->getCollection()
            ->load();

        $userRoles = Mage::getModel('api/roles')
            ->getUsersCollection()
            ->setUserFilter($uid)
            ->load();

        $this->setTemplate('api/userroles.phtml')
            ->assign('roles', $roles)
            ->assign('user_roles', $userRoles);
    }
}
