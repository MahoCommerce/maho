<?php

/**
 * The body of a new comment in the status history of an order.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

declare(strict_types=1);

namespace Mage\Sales\Api;

use ApiPlatform\Metadata\ApiProperty;

final class OrderCommentInput
{
    #[ApiProperty(description: 'Text of the comment', required: true)]
    public ?string $comment = null;

    #[ApiProperty(description: 'New status of the order. It must be a status of the current state of the order. Default: the status does not change')]
    public ?string $status = null;

    #[ApiProperty(description: 'Send the order update email, with the comment, to the customer. Default: false')]
    public ?bool $notifyCustomer = null;

    #[ApiProperty(description: 'Show the comment to the customer in the storefront account. Default: false')]
    public ?bool $visibleOnFront = null;
}
