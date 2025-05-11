<?php

/**
 * Maho
 *
 * @package    Mage_Payment
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Payment_Exception extends Exception
{
    protected $_code = null;

    /**
     * Mage_Payment_Exception constructor.
     * @param string|null $message
     * @param int $code
     */
    public function __construct($message = null, $code = 0)
    {
        $this->_code = $code;
        parent::__construct($message, 0);
    }

    /**
     * @return int|null
     */
    public function getFields()
    {
        return $this->_code;
    }
}
