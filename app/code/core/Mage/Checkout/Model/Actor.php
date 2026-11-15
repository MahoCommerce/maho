<?php

/**
 * The role of the person who changes a cart. The cart rules that differ between shoppers and staff read it.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Checkout
 */

declare(strict_types=1);

enum Mage_Checkout_Model_Actor: string
{
    /** A shopper on the storefront, or a guest or customer token on the API */
    case Customer = 'customer';

    /** The admin order create, an admin token, or an API token with the grant to write carts or orders */
    case Staff = 'staff';
}
