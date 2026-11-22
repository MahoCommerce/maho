<?php

/**
 * SPDX-FileCopyrightText: 2019-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Payment
 */

class Mage_Payment_Exception extends Exception
{
    /**
     * Comma-separated names of the fields with an error
     */
    protected ?string $_code = null;

    /**
     * @param int|string $code Comma-separated names of the fields with an error. Mage::exception() passes 0 when there are none.
     */
    public function __construct(?string $message = null, int|string $code = 0)
    {
        $this->_code = is_string($code) && $code !== '' ? $code : null;
        parent::__construct($message ?? '', 0);
    }

    public function getFields(): ?string
    {
        return $this->_code;
    }
}
