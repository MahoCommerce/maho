<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2025 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Wishlist
 */

class Mage_Wishlist_IndexController extends Mage_Wishlist_Controller_Abstract
{
    /**
     * If true, authentication in this controller (wishlist) could be skipped
     *
     * @var bool
     */
    protected $_skipAuthentication = false;

    /**
     * Extend preDispatch
     *
     * @return $this|void
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
        if (!$this->_skipAuthentication && !Mage::getSingleton('customer/session')->authenticate($this)) {
            $this->setFlag('', 'no-dispatch', true);
            if (!Mage::getSingleton('customer/session')->getBeforeWishlistUrl()) {
                Mage::getSingleton('customer/session')->setBeforeWishlistUrl($this->_getRefererUrl());
            }
            Mage::getSingleton('customer/session')->setBeforeWishlistRequest($this->getRequest()->getParams());

            // The mutating wishlist actions are POST-only, so they would 405.
            if (!$this->getRequest()->isGet()) {
                Mage::getSingleton('customer/session')->setBeforeAuthUrl(Mage::getUrl('wishlist'));
            }
        }
        if (!$this->wishlistService()->isEnabled((int) Mage::app()->getStore()->getId())) {
            $this->norouteAction();
            return;
        }
        return $this;
    }

    /**
     * Set skipping authentication in actions of this controller (wishlist)
     *
     * @return $this
     */
    public function skipAuthentication()
    {
        $this->_skipAuthentication = true;
        return $this;
    }

    /**
     * Retrieve wishlist object
     * @param int $wishlistId
     * @return Mage_Wishlist_Model_Wishlist|false
     */
    #[\Override]
    protected function _getWishlist($wishlistId = null)
    {
        $wishlist = Mage::registry('wishlist');
        if ($wishlist) {
            return $wishlist;
        }

        try {
            $wishlistId = $wishlistId ?: $this->getRequest()->getParam('wishlist_id');
            $wishlist = $this->wishlistService()->getForCustomer(
                (int) Mage::getSingleton('customer/session')->getCustomerId(),
                (int) Mage::app()->getStore()->getId(),
                $wishlistId ? (int) $wishlistId : null,
            );
            Mage::register('wishlist', $wishlist);
        } catch (Mage_Core_Exception $e) {
            Mage::getSingleton('wishlist/session')->addError($e->getMessage());
            return false;
        } catch (Exception $e) {
            Mage::getSingleton('wishlist/session')->addException(
                $e,
                Mage::helper('wishlist')->__('Wishlist could not be created.'),
            );
            return false;
        }

        return $wishlist;
    }

    /**
     * Display customer wishlist
     *
     * @return Mage_Core_Controller_Varien_Action|void
     */
    #[Maho\Config\Route('/wishlist', name: 'wishlist.index.index', methods: ['GET'])]
    public function indexAction()
    {
        if (!$this->_getWishlist()) {
            $this->norouteAction();
            return;
        }
        $this->loadLayout();

        if (strpos($this->_getRefererUrl(), 'login')) {
            Mage::getSingleton('core/session')->addError(Mage::helper('wishlist')->__(
                'Please add product to wishlist again.',
            ));
            return $this->_redirectUrl(Mage::getSingleton('customer/session')->getBeforeWishlistUrl());
        }

        $session = Mage::getSingleton('customer/session');
        $block   = $this->getLayout()->getBlock('customer.wishlist');
        $referer = $session->getAddActionReferer(true);
        if ($block) {
            $block->setRefererUrl($this->_getRefererUrl());
            if ($referer) {
                $block->setRefererUrl($referer);
            }
        }

        $this->_initLayoutMessages('customer/session');
        $this->_initLayoutMessages('checkout/session');
        $this->_initLayoutMessages('catalog/session');
        $this->_initLayoutMessages('wishlist/session');

        $this->renderLayout();
    }

