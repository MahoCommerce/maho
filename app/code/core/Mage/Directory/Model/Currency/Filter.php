<?php

/**
 * Maho
 *
 * @package    Mage_Directory
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Directory_Model_Currency_Filter
{
    /**
     * Rate value
     *
     * @var float
     */
    protected $_rate;

    /**
     * Currency NumberFormatter object
     *
     * @var NumberFormatter
     */
    protected $_currency;

    /**
     * Mage_Directory_Model_Currency_Filter constructor.
     * @param string $code
     * @param int $rate
     */
    public function __construct($code, $rate = 1)
    {
        $this->_currency = Mage::app()->getLocale()->currency($code);
        $this->_rate = $rate;
    }

    /**
     * Set filter rate
     *
     * @param double $rate
     */
    public function setRate($rate)
    {
        $this->_rate = $rate;
    }

    /**
     * Filter value
     *
     * @param   double $value
     * @return  string
     */
    public function filter($value)
    {
        $value = Mage::app()->getLocale()->getNumber($value);
        $value = Mage::app()->getStore()->roundPrice($this->_rate * $value);
        //$value = round($value, 2);
        return $this->_currency->format($value);
    }
}
