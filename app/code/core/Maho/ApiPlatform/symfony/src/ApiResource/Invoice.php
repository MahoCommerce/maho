<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_ApiPlatform
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\ApiPlatform\ApiResource;

/**
 * Invoice DTO
 */
class Invoice
{
    public ?int $id = null;
    public ?string $incrementId = null;
    public ?int $orderId = null;
    public float $grandTotal = 0.0;
    public ?string $state = null;
    public ?string $createdAt = null;
}
