<?php

/**
 * SPDX-FileCopyrightText: 2019-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Payment
 */

declare(strict_types=1);

class Mage_Payment_Exception extends Exception
{
    /**
     * Comma-separated names of the fields with an error
     */
    protected ?string $_code = null;

    public function __construct(?string $message = null, ?string $fields = null)
    {
        $this->_code = $fields === '' ? null : $fields;
        parent::__construct($message ?? '', 0);
    }

    public function getFields(): ?string
    {
        return $this->_code;
    }
}
