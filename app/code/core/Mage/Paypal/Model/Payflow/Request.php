<?php

/**
 * Maho
 *
 * @package    Mage_Paypal
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2025 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Paypal_Model_Payflow_Request extends \Maho\DataObject
{
    /**
     * Set/Get attribute wrapper
     * Also add length path if key contains = or &
     *
     * @param   string $method
     * @param   array $args
     * @return  mixed
     */
    #[\Override]
    public function __call($method, $args)
    {
        $key = $this->_underscore(substr($method, 3));
        if (isset($args[0]) && (strstr($args[0], '=') || strstr($args[0], '&'))) {
            $key .= '[' . strlen($args[0]) . ']';
        }
        return match (substr($method, 0, 3)) {
            'get' => $this->getData($key, $args[0] ?? null),
            'set' => $this->setData($key, $args[0] ?? null),
            'uns' => $this->unsetData($key),
            'has' => isset($this->_data[$key]),
            default => throw new \Maho\Exception('Invalid method ' . static::class . '::' . $method . '(' . print_r($args, true) . ')'),
        };
    }
}
