<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Dataflow
 */

abstract class Mage_Dataflow_Model_Convert_Container_Abstract implements Mage_Dataflow_Model_Convert_Container_Interface
{
    protected array $_batchParams = [];

    protected ?array $_vars = null;

    protected ?\Mage_Dataflow_Model_Convert_Profile_Interface $_profile = null;

    protected ?\Mage_Dataflow_Model_Convert_Action_Interface $_action = null;

    protected mixed $_data = null;

    protected ?string $_position = null;

    /**
     * Detect serialization of data
     */
    protected function isSerialized(mixed $data): bool
    {
        return Mage::helper('core/string')->isSerializedArrayOrObject($data);
    }

    public function getVar(string $key, mixed $default = null): mixed
    {
        if (!isset($this->_vars[$key]) || (!is_array($this->_vars[$key]) && strlen($this->_vars[$key]) == 0)) {
            return $default;
        }
        return $this->_vars[$key];
    }

    public function getVars(): ?array
    {
        return $this->_vars;
    }

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

    public function getAction(): ?Mage_Dataflow_Model_Convert_Action_Interface
    {
        return $this->_action;
    }

    #[\Override]
    public function setAction(Mage_Dataflow_Model_Convert_Action_Interface $action): static
    {
        $this->_action = $action;
        return $this;
    }

    public function getProfile(): ?Mage_Dataflow_Model_Convert_Profile_Interface
    {
        return $this->_profile;
    }

    #[\Override]
    public function setProfile(Mage_Dataflow_Model_Convert_Profile_Interface $profile): static
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
    public function setData(mixed $data): static
    {
        if ($this->validateDataSerialized($data)) {
            if ($this->getProfile()) {
                $this->getProfile()->getContainer()->setData($data);
            }

            $this->_data = $data;
        }

        return $this;
    }

    /**
     * Validate serialized data
     */
    public function validateDataSerialized(mixed $data = null): bool
    {
        $data ??= $this->getData();

        $result = true;
        if ($this->isSerialized($data)) {
            try {
                Mage::helper('core/unserializeArray')->unserialize($data);
            } catch (Exception) {
                $result = false;
                $this->addException(
                    'Invalid data, expecting serialized array.',
                    Mage_Dataflow_Model_Convert_Exception::FATAL,
                );
            }
        }

        return $result;
    }

    public function validateDataString(mixed $data = null): bool
    {
        $data ??= $this->getData();
        if (!is_string($data)) {
            $this->addException('Invalid data type, expecting string.', Mage_Dataflow_Model_Convert_Exception::FATAL);
        }
        return true;
    }

    public function validateDataArray(mixed $data = null): bool
    {
        $data ??= $this->getData();
        if (!is_array($data)) {
            $this->addException('Invalid data type, expecting array.', Mage_Dataflow_Model_Convert_Exception::FATAL);
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
            $this->addException(
                'Invalid data type, expecting 2D grid array.',
                Mage_Dataflow_Model_Convert_Exception::FATAL,
            );
        }
        return true;
    }

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
    public function addException(string $error, ?string $level = null): Mage_Dataflow_Model_Convert_Exception
    {
        $e = new Mage_Dataflow_Model_Convert_Exception($error);
        $e->setLevel($level ?? Mage_Dataflow_Model_Convert_Exception::NOTICE);
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

    public function setBatchParams(mixed $data): static
    {
        if (is_array($data)) {
            $this->_batchParams = $data;
        }
        return $this;
    }

    public function getBatchParams(?string $key = null): mixed
    {
        if (!empty($key)) {
            return $this->_batchParams[$key] ?? null;
        }
        return $this->_batchParams;
    }
}
