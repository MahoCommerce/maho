<?php

/**
 * The transport that submits a review. The API checks the CAPTCHA in the service, the storefront before the controller.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Review
 */

declare(strict_types=1);

enum Mage_Review_Model_Channel: string
{
    /** The review form of the product page and of the review list page */
    case Web = 'web';

    /** The REST and GraphQL API, for a headless storefront */
    case Api = 'api';
}
