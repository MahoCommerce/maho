<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Convert\Container;

use Maho\Convert\Exception;
use Maho\Convert\Profile\AbstractProfile;

abstract class AbstractContainer implements ContainerInterface
{
    /** @var array<string, string>|null */
    protected ?array $_vars = null;
    protected ?\Maho\Convert\Profile\AbstractProfile $_profile = null;
    protected mixed $_data = null;
    protected ?string $_position = null;

    public function getVar(string $key, mixed $default = null): mixed
    {
        if (!isset($this->_vars[$key])) {
            return $default;
        }
        return $this->_vars[$key];
    }

    /**
     * @return array<string, string>|null
     */
    public function getVars(): ?array
    {
        return $this->_vars;
    }

    /**
     * @param array<string, string>|string $key
     */
    #[\Override]
    public function setVar(array|string $key, mixed $value = null): static
    {
        if (is_array($key) && is_null($value)) {
            $this->_vars = $key;
        } else {
            $this->_vars[$key] = $value;
        }
        return $this;
    }

    /**
     * @param array<string, string> $vars
     */
    #[\Override]
    public function setVars(array $vars): self
    {
        $this->_vars = $vars;
        return $this;
    }

    public function getProfile(): ?AbstractProfile
    {
        return $this->_profile;
    }

    #[\Override]
    public function setProfile(AbstractProfile $profile): self
    {
        $this->_profile = $profile;
        return $this;
    }

    #[\Override]
    public function getData(): mixed
    {
        if (is_null($this->_data) && $this->getProfile()) {
            $this->_data = $this->getProfile()->getContainer()->getData();
        }
        return $this->_data;
    }

    #[\Override]
    public function setData(mixed $data): self
    {
        if ($this->getProfile()) {
            $this->getProfile()->getContainer()->setData($data);
        }
        $this->_data = $data;
        return $this;
    }

    public function validateDataString(mixed $data = null): bool
    {
        $data ??= $this->getData();
        if (!is_string($data)) {
            $this->addException('Invalid data type, expecting string.', Exception::FATAL);
        }
        return true;
    }

    public function validateDataArray(mixed $data = null): bool
    {
        $data ??= $this->getData();
        if (!is_array($data)) {
            $this->addException('Invalid data type, expecting array.', Exception::FATAL);
        }
        return true;
    }

    public function validateDataGrid(mixed $data = null): bool
    {
        $data ??= $this->getData();
        if (!is_array($data) || !is_array(current($data))) {
            if (count($data) == 0) {
                return true;
            }
            $this->addException('Invalid data type, expecting 2D grid array.', Exception::FATAL);
        }
        return true;
    }

    /**
     * @param array<array<int|string, string|int|float|bool|null>> $grid
     * @return list<int|string>
     */
    public function getGridFields(array $grid): array
    {
        $fields = [];
        foreach ($grid as $row) {
            foreach ($row as $fieldName => $data) {
                if (!in_array($fieldName, $fields)) {
                    $fields[] = $fieldName;
                }
            }
        }
        return $fields;
    }

    #[\Override]
    public function addException(string $error, ?string $level = null): Exception
    {
        $e = new Exception($error);
        $e->setLevel($level ?? Exception::NOTICE);
        $e->setContainer($this);
        $e->setPosition($this->getPosition());

        if ($this->getProfile()) {
            $this->getProfile()->addException($e);
        }

        return $e;
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
