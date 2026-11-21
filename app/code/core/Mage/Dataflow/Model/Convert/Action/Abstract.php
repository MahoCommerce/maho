<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2026 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Dataflow
 */

/**
 * Convert action abstract
 *
 * Instances of this class are used as actions in profile
 *
 * @package    Mage_Dataflow
 */
abstract class Mage_Dataflow_Model_Convert_Action_Abstract implements Mage_Dataflow_Model_Convert_Action_Interface
{
    /**
     * Action parameters
     *
     * Hold information about action container
     *
     * @var array
     */
    protected $_params;

    /**
     * Reference to profile this action belongs to
     */
    protected ?Mage_Dataflow_Model_Convert_Profile_Interface $_profile = null;

    protected array $_actions = [];

    /**
     * Action's container
     */
    protected ?Mage_Dataflow_Model_Convert_Container_Interface $_container = null;

    protected string $_actionDefaultClass = 'Mage_Dataflow_Model_Convert_Action';

    /**
     * Get action parameter
     */
    #[\Override]
    public function getParam(string $key, mixed $default = null): mixed
    {
        return $this->_params[$key] ?? $default;
    }

    /**
     * Set action parameter, or all parameters when $key is an array
     */
    #[\Override]
    public function setParam(array|string $key, mixed $value = null): static
    {
        if (is_array($key) && is_null($value)) {
            $this->_params = $key;
        } else {
            $this->_params[$key] = $value;
        }
        return $this;
    }

    /**
     * Get all action parameters
     *
     * @return array
     */
    public function getParams()
    {
        return $this->_params;
    }

    /**
     * Set all action parameters
     *
     * @param array $params
     * @return Mage_Dataflow_Model_Convert_Action_Abstract
     */
    public function setParams($params)
    {
        $this->_params = $params;
        return $this;
    }

    /**
     * Get profile instance the action belongs to
     */
    public function getProfile(): ?Mage_Dataflow_Model_Convert_Profile_Interface
    {
        return $this->_profile;
    }

    /**
     * Set profile instance the action belongs to
     */
    #[\Override]
    public function setProfile(Mage_Dataflow_Model_Convert_Profile_Interface $profile): static
    {
        $this->_profile = $profile;
        return $this;
    }

    public function addAction(?Mage_Dataflow_Model_Convert_Action_Interface $action = null): Mage_Dataflow_Model_Convert_Action_Interface
    {
        $action ??= new $this->_actionDefaultClass();
        $this->_actions[] = $action;
        $action->setProfile($this->getProfile());
        return $action;
    }

    /**
     * Set action's container
     */
    #[\Override]
    public function setContainer(Mage_Dataflow_Model_Convert_Container_Interface $container): static
    {
        $this->_container = $container;
        if ($this->getProfile()) {
            $this->_container->setProfile($this->getProfile());
        }
        $this->_container->setAction($this);
        return $this;
    }

    /**
     * Get action's container
     */
    #[\Override]
    public function getContainer(string|int|null $name = null): Mage_Dataflow_Model_Convert_Container_Interface
    {
        if (!is_null($name)) {
            return $this->getProfile()->getContainer($name);
        }

        if (!$this->_container) {
            $class = $this->getParam('class');
            $class = is_object($class) ? $class::class : (string) $class;
            self::validateContainerClass($class);
            $this->setContainer(new $class());
        }
        return $this->_container;
    }

    /**
     * Throw an exception when $class is not a Dataflow container.
     *
     * The class comes from the type attribute of the profile XML. Do this check before the class is instantiated.
     *
     * @throws Mage_Core_Exception
     */
    public static function validateContainerClass(string $class): void
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_\\\\]*$/', $class)
            || !is_subclass_of($class, Mage_Dataflow_Model_Convert_Container_Interface::class)
        ) {
            Mage::throwException(Mage::helper('dataflow')->__('The profile XML names a type that is not a Dataflow container.'));
        }
    }

    #[\Override]
    public function importXml(\Maho\Simplexml\Element $actionNode): static
    {
        foreach ($actionNode->attributes() as $key => $value) {
            $this->setParam($key, (string) $value);
        }

        if ($actionNode['use']) {
            $container = $this->getProfile()->getContainer((string) $actionNode['use']);
        } else {
            $this->setParam('class', Mage::getConfig()->getModelClassName((string) $actionNode['type']));
            $container = $this->getContainer();
        }
        $this->setContainer($container);
        if ($this->getParam('name')) {
            $this->getProfile()->addContainer($this->getParam('name'), $container);
        }
        foreach ($actionNode->var as $varNode) {
            $container->setVar((string) $varNode['name'], (string) $varNode);
        }
        foreach ($actionNode->action as $actionSubnode) {
            $action = $this->addAction();
            $action->importXml($actionSubnode);
        }

        return $this;
    }

    /**
     * Run current action
     *
     * @return Mage_Dataflow_Model_Convert_Action_Abstract
     */
    #[\Override]
    public function run(array $args = [])
    {
        if ($method = $this->getParam('method')) {
            if (!is_callable([$this->getContainer(), $method])) {
                throw $this->getContainer()->addException(
                    'Unable to run action method: ' . $method,
                    Mage_Dataflow_Model_Convert_Exception::FATAL,
                );
            }

            $this->getContainer()->addException('Starting ' . $this->getContainer()::class . ' :: ' . $method);

            if ($this->getParam('from')) {
                $this->getContainer()->setData($this->getContainer($this->getParam('from'))->getData());
            }

            $this->getContainer()->$method($args);

            if ($this->getParam('to')) {
                $this->getContainer($this->getParam('to'))->setData($this->getContainer()->getData());
            }
        } else {
            $this->getContainer()->addException('No method specified', Mage_Dataflow_Model_Convert_Exception::FATAL);
        }
        return $this;
    }

    public function runActions(array $args = []): static
    {
        if (empty($this->_actions)) {
            return $this;
        }
        foreach ($this->_actions as $action) {
            $action->run($args);
        }
        return $this;
    }
}
