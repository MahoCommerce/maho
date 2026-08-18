<?php

/**
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

declare(strict_types=1);

/**
 * Base class for configure totals order
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

    public function setCode(?string $value): static
    {
        return $this->setData('code', $value);
    }

    public function setTotalConfigNode(?array $value): static
    {
        return $this->setData('total_config_node', $value);
    }
}
