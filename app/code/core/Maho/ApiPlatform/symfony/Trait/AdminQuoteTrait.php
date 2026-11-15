<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

namespace Maho\ApiPlatform\Trait;

use Mage\Checkout\Api\CartRequest;
use Mage_Sales_Model_Quote;

/**
 * Loads a quote by id without store filtering, for the admin GraphQL handlers
 * that operate across stores. Throws Mage_Core_Exception_NoSuchEntity when the quote is
 * missing, so handlers don't each repeat the load-and-check.
 *
 * CartRequest::load() also gives the quote the display currency of the request, so a shipping
 * estimate or a placed order does not ignore a header the cart reads honored.
 */
trait AdminQuoteTrait
{
    protected function loadAdminQuote(int|string $cartId): Mage_Sales_Model_Quote
    {
        return CartRequest::load((int) $cartId) ?? throw new \Mage_Core_Exception_NoSuchEntity('Cart not found');
    }
}
