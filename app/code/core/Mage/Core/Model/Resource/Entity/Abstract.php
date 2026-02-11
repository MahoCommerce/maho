<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Core
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

abstract class Mage_Core_Model_Resource_Entity_Abstract
{
    protected $_name = null;
    /**
     * Configuration object
     *
     * @var \Maho\Simplexml\Config
     */
    protected $_config = [];

    /**
     * Set config
     *
     * @param \Maho\Simplexml\Config $config
     */
    public function __construct($config)
    {
        $this->_config = $config;
    }

    /**
     * Get config by key
     *
     * @param string $key
     * @return string|boolean
     */
    public function getConfig($key = '')
    {
        if ($key === '') {
            return $this->_config;
        }
        return $this->_config->$key ?? false;
    }
}
