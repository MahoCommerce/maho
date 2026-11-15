<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Wishlist
 */

abstract class Mage_Wishlist_Controller_Abstract extends Mage_Core_Controller_Front_Action
{
    /**
     * Processes localized qty (entered by user at frontend) into internal php format
     *
     * @param string $qty
     * @return float|int|null
     */
    protected function _processLocalizedQty($qty)
    {
        $qty = (float) $qty;
        if ($qty < 0) {
            $qty = null;
        }
        return $qty;
    }

    /**
     * Retrieve current wishlist instance
     *
     * @return Mage_Wishlist_Model_Wishlist|false
     */
    abstract protected function _getWishlist();

    /**
     * Add all items from wishlist to shopping cart
     */
    public function allcartAction(): void
    {
        $wishlist   = $this->_getWishlist();
        if (!$wishlist) {
            $this->_forward('noRoute');
            return;
        }
        $isOwner    = $wishlist->isOwner(Mage::getSingleton('customer/session')->getCustomerId());

        $messages   = [];
        $addedItems = [];

        $service    = Mage::getService('wishlist/wishlist');
        $cart       = Mage::getSingleton('checkout/cart');
        $collection = $wishlist->getItemsCollection()
                ->setVisibilityFilter();

        $qtysString = $this->getRequest()->getParam('qty');
        if (isset($qtysString)) {
            $qtys = array_filter(json_decode($qtysString), fn($tmpString) => strlen($tmpString ?? ''));
        }

        /** @var Mage_Wishlist_Model_Item $item */
        foreach ($collection as $item) {
            try {
                $disableAddToCart = $item->getProduct()->getDisableAddToCart();
                $item->unsProduct();

                $qty = isset($qtys[$item->getId()]) ? $this->_processLocalizedQty($qtys[$item->getId()]) : null;
                $item->getProduct()->setDisableAddToCart($disableAddToCart);
                $service->moveToCart($wishlist, $item, $cart->getQuote(), $qty ?: null, keepItem: !$isOwner);
                $addedItems[] = $item->getProduct();
            } catch (Mage_Core_Exception $e) {
                $messages[] = $this->__('%s for "%s".', trim($e->getMessage(), '.'), $item->getProduct()->getName());

                $cartItem = $cart->getQuote()->getItemByProduct($item->getProduct());
                if ($cartItem) {
                    $cart->getQuote()->deleteItem($cartItem);
                }
            } catch (Exception $e) {
                Mage::logException($e);
                $messages[] = Mage::helper('wishlist')->__('Cannot add the item to shopping cart.');
            }
        }

        if ($isOwner) {
            $indexUrl = Mage::helper('wishlist')->getListUrl($wishlist->getId());
        } else {
            $indexUrl = Mage::getUrl('wishlist/shared', ['code' => $wishlist->getSharingCode()]);
        }
        if (Mage::helper('checkout/cart')->getShouldRedirectToCart()) {
            $redirectUrl = Mage::helper('checkout/cart')->getCartUrl();
        } elseif ($this->_getRefererUrl()) {
            $redirectUrl = $this->_getRefererUrl();
        } else {
            $redirectUrl = $indexUrl;
        }

        if ($messages) {
            $wishlistSession = Mage::getSingleton('wishlist/session');
            foreach ($messages as $message) {
                $wishlistSession->addError($message);
            }
            $redirectUrl = $indexUrl;
        }

        if ($addedItems) {
            $products = [];
            foreach ($addedItems as $product) {
                $products[] = '"' . $product->getName() . '"';
            }

            Mage::getSingleton('checkout/session')->addSuccess(
                Mage::helper('wishlist')->__('%d product(s) have been added to shopping cart: %s.', count($addedItems), implode(', ', $products)),
            );

            $cart->save();
            Mage::getSingleton('checkout/session')->setLastAddedProductId((int) end($addedItems)->getId());
        }

        Mage::helper('wishlist')->calculate();

        $this->_redirectUrl($redirectUrl);
    }
}
