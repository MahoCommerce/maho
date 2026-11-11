<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Wishlist
 */

declare(strict_types=1);

namespace Mage\Wishlist\Api;

use Mage\Checkout\Api\CartRequest;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Delete;
use Maho\ApiPlatform\Service\StoreContext;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Give the wishlist operations of the customer to the wishlist service, and return the item.
 */
final class WishlistProcessor extends \Maho\ApiPlatform\Processor
{
    private readonly \Mage_Checkout_Service_Cart $cartService;
    private readonly \Mage_Wishlist_Service_Wishlist $wishlistService;

    public function __construct(Security $security)
    {
        parent::__construct($security);
        $this->cartService = \Mage::getService('checkout/cart');
        $this->wishlistService = \Mage::getService('wishlist/wishlist');
    }

    /**
     * @param WishlistItem $data
     * @return WishlistItem|null
     */
    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        StoreContext::ensureStore();
        $operationName = $operation->getName();

        // GraphQL mutations
        if ($operationName === 'add') {
            $args = $context['args']['input'] ?? [];
            return $this->addToWishlist(
                (int) $args['productId'],
                (int) ($args['qty'] ?? 1),
                $args['description'] ?? null,
            );
        }

        if ($operationName === 'remove') {
            $args = $context['args']['input'] ?? [];
            return $this->removeFromWishlist((int) $args['itemId']);
        }

        if ($operationName === 'moveToCart') {
            $args = $context['args']['input'] ?? [];
            return $this->moveToCart(
                (int) $args['itemId'],
                isset($args['qty']) ? (float) $args['qty'] : null,
            );
        }

        if ($operationName === 'sync') {
            $args = $context['args']['input'] ?? [];
            return $this->syncWishlist($args['productIds'] ?? []);
        }

        if ($operationName === 'share') {
            $args = $context['args']['input'] ?? [];
            return $this->shareWishlist($args['emails'] ?? [], (string) ($args['message'] ?? ''));
        }

        // REST operations
        if ($operation instanceof Delete) {
            $itemId = (int) ($uriVariables['id'] ?? 0);
            return $this->removeFromWishlist($itemId);
        }

        if ($operationName === 'move_to_cart') {
            $itemId = (int) ($uriVariables['id'] ?? 0);
            $body = $context['request']?->toArray() ?? [];
            $qty = isset($body['qty']) ? (float) $body['qty'] : null;
            // JSON numbers arrive as int; moveToCart types $cartId as ?string.
            $cartId = isset($body['cartId']) ? (string) $body['cartId'] : null;
            return $this->moveToCart($itemId, $qty, $cartId);
        }

        if ($operationName === 'sync_wishlist') {
            $body = $context['request']?->toArray() ?? [];
            return $this->syncWishlist($body['productIds'] ?? []);
        }

        if ($operationName === 'share_wishlist') {
            $body = $context['request']?->toArray() ?? [];
            if (!is_array($body['emails'] ?? [])) {
                throw new BadRequestHttpException('emails must be a list of email addresses');
            }
            return $this->shareWishlist($body['emails'] ?? [], (string) ($body['message'] ?? ''));
        }

        // Default POST - add to wishlist
        if ($data instanceof WishlistItem && $data->productId) {
            return $this->addToWishlist(
                $data->productId,
                $data->qty,
                $data->description,
            );
        }

        throw new BadRequestHttpException('Invalid wishlist operation');
    }

    private function getWishlist(): \Mage_Wishlist_Model_Wishlist
    {
        return $this->wishlistService->getForCustomer($this->requireCustomerId(), StoreContext::getStoreId());
    }

    private function toDto(\Mage_Wishlist_Model_Item $item): WishlistItem
    {
        $dto = WishlistItem::fromModel($item);
        \Mage::dispatchEvent('api_wishlist_item_dto_build', ['dto' => $dto]);
        return $dto;
    }

    private function addToWishlist(int $productId, int $qty = 1, ?string $description = null): WishlistItem
    {
        return $this->toDto($this->wishlistService->addProduct(
            $this->getWishlist(),
            $productId,
            new \Maho\DataObject(['qty' => $qty]),
            StoreContext::getStoreId(),
            $description,
        ));
    }

    private function removeFromWishlist(int $itemId): null
    {
        $wishlist = $this->getWishlist();
        $this->wishlistService->removeItem($wishlist, $this->wishlistService->getItem($wishlist, $itemId));
        return null;
    }

    /**
     * Add the item to the cart with the options of its buy request, then remove it from the wishlist.
     *
     * @param float|null $qty null takes the qty of the item
     */
    private function moveToCart(int $itemId, ?float $qty = null, ?string $cartId = null): WishlistItem
    {
        $customerId = $this->requireCustomerId();
        $wishlist = $this->getWishlist();
        $item = $this->wishlistService->getItem($wishlist, $itemId);

        // Get the quote - prefer guest cart if cartId provided
        /** @var \Mage_Sales_Model_Quote|null $quote */
        $quote = null;
        if ($cartId) {
            // Use the cart service to load the cart properly (handles numeric or masked IDs)
            $accessedByMaskedId = !is_numeric($cartId);
            $quote = $accessedByMaskedId ? CartRequest::load(null, $cartId) : CartRequest::load((int) $cartId);

            // CartRequest::load() applies no ownership filtering, verify the caller owns
            // this cart (or holds its masked guest token) before writing to it,
            // otherwise a customer could push items into another customer's cart.
            if ($quote && $quote->getId()) {
                $this->cartService->verifyAccess(
                    $quote,
                    $accessedByMaskedId,
                    $customerId,
                    $this->isAdmin() || $this->isApiUser(),
                );
            }
        }

        if (!$quote || !$quote->getId()) {
            $quote = $this->cartService->getForCustomer($customerId, (int) \Mage::app()->getStore()->getId());
        }

        // Build the response before the move deletes the item
        $dto = $this->toDto($item);
        $this->wishlistService->moveToCart($wishlist, $item, $quote, $qty);

        return $dto;
    }

    /**
     * Send the wishlist to $emails. Return a placeholder with the ID of the wishlist, because the operation
     * returns one resource.
     *
     * @param array<mixed> $emails
     */
    private function shareWishlist(array $emails, string $message): WishlistItem
    {
        $wishlist = $this->wishlistService->share($this->getWishlist(), $emails, $message, StoreContext::getStoreId());

        $placeholder = new WishlistItem();
        $placeholder->id = (int) $wishlist->getId();
        return $placeholder;
    }

    /**
     * Add the products of the guest wishlist (from localStorage) to the wishlist of the customer. Return the
     * first new item, or else the first item of the wishlist, because the operation returns one resource.
     *
     * @param array<int> $productIds
     */
    private function syncWishlist(array $productIds): WishlistItem
    {
        $wishlist = $this->getWishlist();
        $added = $this->wishlistService->addProducts($wishlist, $productIds, StoreContext::getStoreId());
        if ($added !== []) {
            return $this->toDto($added[0]);
        }

        $itemCollection = $wishlist->getItemsCollection();
        $itemCollection->addStoreFilter([\Mage::app()->getStore()->getId()]);

        /** @var \Mage_Wishlist_Model_Item $item */
        foreach ($itemCollection as $item) {
            $product = $item->getProduct();
            if ($product && $product->getId()) {
                return $this->toDto($item);
            }
        }

        // Empty wishlist, return a placeholder with wishlist ID
        $placeholder = new WishlistItem();
        $placeholder->id = (int) $wishlist->getId();
        return $placeholder;
    }
}
