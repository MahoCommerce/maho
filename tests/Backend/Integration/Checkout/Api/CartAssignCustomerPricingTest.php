<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Mage\Checkout\Api\CartMapper;
use Mage\Checkout\Api\CartService;
use Mage\Checkout\Api\GraphQL\CartMutationHandler;

uses(Tests\MahoBackendTestCase::class);

/**
 * Assigning a customer changes the quote's customer group, so the totals must
 * recollect: group prices and group-based tax classes only apply then. The
 * handlers used to call collectTotals() on an already-collected quote, which
 * the totals-collected flag turned into a no-op, so the guest-priced subtotal
 * was saved for the customer.
 */

/** A product priced 20.00, with a 15.00 group price for the General group (id 1). */
function cartAssignCreateGroupPricedProduct(): Mage_Catalog_Model_Product
{
    return createPriceWebsiteProduct('cart-assign-group', 20.00, data: [
        'group_price' => [[
            'website_id' => 0,
            'cust_group' => 1,
            'price' => 15.00,
        ]],
        'tax_class_id' => 0,
        'stock_data' => [
            'qty' => 100,
            'is_in_stock' => 1,
        ],
    ]);
}

function cartAssignCreateGeneralGroupCustomer(): Mage_Customer_Model_Customer
{
    $customer = Mage::getModel('customer/customer');
    $customer->setWebsiteId(1);
    $customer->setGroupId(1);
    $customer->setFirstname('Group');
    $customer->setLastname('Pricing');
    $customer->setEmail('cart-assign-group-' . bin2hex(random_bytes(4)) . '@example.com');
    $customer->save();
    return $customer;
}

describe('customer assignment pricing', function (): void {

    it('applies the customer group price when a customer is assigned to a cart', function (): void {
        $product = cartAssignCreateGroupPricedProduct();
        $customer = cartAssignCreateGeneralGroupCustomer();
        // Reload: only a loaded product carries the stock item the qty observer needs
        $product = Mage::getModel('catalog/product')->setStoreId(1)->load($product->getId());
        $quote = createPricedQuote($product);

        try {
            expect((float) $quote->getSubtotal())->toBe(40.0);

            $handler = new CartMutationHandler(new CartService(), new CartMapper());
            $result = $handler->handleAssignCustomer([
                'cartId' => (int) $quote->getId(),
                'customerId' => (int) $customer->getId(),
            ]);

            expect((float) $result['assignCustomerToCart']['prices']['subtotal'])->toBe(30.0);

            $reloaded = Mage::getModel('sales/quote')->loadByIdWithoutStore((int) $quote->getId());
            expect((float) $reloaded->getSubtotal())->toBe(30.0);
        } finally {
            $quote->delete();
            $customer->delete();
            $product->delete();
        }
    });

});
