<?php

/**
 * Maho
 *
 * @package    MahoLib
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\Convert\Profile;

use Maho\Convert\Action\ActionInterface;
use Maho\Convert\Container\Collection;
use Maho\Convert\Container\ContainerInterface;
use Maho\Convert\Exception;

abstract class AbstractProfile
{
    protected $_actions;
    protected $_containers;
    protected $_exceptions = [];
    protected $_dryRun;

    protected $_actionDefaultClass = \Maho\Convert\Action::class;
    protected $_containerCollectionDefaultClass = \Maho\Convert\Container\Collection::class;

    public function addAction(?ActionInterface $action = null)
    {
        if (is_null($action)) {
            $action = new $this->_actionDefaultClass();
        }
        $this->_actions[] = $action;
        $action->setProfile($this);
        return $action;
    }

    public function setContainers(Collection $containers)
    {
        $this->_containers = $containers;
        return $this;
    }

    public function getContainers()
    {
        if (!$this->_containers) {
            $this->_containers = new $this->_containerCollectionDefaultClass();
        }
        return $this->_containers;
    }

    public function getContainer($name = null)
    {
        if (is_null($name)) {
            $name = '_default';
        }
        return $this->getContainers()->getItem($name);
    }

    public function addContainer($name, ContainerInterface $container)
    {
        $container = $this->getContainers()->addItem($name, $container);
        $container->setProfile($this);
        return $container;
    }

    public function getExceptions()
    {
        return $this->_exceptions;
    }

    public function getDryRun()
    {
        return $this->_dryRun;
    }

    public function setDryRun($flag)
    {
        $this->_dryRun = $flag;
        return $this;
    }

    public function addException(Exception $e)
    {
        $this->_exceptions[] = $e;
        return $this;
    }

    public function run()
    {
        if (!$this->_actions) {
            $e = new Exception('Could not find any actions for this profile');
            $e->setLevel(Exception::FATAL);
            $this->addException($e);
            return;
        }

        foreach ($this->_actions as $action) {
            $action->run();
        }
        return $this;
    }
}
