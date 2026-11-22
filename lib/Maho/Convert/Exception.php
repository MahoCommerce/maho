<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Convert;

use Maho\Convert\Container\ContainerInterface;
use Maho\Exception as MahoException;

class Exception extends MahoException
{
    public const string NOTICE = 'NOTICE';
    public const string WARNING = 'WARNING';
    public const string ERROR = 'ERROR';
    public const string FATAL = 'FATAL';

    protected ?ContainerInterface $_container = null;

    protected ?string $_level = null;

    protected ?string $_position = null;

    public function setContainer(?ContainerInterface $container): static
    {
        $this->_container = $container;
        return $this;
    }

    public function getContainer(): ?ContainerInterface
    {
        return $this->_container;
    }

    public function getLevel(): ?string
    {
        return $this->_level;
    }

    public function setLevel(string $level): static
    {
        $this->_level = $level;
        return $this;
    }

    public function getPosition(): ?string
    {
        return $this->_position;
    }

    public function setPosition(?string $position): static
    {
        $this->_position = $position;
        return $this;
    }
}
