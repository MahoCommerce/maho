<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_PromoController extends Mage_Adminhtml_Controller_Action
{
    /**
     * ACL resource
     * @see Mage_Adminhtml_Controller_Action::_isAllowed()
     */
    public const ADMIN_RESOURCE = 'promo';

    public function indexAction()
    {
        $this->loadLayout();
        $this->_setActiveMenu('promo');
        $this->_addBreadcrumb(Mage::helper('adminhtml')->__('Promotions'), Mage::helper('adminhtml')->__('Promo'));
        $this->renderLayout();
    }
}
