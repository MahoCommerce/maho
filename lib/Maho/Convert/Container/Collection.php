<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 */

namespace Maho\Convert\Container;

class Collection
{
    /** @var array<string|int, ContainerInterface> */
    protected array $_items = [];
    /** @var class-string<ContainerInterface> */
    protected string $_defaultClass = \Maho\Convert\Container\Generic::class;

    public function setDefaultClass(string $className): static
    {
        $this->_defaultClass = $className;
        return $this;
    }

    public function addItem(string|int|null $name, ContainerInterface $item): ContainerInterface
    {
        // No core container has a name, but an extension container can have one
        if ($name === null && method_exists($item, 'getName') && $item->getName()) {
            $name = $item->getName();
        }

        // A nameless container takes the next free index, so it never replaces another one
        if ($name === null) {
            $this->_items[] = $item;
        } else {
            $this->_items[$name] = $item;
        }

        return $item;
    }

    public function getItem(string|int $name): ContainerInterface
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
