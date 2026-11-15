<?php

/**
 * A client fault: the client did not prove who it is, for example a wrong password or a wrong two-factor code.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

class Mage_Core_Exception_Unauthorized extends Mage_Core_Exception
{
    /**
     * $errorCode is the name of the fault for an API client, for example invalid_credentials, or null for unauthorized.
     */
    public function __construct(string $message = '', private readonly ?string $errorCode = null)
    {
        parent::__construct($message);
    }

    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }
}
