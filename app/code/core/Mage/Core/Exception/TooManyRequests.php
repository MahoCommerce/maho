<?php

/**
 * A client fault: the client sent more requests than a rate limit allows, for example more contact messages in one hour.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

class Mage_Core_Exception_TooManyRequests extends Mage_Core_Exception
{
    /**
     * $retryAfter is the number of seconds after which the client can try again, or null when it is not known.
     */
    public function __construct(string $message = '', private readonly ?int $retryAfter = null)
    {
        parent::__construct($message);
    }

    public function getRetryAfter(): ?int
    {
        return $this->retryAfter;
    }
}
