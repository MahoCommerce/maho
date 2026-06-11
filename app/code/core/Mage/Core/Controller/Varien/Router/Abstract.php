<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

abstract class Mage_Core_Controller_Varien_Router_Abstract
{
    protected $_front;

    /**
     * @param Mage_Core_Controller_Varien_Front $front
     * @return $this
     */
    public function setFront($front)
    {
        $this->_front = $front;
        return $this;
    }

    /**
     * @return Mage_Core_Controller_Varien_Front
     */
    public function getFront()
    {
        return $this->_front;
    }

    /**
     * @param string $routeName
     * @return string
     */
    public function getFrontNameByRoute($routeName)
    {
        return $routeName;
    }

    /**
     * @param string $frontName
     * @return string
     */
    public function getRouteByFrontName($frontName)
    {
        return $frontName;
    }

    abstract public function match(Mage_Core_Controller_Request_Http $request): bool;
}
