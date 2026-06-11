<?php

/**
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Api
 */

class Mage_Api_Exception extends Mage_Core_Exception
{
    protected $_customMessage = null;

    /**
     * Mage_Api_Exception constructor.
     * @param string $faultCode
     * @param string|null $customMessage
     */
    public function __construct($faultCode, $customMessage = null)
    {
        parent::__construct($faultCode);
        $this->_customMessage = $customMessage;
    }

    /**
     * Custom error message, if error is not in api.
     *
     * @return string
     */
    public function getCustomMessage()
    {
        return $this->_customMessage;
    }
}
