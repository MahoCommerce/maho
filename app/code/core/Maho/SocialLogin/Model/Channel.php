<?php

/**
 * The transport of a social sign-in. Each channel has its own rate limit and its own nonce rule.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_SocialLogin
 */

declare(strict_types=1);

enum Maho_SocialLogin_Model_Channel: string
{
    /** The sign-in buttons of the storefront, which log in the customer session */
    case Web = 'web';

    /** The REST API, which returns a customer token to a headless storefront */
    case Api = 'api';
}
