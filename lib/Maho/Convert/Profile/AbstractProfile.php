<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 */

namespace Maho\Convert\Profile;

use Maho\Convert\Action\ActionInterface;
use Maho\Convert\Container\Collection;
use Maho\Convert\Container\ContainerInterface;
use Maho\Convert\Exception;

abstract class AbstractProfile
{
    /** @var list<ActionInterface> */
    protected array $_actions = [];
    protected ?object $_containers = null;
    /** @var list<Exception> */
    protected array $_exceptions = [];
    protected ?bool $_dryRun = null;

    /** @var class-string<ActionInterface> */
    protected string $_actionDefaultClass = \Maho\Convert\Action::class;
    /** @var class-string<Collection> */
    protected string $_containerCollectionDefaultClass = \Maho\Convert\Container\Collection::class;

    public function addAction(?ActionInterface $action = null): ActionInterface
    {
        $action ??= new $this->_actionDefaultClass();
        $this->_actions[] = $action;
        $action->setProfile($this);
        return $action;
    }

    public function setContainers(Collection $containers): static
    {
        $this->_containers = $containers;
        return $this;
    }

    public function getContainers(): Collection
    {
        if (!$this->_containers) {
            $this->_containers = new $this->_containerCollectionDefaultClass();
        }
        return $this->_containers;
    }

    public function getContainer(string|int|null $name = null): ContainerInterface
    {
        $name ??= '_default';
        return $this->getContainers()->getItem($name);
    }

    public function addContainer(string|int|null $name, ContainerInterface $container): ContainerInterface
    {
        $container = $this->getContainers()->addItem($name, $container);
        $container->setProfile($this);
        return $container;
    }

    /**
     * @return list<Exception>
     */
    public function getExceptions(): array
    {
        return $this->_exceptions;
    }

    public function getDryRun(): ?bool
    {
        return $this->_dryRun;
    }

    public function setDryRun(bool $flag): static
    {
        $this->_dryRun = $flag;
        return $this;
    }

    public function addException(Exception $e): static
    {
        $this->_exceptions[] = $e;
        return $this;
    }

    public function run(): ?static
    {
        if (!$this->_actions) {
            $e = new Exception('Could not find any actions for this profile');
            $e->setLevel(Exception::FATAL);
            $this->addException($e);
            return null;
        }

        foreach ($this->_actions as $action) {
            $action->run();
        }
        return $this;
    }
}
