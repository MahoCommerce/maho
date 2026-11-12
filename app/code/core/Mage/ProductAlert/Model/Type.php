<?php

/**
 * The event that a product alert waits for.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_ProductAlert
 */

declare(strict_types=1);

enum Mage_ProductAlert_Model_Type: string
{
    /** The price of the product goes below the price at the time of the subscription */
    case Price = 'price';

    /** The product is in stock again */
    case Stock = 'stock';
}