    /**
     * Adding new item
     *
     * @return Mage_Core_Controller_Varien_Action|void
     */
    #[Maho\Config\Route('/wishlist/index/add', name: 'wishlist.index.add', methods: ['POST'])]
    public function addAction()
    {
        $this->_addItemToWishList();
    }

    /**
     * Add the item to wish list
     *
     * @return void
     * @throws Throwable
     */
    protected function _addItemToWishList()
    {
        $wishlist = $this->_getWishlist();
        if (!$wishlist) {
            $this->norouteAction();
            return;
        }

        $session = Mage::getSingleton('customer/session');

        $productId = (int) $this->getRequest()->getParam('product');
        if (!$productId) {
            $this->_redirect('*/');
            return;
        }

        try {
            $requestParams = $this->getRequest()->getParams();
            if ($session->getBeforeWishlistRequest()) {
                $requestParams = $session->getBeforeWishlistRequest();
                $session->unsBeforeWishlistRequest();
            }
            $item = $this->wishlistService()->addProduct(
                $wishlist,
                $productId,
                new \Maho\DataObject($requestParams),
                (int) Mage::app()->getStore()->getId(),
            );

            $referer = $session->getBeforeWishlistUrl();
            if ($referer) {
                $session->setBeforeWishlistUrl(null);
            } else {
                $referer = $this->_getRefererUrl();
            }

            /**
             *  Set referer to avoid referring to the compare popup window
             */
            $session->setAddActionReferer($referer);

            Mage::helper('wishlist')->calculate();

            $session->addSuccess(
                $this->__('%1$s has been added to your wishlist. %2$s to continue shopping.'),
                $item->getProduct()->getName(),
                new \Maho\Message\Link($this->__('Click here'), $referer),
            );
        } catch (Mage_Core_Exception $e) {
            $session->addError($this->__('An error occurred while adding item to wishlist: %s', $e->getMessage()));
        } catch (Exception) {
            $session->addError($this->__('An error occurred while adding item to wishlist.'));
        }

        $this->_redirect('*', ['wishlist_id' => $wishlist->getId()]);
    }

    /**
     * Action to reconfigure wishlist item
     */
    #[Maho\Config\Route('/wishlist/index/configure/{id}', name: 'wishlist.index.configure', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function configureAction(): void
    {
        $id = (int) $this->getRequest()->getParam('id');
        try {
            $wishlist = $this->_getWishlist();
            if (!$wishlist) {
                $this->norouteAction();
                return;
            }
            $item = $this->wishlistService()->getItem($wishlist, $id);

            Mage::register('wishlist_item', $item);

            $params = new \Maho\DataObject();
            $params->setCategoryId(false);
            $params->setConfigureMode(true);
            $buyRequest = $item->getBuyRequest();
            if (!$buyRequest->getQty() && $item->getQty()) {
                $buyRequest->setQty($item->getQty());
            }
            if ($buyRequest->getQty() && !$item->getQty()) {
                $item->setQty($buyRequest->getQty());
                Mage::helper('wishlist')->calculate();
            }
            $params->setBuyRequest($buyRequest);
            Mage::helper('catalog/product_view')->prepareAndRender($item->getProductId(), $this, $params);
        } catch (Mage_Core_Exception $e) {
            Mage::getSingleton('customer/session')->addError($e->getMessage());
            $this->_redirect('*');
            return;
        } catch (Exception $e) {
            Mage::getSingleton('customer/session')->addError($this->__('Cannot configure product'));
            Mage::logException($e);
            $this->_redirect('*');
            return;
        }
    }

