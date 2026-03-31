<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Mage_Sales
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Mage\Sales\Api;

/**
 * Shipment Item DTO
 */
class ShipmentItem
{
    public ?string $sku = null;
    public ?string $name = null;
    public float $qty = 0;
}
