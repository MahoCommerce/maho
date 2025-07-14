<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Core
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * JSON encoding/decoding exception
 */
class Mage_Core_Exception_Json extends Mage_Core_Exception
{
    protected ?int $_jsonErrorCode = null;

    public function getJsonErrorCode(): ?int
    {
        return $this->_jsonErrorCode;
    }

    /**
     * Create exception from last JSON error
     *
     * @param string $operation Either 'encode' or 'decode'
     */
    public static function createFromLastError(string $operation = 'decode'): self
    {
        $errorCode = json_last_error();
        $errorMsg = json_last_error_msg();

        $message = sprintf(
            'Unable to %s JSON: %s',
            $operation,
            $errorMsg ?: 'Unknown error',
        );

        $exception = new self($message);
        $exception->_jsonErrorCode = $errorCode;

        return $exception;
    }
}
