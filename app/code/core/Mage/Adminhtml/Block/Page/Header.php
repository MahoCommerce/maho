<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_Block_Page_Header extends Mage_Adminhtml_Block_Template
{
    public function __construct()
    {
        parent::__construct();
        $this->setTemplate('page/header.phtml');
    }

    /**
     * @return string
     */
    public function getHomeLink()
    {
        return $this->getUrl('adminhtml');
    }

    /**
     * @return Mage_Admin_Model_User
     */
    public function getUser()
    {
        return Mage::getSingleton('admin/session')->getUser();
    }

    /**
     * @return string
     */
    public function getLogoutLink()
    {
        return $this->getUrl('adminhtml/index/logout');
    }

    /**
     * Check if global search is enabled
     *
     * @return bool
     */
    public function displayGlobalSearch()
    {
        return Mage::getStoreConfigFlag('admin/global_search/enable')
            && Mage::getSingleton('admin/session')->isAllowed('admin/global_search');
    }
}
