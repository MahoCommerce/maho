<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

class Mage_Core_Model_Translate_Expr
{
    protected string $_text;
    protected string $_module;

    public function __construct(string $text = '', string $module = '')
    {
        $this->_text = $text;
        $this->_module = $module;
    }

    public function setText(string $text): static
    {
        $this->_text = $text;
        return $this;
    }

    public function setModule(string $module): static
    {
        $this->_module = $module;
        return $this;
    }

    public function getText(): string
    {
        return $this->_text;
    }

    public function getModule(): string
    {
        return $this->_module;
    }

    public function getCode(string $separator = '::'): string
    {
        return $this->getModule() . $separator . $this->getText();
    }
}
