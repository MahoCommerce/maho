<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Api2
 */

abstract class Mage_Api2_Model_Route_Abstract extends Mage_Api2_Model_Route_Base
{
    /**
     * Names for parent::__construct params
     */
    public const PARAM_ROUTE      = 'route';
    public const PARAM_DEFAULTS   = 'defaults';
    public const PARAM_REQS       = 'reqs';

    /**
     * Default values of parent::__construct() params
     *
     * @var array<string, mixed>
     */
    protected array $_paramsDefaultValues = [
        self::PARAM_ROUTE      => '',
        self::PARAM_DEFAULTS   => [],
        self::PARAM_REQS       => [],
    ];

    /**
     * Process construct param and call parent::__construct() with params
     *
     * @param array<string, mixed> $arguments
     */
    public function __construct(array $arguments)
    {
        parent::__construct(
            $this->_getArgumentValue(self::PARAM_ROUTE, $arguments),
            $this->_getArgumentValue(self::PARAM_DEFAULTS, $arguments),
            $this->_getArgumentValue(self::PARAM_REQS, $arguments),
        );
    }

    /**
     * Retrieve argument value
     *
     * @param string $name argument name
     * @param array<string, mixed> $arguments
     */
    protected function _getArgumentValue(string $name, array $arguments): mixed
    {
        return $arguments[$name] ?? $this->_paramsDefaultValues[$name];
    }

    /**
     * Matches a Request with parts defined by a map. Assigns and
     * returns an array of variables on a successful match.
     *
     * @param string|Mage_Api2_Model_Request $path Path string or Request object to match
     * @param bool $partial Partial path matching
     * @return array<string, mixed>|false An array of assigned values or false on a mismatch
     */
    #[\Override]
    public function match($path, bool $partial = false): array|false
    {
        // Handle both string paths and Request objects
        if ($path instanceof Mage_Api2_Model_Request) {
            $pathString = ltrim($path->getPathInfo(), $this->_urlDelimiter);
        } else {
            $pathString = $path;
        }

        return parent::match($pathString, $partial);
    }
}
