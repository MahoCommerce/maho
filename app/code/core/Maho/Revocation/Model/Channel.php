<?php

/**
 * The transport that submits a revocation declaration. Each channel has its own abuse checks and order rules.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Revocation
 */

declare(strict_types=1);

enum Maho_Revocation_Model_Channel: string
{
    /** The public form of the storefront, which a guest can use */
    case Web = 'web';

    /** The REST and GraphQL API, for a customer who revokes an own order */
    case Api = 'api';
}