    /**
     * Action to accept new configuration for a wishlist item
     */
    #[Maho\Config\Route('/wishlist/index/updateItemOptions', name: 'wishlist.index.updateItemOptions', methods: ['POST'])]
    public function updateItemOptionsAction(): void
    {
        $session = Mage::getSingleton('customer/session');
        $productId = (int) $this->getRequest()->getParam('product');
        if (!$productId) {
            $this->_redirect('*/');
            return;
        }

        try {
            $product = $this->wishlistService()->getProduct($productId, (int) Mage::app()->getStore()->getId());
        } catch (Mage_Core_Exception) {
            $session->addError($this->__('Cannot specify product.'));
            $this->_redirect('*/');
            return;
        }

        $wishlist = $this->_getWishlist();
        if (!$wishlist) {
            $this->_redirect('*/');
            return;
        }
        try {
            $id = (int) $this->getRequest()->getParam('id');
            $item = $this->wishlistService()->getItem($wishlist, $id);

            $buyRequest = new \Maho\DataObject($this->getRequest()->getPost());
            $buyRequest->unsFormKey();
            $this->wishlistService()->updateItem($wishlist, $item, $buyRequest);
            Mage::helper('wishlist')->calculate();

            $message = $this->__('%1$s has been updated in your wishlist.', $product->getName());
            $session->addSuccess($message);
        } catch (Mage_Core_Exception $e) {
            $session->addError($e->getMessage());
        } catch (Exception $e) {
            $session->addError($this->__('An error occurred while updating wishlist.'));
            Mage::logException($e);
        }
        $this->_redirect('*/*', ['wishlist_id' => $wishlist->getId()]);
    }

    /**
     * Update wishlist item comments
     *
     * @return Mage_Core_Controller_Varien_Action|void
     */
    #[Maho\Config\Route('/wishlist/index/update', name: 'wishlist.index.update', methods: ['POST'])]
    public function updateAction()
    {
        $wishlist = $this->_getWishlist();
        if (!$wishlist) {
            $this->norouteAction();
            return;
        }

        $post = $this->getRequest()->getPost();
        if ($post && isset($post['description']) && is_array($post['description'])) {
            $changes = [];
            foreach ($post['description'] as $itemId => $description) {
                $changes[(int) $itemId] = [
                    // The placeholder of an empty comment clears it, and an empty field keeps it
                    'description' => match ((string) $description) {
                        Mage::helper('wishlist')->defaultCommentString() => '',
                        '' => null,
                        default => (string) $description,
                    },
                    'qty' => isset($post['qty'][$itemId]) ? $this->_processLocalizedQty($post['qty'][$itemId]) : null,
                ];
            }
            try {
                if ($this->wishlistService()->updateItems($wishlist, $changes)) {
                    Mage::helper('wishlist')->calculate();
                }
            } catch (Mage_Core_Exception $e) {
                Mage::getSingleton('customer/session')->addError($e->getMessage());
            } catch (Exception $e) {
                Mage::logException($e);
                Mage::getSingleton('customer/session')->addError($this->__('Can\'t update wishlist'));
            }

            if (isset($post['save_and_share'])) {
                $this->_redirect('*/*/share', ['wishlist_id' => $wishlist->getId()]);
                return;
            }
        }
        $this->_redirect('*', ['wishlist_id' => $wishlist->getId()]);
    }

    /**
     * Add every wishlist item to the shopping cart
     */
    #[\Override]
    #[Maho\Config\Route('/wishlist/index/allcart', name: 'wishlist.index.allcart', methods: ['POST'])]
    public function allcartAction(): void
    {
        parent::allcartAction();
    }

    /**
     * Remove item
     *
     *  @return Mage_Core_Controller_Varien_Action|void
     */
    #[Maho\Config\Route('/wishlist/index/remove', name: 'wishlist.index.remove', methods: ['POST'])]
    public function removeAction()
    {
        $wishlist = $this->_getWishlist();
        if (!$wishlist) {
            $this->norouteAction();
            return;
        }
        try {
            $item = $this->wishlistService()->getItem($wishlist, (int) $this->getRequest()->getParam('item'));
            $this->wishlistService()->removeItem($wishlist, $item);
        } catch (Mage_Core_Exception_NoSuchEntity) {
            $this->norouteAction();
            return;
        } catch (Mage_Core_Exception $e) {
            Mage::getSingleton('customer/session')->addError(
                $this->__('An error occurred while deleting the item from wishlist: %s', $e->getMessage()),
            );
        } catch (Exception) {
            Mage::getSingleton('customer/session')->addError(
                $this->__('An error occurred while deleting the item from wishlist.'),
            );
        }

        Mage::helper('wishlist')->calculate();

        $this->_redirectReferer(Mage::getUrl('*/*'));
    }

