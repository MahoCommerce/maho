<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_ApiPlatform
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\Sales\Api\Resource;

/**
 * Result DTO for placeOrderWithSplitPayments mutation
 */
class PlaceOrderWithSplitPaymentsResult
{
    public ?Order $order = null;

    /** @var PosPayment[] */
    public array $payments = [];

    public ?float $changeAmount = null;

    public ?Invoice $invoice = null;

    public ?Shipment $shipment = null;
}
