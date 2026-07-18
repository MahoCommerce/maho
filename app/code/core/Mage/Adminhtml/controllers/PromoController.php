<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_PromoController extends Mage_Adminhtml_Controller_Action
{
    /**
     * ACL resource
     * @see Mage_Adminhtml_Controller_Action::_isAllowed()
     */
    public const ADMIN_RESOURCE = 'promo';

    #[Maho\Config\Route('/admin/promo/index')]
    public function indexAction(): void
    {
        $this->loadLayout();
        $this->_setActiveMenu('promo');
        $this->_addBreadcrumb(Mage::helper('adminhtml')->__('Promotions'), Mage::helper('adminhtml')->__('Promo'));
        $this->renderLayout();
    }
}
