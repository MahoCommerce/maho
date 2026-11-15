<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Dataflow
 */

class Mage_Dataflow_Model_Convert_Container_Collection
{
    protected array $_items = [];

    /** @var class-string<Mage_Dataflow_Model_Convert_Container_Interface> */
    protected string $_defaultClass = 'Mage_Dataflow_Model_Convert_Container_Generic';

    public function setDefaultClass(string $className): static
    {
        $this->_defaultClass = $className;
        return $this;
    }

    public function addItem(string|int|null $name, Mage_Dataflow_Model_Convert_Container_Interface $item): Mage_Dataflow_Model_Convert_Container_Interface
    {
        // No core container has a name, but an extension container can have one
        $name ??= method_exists($item, 'getName') && $item->getName() ? $item->getName() : count($this->_items);

        $this->_items[$name] = $item;

        return $item;
    }

    public function getItem(string|int $name): Mage_Dataflow_Model_Convert_Container_Interface
    {
        if (!isset($this->_items[$name])) {
            $this->addItem($name, new $this->_defaultClass());
        }
        return $this->_items[$name];
    }

    public function hasItem(string|int $name): bool
    {
        return isset($this->_items[$name]);
    }
}
