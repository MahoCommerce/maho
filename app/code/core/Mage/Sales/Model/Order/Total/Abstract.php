<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Sales
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
/**
 * Base class for configure totals order
 *
 * @method $this setCode(string $value)
 * @method $this setTotalConfigNode(array $value)
 */
abstract class Mage_Sales_Model_Order_Total_Abstract extends \Maho\DataObject
{
    /**
     * Process model configuration array.
     * This method can be used for changing models apply sort order
     *
     * @param   array $config
     * @return  array
     */
    public function processConfigArray($config)
    {
        return $config;
    }
}
