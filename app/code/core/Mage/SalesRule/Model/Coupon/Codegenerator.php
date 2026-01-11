<?php

/**
 * Maho
 *
 * @package    Mage_SalesRule
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2025 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Class Mage_SalesRule_Model_Coupon_Codegenerator
 *
 * @package    Mage_SalesRule
 *
 * @method string getAlphabet()
 * @method int getLength()
 * @method int  getLengthMax()
 * @method int  getLengthMin()
 */
class Mage_SalesRule_Model_Coupon_Codegenerator extends \Maho\DataObject implements Mage_SalesRule_Model_Coupon_CodegeneratorInterface
{
    /**
     * Retrieve generated code
     *
     * @return string
     */
    #[\Override]
    public function generateCode()
    {
        $alphabet = ($this->getAlphabet() ?: 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789');
        $lengthMin = ($this->getLengthMin() ?: 16);
        $lengthMax = ($this->getLengthMax() ?: 32);
        $length = ($this->getLength() ?: random_int($lengthMin, $lengthMax));
        $result = '';
        $indexMax = strlen($alphabet) - 1;
        for ($i = 0; $i < $length; $i++) {
            $index = random_int(0, $indexMax);
            $result .= $alphabet[$index];
        }
        return $result;
    }

    /**
     * Retrieve delimiter
     *
     * @return string
     */
    #[\Override]
    public function getDelimiter()
    {
        return ($this->getData('delimiter') ?: '-');
    }
}
