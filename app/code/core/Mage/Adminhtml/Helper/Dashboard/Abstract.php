<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

declare(strict_types=1);

abstract class Mage_Adminhtml_Helper_Dashboard_Abstract extends Mage_Core_Helper_Data
{
    protected ?\Maho\Data\Collection $_collection = null;

    /**
     * Parameters for helper
     *
     * @var array
     */
    protected $_params = [];

    public function getCollection(): \Maho\Data\Collection
    {
        if (is_null($this->_collection)) {
            $this->_initCollection();
        }
        return $this->_collection;
    }

    abstract protected function _initCollection(): void;

    public function getItems(): array
    {
        return $this->getCollection()->getItems();
    }

    public function getCount(): int
    {
        return count($this->getItems());
    }

    public function getColumn(string $index): array
    {
        $result = [];
        foreach ($this->getItems() as $item) {
            if (is_array($item)) {
                if (isset($item[$index])) {
                    $result[] = $item[$index];
                } else {
                    $result[] = null;
                }
            } elseif ($item instanceof \Maho\DataObject) {
                $result[] = $item->getData($index);
            } else {
                $result[] = null;
            }
        }
        return $result;
    }

    public function setParam(string $name, mixed $value): void
    {
        $this->_params[$name] = $value;
    }

    public function setParams(array $params): void
    {
        $this->_params = $params;
    }

    public function getParam(string $name): mixed
    {
        return $this->_params[$name] ?? null;
    }

    public function getParams(): array
    {
        return $this->_params;
    }
}