    /**
     * Add wishlist item to shopping cart and remove from wishlist
     *
     * If Product has required options - item removed from wishlist and redirect
     * to product view page with message about needed defined required options
     */
    #[Maho\Config\Route('/wishlist/index/cart', name: 'wishlist.index.cart', methods: ['POST'])]
    public function cartAction()
    {
        $itemId = (int) $this->getRequest()->getParam('item');

        $wishlist = $this->_getWishlist();
        if (!$wishlist) {
            return $this->_redirect('*/*');
        }
        try {
            $item = $this->wishlistService()->getItem($wishlist, $itemId);
        } catch (Mage_Core_Exception_NoSuchEntity) {
            return $this->_redirect('*/*');
        }

        $qty = $this->getRequest()->getParam('qty');
        if (is_array($qty)) {
            $qty = $qty[$itemId] ?? 1;
        }
        $qty = (float) $qty > 0 ? (float) $qty : null;

        /** @var Mage_Wishlist_Model_Session $session */
        $session    = Mage::getSingleton('wishlist/session');
        $cart       = Mage::getSingleton('checkout/cart');

        $redirectUrl = Mage::getUrl('*/*');

        try {
            $item->mergeBuyRequest(Mage::helper('catalog/product')->addParamsToBuyRequest(
                $this->getRequest()->getParams(),
                ['current_config' => $item->getBuyRequest()],
            ));
            $this->wishlistService()->moveToCart($wishlist, $item, $cart->getQuote(), $qty);
            $cart->save();
            Mage::getSingleton('checkout/session')->setLastAddedProductId((int) $item->getProductId());

            if (Mage::helper('checkout/cart')->getShouldRedirectToCart()) {
                $redirectUrl = Mage::helper('checkout/cart')->getCartUrl();
            }

            $product = Mage::getModel('catalog/product')
                ->setStoreId(Mage::app()->getStore()->getId())
                ->load($item->getProductId());
            $message = $this->__('%s was added to your shopping cart.', $product->getName());
            Mage::getSingleton('catalog/session')->addSuccess($message);
        } catch (Mage_Core_Exception $e) {
            // A notice asks for an option that the shopper chooses on the configure page
            if ($e->getMessages(Mage_Core_Model_Message::NOTICE)) {
                Mage::getSingleton('catalog/session')->addNotice($e->getMessage());
                $redirectUrl = Mage::getUrl('*/*/configure/', ['id' => $item->getId()]);
            } else {
                $session->addError($e->getMessage());
            }
        } catch (Exception $e) {
            Mage::logException($e);
            $session->addException($e, $this->__('Cannot add item to shopping cart'));
        }

        Mage::helper('wishlist')->calculate();

        return $this->_redirectUrl($redirectUrl);
    }

