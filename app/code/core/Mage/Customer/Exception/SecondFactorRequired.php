<?php

/**
 * A client fault: another step proved who the customer is, and the customer must now give the code of the second factor.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Customer
 */

declare(strict_types=1);

class Mage_Customer_Exception_SecondFactorRequired extends Mage_Core_Exception_Unauthorized
{
    public const ERROR_CODE = 'twofa_required';

    public function __construct(string $message, private readonly int $customerId)
    {
        parent::__construct($message, self::ERROR_CODE);
    }

    /**
     * Return the ID of the customer who must give the code. A client with a session keeps the ID, asks for the code,
     * and checks it with Mage_Customer_Service_Authentication::verifySecondFactor() before it logs the customer in.
     */
    public function getCustomerId(): int
    {
        return $this->customerId;
    }
}
