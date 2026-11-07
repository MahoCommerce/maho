<?php

/**
 * The transport that sends a contact message. Each channel has its own switches in the configuration.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Contacts
 */

declare(strict_types=1);

enum Mage_Contacts_Model_Channel: string
{
    /** The contact form of the storefront */
    case Web = 'web';

    /** The REST and GraphQL API, for a headless storefront */
    case Api = 'api';
}
