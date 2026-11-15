<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2021-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

declare(strict_types=1);

class Mage_Adminhtml_Model_Report_Item extends \Maho\DataObject
{
    protected bool $_isEmpty  = false;
    protected array $_children = [];

    public function setIsEmpty(bool $flag = true): static
    {
        $this->_isEmpty = $flag;
        return $this;
    }

    public function getIsEmpty(): bool
    {
        return $this->_isEmpty;
    }

    public function hasIsEmpty(): void {}

    public function getChildren(): array
    {
        return $this->_children;
    }

    public function setChildren(array $children): static
    {
        $this->_children = $children;
        return $this;
    }

    public function hasChildren(): bool
    {
        return count($this->_children) > 0;
    }

    public function addChild(\Maho\DataObject $child): static
    {
        $this->_children[] = $child;
        return $this;
    }
}
