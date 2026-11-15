<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2025 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Dataflow
 */

/**
 * @package    Mage_Dataflow
 */
abstract class Mage_Dataflow_Model_Convert_Profile_Abstract implements Mage_Dataflow_Model_Convert_Profile_Interface
{
    protected array $_actions = [];

    protected ?object $_containers = null;

    protected array $_exceptions = [];

    protected ?bool $_dryRun = null;

    /** @var class-string<Mage_Dataflow_Model_Convert_Action_Interface> */
    protected string $_actionDefaultClass = 'Mage_Dataflow_Model_Convert_Action';

    /** @var class-string<Mage_Dataflow_Model_Convert_Container_Collection> */
    protected string $_containerCollectionDefaultClass = 'Mage_Dataflow_Model_Convert_Container_Collection';

    protected ?array $_dataflow_profile = null;

    #[\Override]
    public function addAction(?Mage_Dataflow_Model_Convert_Action_Interface $action = null): Mage_Dataflow_Model_Convert_Action_Interface
    {
        $action ??= new $this->_actionDefaultClass();
        $this->_actions[] = $action;
        $action->setProfile($this);
        return $action;
    }

    #[\Override]
    public function setContainers(Mage_Dataflow_Model_Convert_Container_Collection $containers): static
    {
        $this->_containers = $containers;
        return $this;
    }

    public function getContainers(): Mage_Dataflow_Model_Convert_Container_Collection
    {
        if (!$this->_containers) {
            $this->_containers = new $this->_containerCollectionDefaultClass();
        }
        return $this->_containers;
    }

    #[\Override]
    public function getContainer(string|int|null $name = null): Mage_Dataflow_Model_Convert_Container_Interface
    {
        $name ??= '_default';
        return $this->getContainers()->getItem($name);
    }

    #[\Override]
    public function addContainer(string|int|null $name, Mage_Dataflow_Model_Convert_Container_Interface $container): Mage_Dataflow_Model_Convert_Container_Interface
    {
        $container = $this->getContainers()->addItem($name, $container);
        $container->setProfile($this);
        return $container;
    }

    public function getExceptions(): array
    {
        return $this->_exceptions;
    }

    #[\Override]
    public function getDryRun(): ?bool
    {
        return $this->_dryRun;
    }

    public function setDryRun(bool $flag): static
    {
        $this->_dryRun = $flag;
        return $this;
    }

    #[\Override]
    public function addException(Mage_Dataflow_Model_Convert_Exception $e): static
    {
        $this->_exceptions[] = $e;
        return $this;
    }

    public function importXml(\Maho\Simplexml\Element $profileNode): static
    {
        foreach ($profileNode->action as $actionNode) {
            $action = $this->addAction();
            $action->importXml($actionNode);
        }

        return $this;
    }

    #[\Override]
    public function run(): static
    {
        if (!$this->_actions) {
            $e = new Mage_Dataflow_Model_Convert_Exception('Could not find any actions for this profile');
            $e->setLevel(Mage_Dataflow_Model_Convert_Exception::FATAL);
            $this->addException($e);
            return $this;
        }

        foreach ($this->_actions as $action) {
            /** @var Mage_Dataflow_Model_Convert_Action $action */
            try {
                $action->run();
            } catch (Exception $e) {
                $dfe = new Mage_Dataflow_Model_Convert_Exception($e->getMessage());
                $dfe->setLevel(Mage_Dataflow_Model_Convert_Exception::FATAL);
                $this->addException($dfe);
                return $this;
            }
        }
        return $this;
    }

    public function setDataflowProfile(mixed $profile): static
    {
        if (is_array($profile)) {
            $this->_dataflow_profile = $profile;
        }
        return $this;
    }

    public function getDataflowProfile(): ?array
    {
        return $this->_dataflow_profile;
    }
}
