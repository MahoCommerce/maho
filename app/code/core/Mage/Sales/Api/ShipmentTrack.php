<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

declare(strict_types=1);

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
