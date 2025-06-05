<?php

/**
 * Maho
 *
 * @package    Mage_Core
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Core_Model_Url_Validator
{
    /**
     * Last error message
     *
     * @var string|null
     */
    protected $_lastError;

    /**
     * Last value for validation
     *
     * @var mixed
     */
    protected $_lastValue;

    /**
     * Error keys
     */
    public const INVALID_URL = 'invalidUrl';

    /**
     * Object constructor
     */
    public function __construct()
    {
        // Initialize message templates
        $this->_messageTemplates[self::INVALID_URL] = Mage::helper('core')->__("Invalid URL '%value%'.");
    }

    /**
     * Validation failure message template definitions
     *
     * @var array
     */
    protected $_messageTemplates = [
        self::INVALID_URL => "Invalid URL '%value%'.",
    ];

    /**
     * Validate value
     *
     * @param string $value
     * @return bool
     */
    public function isValid($value)
    {
        // Store value for potential error message
        $this->_lastValue = $value;

        //check valid URL
        if (!Zend_Uri::check($value)) {
            $this->_lastError = Mage::helper('core')->__('Invalid URL.');
            return false;
        }

        return true;
    }

    /**
     * Get last error message
     *
     * @return string
     */
    public function getMessage()
    {
        return $this->_lastError ?? '';
    }
}
