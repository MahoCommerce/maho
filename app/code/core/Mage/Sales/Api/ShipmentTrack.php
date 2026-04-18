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
 * Shipment Track DTO
 */
class ShipmentTrack extends \Maho\ApiPlatform\Resource
{
    public ?int $id = null;
    public ?string $carrier = null;
    public ?string $title = null;
    public ?string $trackNumber = null;
}
