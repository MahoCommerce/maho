<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Downloadable
 */

class Mage_Downloadable_CustomerController extends Mage_Core_Controller_Front_Action
{
    /**
     * Check customer authentication
     */
    #[\Override]
    public function preDispatch()
    {
        parent::preDispatch();
        if (!Mage::getStoreConfigFlag('customer/account/enabled_in_frontend')) {
            $this->norouteAction();
            $this->setFlag('', self::FLAG_NO_DISPATCH, true);
            return $this;
        }

        $loginUrl = Mage::helper('customer')->getLoginUrl();

        if (!Mage::getSingleton('customer/session')->authenticate($this, $loginUrl)) {
            $this->setFlag('', self::FLAG_NO_DISPATCH, true);
        }
        return $this;
    }

    /**
     * Display downloadable links bought by customer
     */
    #[Maho\Config\Route('/downloadable/customer/products', name: 'downloadable.customer.products', methods: ['GET'])]
    public function productsAction(): void
    {
        $this->loadLayout();
        $this->_initLayoutMessages('customer/session');
        if ($block = $this->getLayout()->getBlock('downloadable_customer_products_list')) {
            $block->setRefererUrl($this->_getRefererUrl());
        }
        $headBlock = $this->getLayout()->getBlock('head');
        if ($headBlock) {
            $headBlock->setTitle(Mage::helper('downloadable')->__('My Downloadable Products'));
        }
        $this->renderLayout();
    }
}
