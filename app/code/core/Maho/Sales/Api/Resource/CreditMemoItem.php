<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_Sales
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\Sales\Api\Resource;

class CreditMemoItem
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
