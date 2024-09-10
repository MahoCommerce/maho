<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Core
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2023 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Validate URL
 *
 * @category   Mage
 * @package    Mage_Core
 */
class Mage_Core_Model_Url_Validator extends Zend_Validate_Abstract
{
    /**
     * Error keys
     */
    public const INVALID_URL = 'invalidUrl';

    /**
     * Object constructor
     */
    public function __construct()
    {
        // set translated message template
        $this->setMessage(Mage::helper('core')->__("Invalid URL '%value%'."), self::INVALID_URL);
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
    #[\Override]
    public function isValid($value)
    {
        $this->_setValue($value);

        //check valid URL
        if (!Zend_Uri::check($value)) {
            $this->_error(self::INVALID_URL);
            return false;
        }

        return true;
    }
}
