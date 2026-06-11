<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Rss
 */

class Mage_Rss_IndexController extends Mage_Rss_Controller_Abstract
{
    /**
     * Current wishlist
     *
     * @var Mage_Wishlist_Model_Wishlist|null
     */
    protected $_wishlist;

    /**
     * Current customer
     *
     * @var Mage_Customer_Model_Customer|null
     */
    protected $_customer;

    /**
     * Index action
     */
    #[Maho\Config\Route('/rss', name: 'rss.index', methods: ['GET'])]
    public function indexAction(): void
    {
        /** @var Mage_Rss_Helper_Data $helper */
        $helper = Mage::helper('rss');
        if ($helper->isRssEnabled()) {
            $this->loadLayout();
            $this->renderLayout();
        } else {
            $this->getResponse()->setHeader('HTTP/1.1', '404 Not Found');
            $this->getResponse()->setHeader('Status', '404 File not found');
            $this->_forward('defaultNoRoute');
        }
    }

    /**
     * Display feed not found message
     */
    #[Maho\Config\Route('/rss/index/nofeed', name: 'rss.index.nofeed', methods: ['GET'])]
    public function nofeedAction(): void
    {
        $this->getResponse()->setHeader('HTTP/1.1', '404 Not Found');
        $this->getResponse()->setHeader('Status', '404 File not found');
        $this->loadLayout(false);
        $this->renderLayout();
    }

    /**
     * Wishlist rss feed action
     * Show all public wishlists and private wishlists that belong to current user
     */
    #[Maho\Config\Route('/rss/index/wishlist', name: 'rss.index.wishlist', methods: ['GET'])]
    public function wishlistAction(): void
    {
        if (!$this->isFeedEnable('wishlist/active')) {
            $this->_forward('nofeed', 'index', 'rss');
            return;
        }

        $wishlist = $this->_getWishlist();
        if (!$wishlist) {
            $this->_forward('nofeed', 'index', 'rss');
            return;
        }

        if ($wishlist->getVisibility()) {
            $this->_showWishlistRss();
            return ;
        }

        if (Mage::getSingleton('customer/session')->authenticate($this)
            && $wishlist->getCustomerId() == $this->_getCustomer()->getId()
        ) {
            $this->_showWishlistRss();
        } else {
            $this->_forward('nofeed', 'index', 'rss');
        }
    }

    /**
     * Show wishlist rss
     */
    protected function _showWishlistRss()
    {
        $this->getResponse()->setHeader('Content-type', 'text/xml; charset=UTF-8');
        $this->loadLayout(false);
        $this->renderLayout();
    }

    /**
     * Retrieve Wishlist model
     *
     * @return Mage_Wishlist_Model_Wishlist|null
     */
    protected function _getWishlist()
    {
        if (is_null($this->_wishlist)) {
            $this->_wishlist = Mage::getModel('wishlist/wishlist');
            $wishlistId = $this->getRequest()->getParam('wishlist_id');
            if ($wishlistId) {
                $this->_wishlist->load($wishlistId);
            } else {
                if ($this->_getCustomer()->getId()) {
                    $this->_wishlist->loadByCustomer($this->_getCustomer());
                }
            }
        }
        return $this->_wishlist;
    }

    /**
     * Retrieve Customer instance
     *
     * @return Mage_Customer_Model_Customer
     */
    protected function _getCustomer()
    {
        if (is_null($this->_customer)) {
            $this->_customer = Mage::getModel('customer/customer');

            $params = Mage::helper('core')->urlDecode($this->getRequest()->getParam('data'));
            $data   = explode(',', $params);
            $customerId    = abs((int) $data[0]);
            if ($customerId && ($customerId == Mage::getSingleton('customer/session')->getCustomerId())) {
                $this->_customer->load($customerId);
            }
        }

        return $this->_customer;
    }
}
