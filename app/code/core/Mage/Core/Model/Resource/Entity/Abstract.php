<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

abstract class Mage_Core_Model_Resource_Entity_Abstract
{
    protected $_name = null;
    /**
     * Configuration object
     *
     * @var \Maho\Simplexml\Config|array
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
     * @return \Maho\Simplexml\Config|array|string|false
     */
    public function getConfig($key = '')
    {
        if ($key === '') {
            return $this->_config;
        }
        return $this->_config->$key ?? false;
    }
}
