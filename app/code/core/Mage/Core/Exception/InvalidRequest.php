<?php

/**
 * A client fault: the service cannot do what the client asks, with these values or in this state.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

class Mage_Core_Exception_InvalidRequest extends Mage_Core_Exception
{
    /**
     * $errorCode names the fault for a client that must tell it apart from other faults, for example invalid_coupon.
     */
    public function __construct(string $message, private readonly ?string $errorCode = null)
    {
        parent::__construct($message);
    }

    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }
}
