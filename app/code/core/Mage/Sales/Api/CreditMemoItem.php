<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Sales
 */

declare(strict_types=1);

namespace Mage\Sales\Api;

class CreditMemoItem extends \Maho\ApiPlatform\Resource
{
    public ?int $id = null;
    public ?int $orderItemId = null;
    public string $sku = '';
    public string $name = '';
    public float $qty = 0;
    public float $price = 0;
    public float $rowTotal = 0;
    public float $taxAmount = 0;
    public float $discountAmount = 0;
    public bool $backToStock = false;
}
