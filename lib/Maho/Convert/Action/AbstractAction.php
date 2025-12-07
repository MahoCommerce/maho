<?php

/**
 * Maho
 *
 * @package    MahoLib
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\Convert\Action;

use Maho\Convert\Container\ContainerInterface;
use Maho\Convert\Exception;
use Maho\Convert\Profile\AbstractProfile;

abstract class AbstractAction implements ActionInterface
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
     *
     * @var AbstractProfile
     */
    protected $_profile;

    /**
     * Action's container
     *
     * @var ContainerInterface
     */
    protected $_container;

    /**
     * Get action parameter
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getParam($key, $default = null)
    {
        if (!isset($this->_params[$key])) {
            return $default;
        }
        return $this->_params[$key];
    }

    /**
     * Set action parameter
     *
     * @param string $key
     * @param mixed $value
     * @return AbstractAction
     */
    public function setParam($key, $value = null)
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
     * @return AbstractAction
     */
    public function setParams($params)
    {
        $this->_params = $params;
        return $this;
    }

    /**
     * Get profile instance the action belongs to
     *
     * @return AbstractProfile
     */
    public function getProfile()
    {
        return $this->_profile;
    }

    /**
     * Set profile instance the action belongs to
     *
     * @return AbstractAction
     */
    public function setProfile(AbstractProfile $profile)
    {
        $this->_profile = $profile;
        return $this;
    }

    /**
     * Set action's container
     *
     * @return AbstractAction
     */
    public function setContainer(ContainerInterface $container)
    {
        $this->_container = $container;
        $this->_container->setProfile($this->getProfile());
        return $this;
    }

    /**
     * Get action's container
     *
     * @param string $name
     * @return ContainerInterface
     */
    public function getContainer($name = null)
    {
        if (!is_null($name)) {
            return $this->getProfile()->getContainer($name);
        }

        if (!$this->_container) {
            $class = $this->getParam('class');
            $this->setContainer(new $class());
        }
        return $this->_container;
    }

    /**
     * Run current action
     *
     * @return AbstractAction
     */
    #[\Override]
    public function run()
    {
        if ($method = $this->getParam('method')) {
            if (!method_exists($this->getContainer(), $method)) {
                $this->addException('Unable to run action method: ' . $method, Exception::FATAL);
            }

            $this->getContainer()->addException('Starting ' . $this->getContainer()::class . ' :: ' . $method);

            if ($this->getParam('from')) {
                $this->getContainer()->setData($this->getContainer($this->getParam('from'))->getData());
            }

            $this->getContainer()->$method();

            if ($this->getParam('to')) {
                $this->getContainer($this->getParam('to'))->setData($this->getContainer()->getData());
            }
        } else {
            $this->addException('No method specified', Exception::FATAL);
        }
        return $this;
    }
}
