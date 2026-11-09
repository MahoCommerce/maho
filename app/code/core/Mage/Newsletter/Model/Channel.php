<?php

/**
 * The transport that subscribes an address. The API checks the CAPTCHA in the service, the storefront before the controller.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Newsletter
 */

declare(strict_types=1);

enum Mage_Newsletter_Model_Channel: string
{
    /** The footer form and the newsletter page of the customer account */
    case Web = 'web';

    /** The REST and GraphQL API, for a headless storefront: an anonymous caller or a customer token */
    case Api = 'api';

    /** The API with an admin token or a service token that has access to the newsletter. The admin asks no CAPTCHA. */
    case Admin = 'admin';
}
