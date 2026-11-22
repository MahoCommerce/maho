<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Dataflow
 */

declare(strict_types=1);

class Mage_Dataflow_Model_Convert_Exception extends \Maho\Exception
{
    public const string NOTICE = 'NOTICE';
    public const string WARNING = 'WARNING';
    public const string ERROR = 'ERROR';
    public const string FATAL = 'FATAL';

    protected ?Mage_Dataflow_Model_Convert_Container_Interface $_container = null;

    protected ?string $_level = null;

    protected ?string $_position = null;

    public function setContainer(?Mage_Dataflow_Model_Convert_Container_Interface $container): static
    {
        $this->_container = $container;
        return $this;
    }

    public function getContainer(): ?Mage_Dataflow_Model_Convert_Container_Interface
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
