<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_ApiPlatform
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\ApiPlatform\ApiResource;

/**
 * Payment Summary DTO for grouped payment totals
 */
class PaymentSummary
{
    public ?string $method = null;
    public ?string $methodTitle = null;
    public float $totalAmount = 0.0;
    public int $paymentCount = 0;
}
