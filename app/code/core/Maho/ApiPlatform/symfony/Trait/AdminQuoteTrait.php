<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

namespace Maho\ApiPlatform\Trait;

use Mage;
use Mage_Sales_Model_Quote;
use Maho\ApiPlatform\Exception\NotFoundException;

/**
 * Loads a quote by id without store filtering, for admin/POS GraphQL handlers
 * that operate across stores. Throws a not-found exception when the quote is
 * missing, so handlers don't each repeat the load-and-check.
 */
trait AdminQuoteTrait
{
    protected function loadAdminQuote(int|string $cartId): Mage_Sales_Model_Quote
    {
        /** @var Mage_Sales_Model_Quote $quote */
        $quote = Mage::getModel('sales/quote')->loadByIdWithoutStore($cartId);
        if (!$quote->getId()) {
            throw NotFoundException::cart($cartId);
        }
        return $quote;
    }
}
