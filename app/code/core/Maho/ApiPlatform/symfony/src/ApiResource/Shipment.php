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
 * Shipment DTO
 */
class Shipment
{
    public ?int $id = null;
    public ?string $incrementId = null;
    public ?int $orderId = null;
    public int $totalQty = 0;
    public ?string $createdAt = null;
}
