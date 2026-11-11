<?php

/**
 * The transport that registers a customer or asks for an email with a link. The API checks the CAPTCHA in the service, the storefront before the controller.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Customer
 */

declare(strict_types=1);

enum Mage_Customer_Model_Channel: string
{
    /** The forms of the storefront, the checkout and a social sign-in */
    case Web = 'web';

    /** The REST and GraphQL API, for a headless storefront: an anonymous caller or a customer token */
    case Api = 'api';

    /** The API with an admin token or a service token that has access to the customers. The admin asks no CAPTCHA. */
    case Admin = 'admin';
}
