<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

namespace Tests\Helpers;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManager;
use Symfony\Component\Security\Core\Authorization\AuthorizationChecker;
use Symfony\Component\Security\Core\Authorization\Voter\RoleVoter;

/**
 * Build the guest carts and the guest security that the cart parity tests give to the storefront and the API (#1348).
 */
final class CartParity
{
    /**
     * Return the security of a call without a token, as a guest storefront sends it.
     */
    public static function guestSecurity(): Security
    {
        $storage = new TokenStorage();

        return new Security(new readonly class ($storage) implements \Psr\Container\ContainerInterface {
            public function __construct(private TokenStorage $storage) {}

            #[\Override]
            public function get(string $id): mixed
            {
                return $id === 'security.authorization_checker'
                    ? new AuthorizationChecker($this->storage, new AccessDecisionManager([new RoleVoter()]))
                    : $this->storage;
            }

            #[\Override]
            public function has(string $id): bool
            {
                return in_array($id, ['security.token_storage', 'security.authorization_checker'], true);
            }
        });
    }

    /**
     * Return a new guest cart of the current store, with one simple product that is in stock unless $withItem is false.
     */
    public static function cart(bool $withItem = true): \Mage_Sales_Model_Quote
    {
        /** @var \Mage_Checkout_Service_Cart $service */
        $service = \Mage::getService('checkout/cart');
        $quote = $service->create(null, (int) \Mage::app()->getStore()->getId());
        if (!$withItem) {
            return $quote;
        }

        $productId = (int) \Mage::getResourceModel('catalog/product_collection')
            ->addAttributeToFilter('type_id', 'simple')
            ->addAttributeToFilter('status', \Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
            ->addAttributeToFilter('visibility', ['neq' => \Mage_Catalog_Model_Product_Visibility::VISIBILITY_NOT_VISIBLE])
            ->joinField('is_in_stock', 'cataloginventory/stock_item', 'is_in_stock', 'product_id=entity_id', '{{table}}.is_in_stock=1', 'inner')
            ->setOrder('entity_id', 'ASC')
            ->setPageSize(1)
            ->getFirstItem()
            ->getId();
        $product = \Mage::getModel('catalog/product')->setStoreId($quote->getStoreId())->load($productId);
        $quote->addProduct($product, 1);
        $service->collectAndSave($quote);

        return $quote;
    }
}
