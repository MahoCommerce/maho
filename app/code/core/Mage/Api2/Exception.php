<?php

/**
 * Maho
 *
 * @package    Mage_Api2
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2025 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Api2_Exception extends Exception
{
    /**
     * Log the exception in the log file?
     */
    protected bool $shouldLog = true;

    /**
     * Exception constructor
     *
     * @param string $message
     * @param int $code
     * @param bool $shouldLog
     */
    public function __construct($message, $code, $shouldLog = true)
    {
        if ($code <= 100 || $code >= 599) {
            throw new Exception(sprintf('Invalid Exception code "%d"', $code));
        }

        $this->shouldLog = $shouldLog;
        parent::__construct($message, $code);
    }

    /**
     * Check if exception should be logged
     */
    public function shouldLog(): bool
    {
        return $this->shouldLog;
    }
}
