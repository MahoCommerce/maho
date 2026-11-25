<?php

/**
 * The body of a new comment on an invoice or a credit memo.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

declare(strict_types=1);

namespace Mage\Sales\Api;

use ApiPlatform\Metadata\ApiProperty;

final class DocumentCommentInput
{
    public const array FIELDS = ['comment', 'notifyCustomer', 'visibleOnFront'];

    #[ApiProperty(description: 'Text of the comment', required: true)]
    public ?string $comment = null;

    #[ApiProperty(description: 'Send the update email of the document, with the comment, to the customer. Default: false')]
    public ?bool $notifyCustomer = null;

    #[ApiProperty(description: 'Show the comment to the customer in the storefront account. Default: false')]
    public ?bool $visibleOnFront = null;
}