    /**
     * Add cart item to wishlist and remove from cart
     *
     * @return Mage_Core_Controller_Varien_Action|void
     */
    #[Maho\Config\Route('/wishlist/index/fromcart', name: 'wishlist.index.fromcart', methods: ['POST'])]
    public function fromcartAction()
    {
        $wishlist = $this->_getWishlist();
        if (!$wishlist) {
            $this->norouteAction();
            return;
        }
        $itemId = (int) $this->getRequest()->getParam('item');

        /** @var Mage_Checkout_Model_Cart $cart */
        $cart = Mage::getSingleton('checkout/cart');
        $session = Mage::getSingleton('checkout/session');

        try {
            $product = $this->wishlistService()->moveFromCart($wishlist, $cart->getQuote(), $itemId)->getProduct();
            $cart->save();
            Mage::helper('wishlist')->calculate();

            $session->addSuccess($this->wishlistService()->hasFileOption($product)
                ? Mage::helper('wishlist')->__('%s has been moved to wishlist %s. Please note that uploaded files will need to be re-uploaded when adding to cart.', $product->getName(), $wishlist->getName())
                : Mage::helper('wishlist')->__('%s has been moved to wishlist %s', $product->getName(), $wishlist->getName()));
        } catch (Mage_Core_Exception $e) {
            $session->addError($e->getMessage());
        } catch (Exception $e) {
            $session->addException($e, Mage::helper('wishlist')->__('Cannot move item to wishlist'));
        }

        return $this->_redirectUrl(Mage::helper('checkout/cart')->getCartUrl());
    }

    /**
     * Prepare wishlist for share
     */
    #[Maho\Config\Route('/wishlist/index/share', name: 'wishlist.index.share', methods: ['GET'])]
    public function shareAction(): void
    {
        $this->_getWishlist();
        $this->loadLayout();
        $this->_initLayoutMessages('customer/session');
        $this->_initLayoutMessages('wishlist/session');
        $this->renderLayout();
    }

    /**
     * Share wishlist
     *
     * @return Mage_Core_Controller_Varien_Action|void
     */
    #[Maho\Config\Route('/wishlist/index/send', name: 'wishlist.index.send', methods: ['POST'])]
    public function sendAction()
    {
        $wishlist = $this->_getWishlist();
        if (!$wishlist) {
            $this->norouteAction();
            return;
        }

        try {
            $this->wishlistService()->share(
                $wishlist,
                explode(',', (string) $this->getRequest()->getPost('emails', '')),
                (string) $this->getRequest()->getPost('message'),
                (int) Mage::app()->getStore()->getId(),
                (bool) $this->getRequest()->getParam('rss_url'),
            );
            Mage::getSingleton('customer/session')->addSuccess(
                $this->__('Your Wishlist has been shared.'),
            );
            $this->_redirect('*/*', ['wishlist_id' => $wishlist->getId()]);
        } catch (Exception $e) {
            Mage::getSingleton('wishlist/session')->addError($e->getMessage());
            Mage::getSingleton('wishlist/session')->setSharingForm($this->getRequest()->getPost());
            $this->_redirect('*/*/share');
        }
    }

    /**
     * Custom options download action
     */
    #[Maho\Config\Route('/wishlist/index/downloadCustomOption', name: 'wishlist.index.downloadCustomOption', methods: ['GET'])]
    public function downloadCustomOptionAction(): void
    {
        $option = Mage::getModel('wishlist/item_option')->load($this->getRequest()->getParam('id'));

        if (!$option->getId()) {
            $this->_forward('noRoute');
            return;
        }

        // Verify the wishlist belongs to the logged-in customer
        $wishlistItem = Mage::getModel('wishlist/item')->load($option->getWishlistItemId());
        $wishlist = Mage::getModel('wishlist/wishlist')->load($wishlistItem->getWishlistId());
        if ($wishlist->getCustomerId() != Mage::getSingleton('customer/session')->getCustomerId()) {
            $this->_forward('noRoute');
            return;
        }

        try {
            $file = Mage::getService('catalog/product_option')->openItemOptionFile($option, (string) $this->getRequest()->getParam('key'));
        } catch (Mage_Core_Exception) {
            $this->_forward('noRoute');
            return;
        }
        $this->_prepareDownloadResponse($file['title'], [
            'value' => $file['stream'],
            'type'  => 'stream',
        ], contentLength: $file['size']);
        exit(0);
    }

    protected function wishlistService(): Mage_Wishlist_Service_Wishlist
    {
        return Mage::getService('wishlist/wishlist');
    }
}
