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

use SimpleXMLElement;
use Maho\Convert\Container\ContainerInterface;
use Maho\Simplexml\Element;

class Collection
{
    protected $_xml;
    protected $_containers;
    protected $_profiles = [];

    protected $_simplexmlDefaultClass = \Maho\Simplexml\Element::class;
    protected $_profileDefaultClass = \Maho\Convert\Profile::class;
    protected $_profileCollectionDefaultClass = \Maho\Convert\Profile\Collection::class;
    protected $_containerDefaultClass = \Maho\Convert\Container\Generic::class;
    protected $_containerCollectionDefaultClass = \Maho\Convert\Container\Collection::class;

    public function getContainers()
    {
        if (!$this->_containers) {
            $this->_containers = new $this->_containerCollectionDefaultClass();
            $this->_containers->setDefaultClass($this->_containerDefaultClass);
        }
        return $this->_containers;
    }

    public function getContainer($name)
    {
        return $this->getContainers()->getItem($name);
    }

    public function addContainer($name, ContainerInterface $container)
    {
        $container = $this->getContainers()->addItem($name, $container);
        return $container;
    }

    public function getProfiles()
    {
        return $this->_profiles;
    }

    public function getProfile($name)
    {
        if (!isset($this->_profiles[$name])) {
            $this->importProfileXml($name);
        }
        return $this->_profiles[$name];
    }

    public function addProfile($name, ?AbstractProfile $profile = null)
    {
        if (is_null($profile)) {
            $profile = new $this->_profileDefaultClass();
        }
        $this->_profiles[$name] = $profile;
        return $profile;
    }

    public function run($profile)
    {
        $this->getProfile($profile)->run();
        return $this;
    }

    public function getClassNameByType($type)
    {
        return $type;
    }

    public function importXml($xml)
    {
        if (is_string($xml)) {
            $xml = simplexml_load_string($xml, $this->_simplexmlDefaultClass);
        }
        if (!$xml instanceof SimpleXMLElement) {
            return $this;
        }
        $this->_xml = $xml;

        foreach ($xml->container as $containerNode) {
            if (!$containerNode['name'] || !$containerNode['type']) {
                continue;
            }
            $class = $this->getClassNameByType((string) $containerNode['type']);
            $container = $this->addContainer((string) $containerNode['name'], new $class());
            foreach ($containerNode->var as $varNode) {
                $container->setVar((string) $varNode['name'], (string) $varNode);
            }
        }
        return $this;
    }

    public function importProfileXml($name)
    {
        if (!$this->_xml) {
            return $this;
        }
        $nodes = $this->_xml->xpath("//profile[@name='" . $name . "']");
        if (!$nodes) {
            return $this;
        }
        $profileNode = $nodes[0];

        $profile = $this->addProfile($name);
        $profile->setContainers($this->getContainers());
        foreach ($profileNode->action as $actionNode) {
            $action = $profile->addAction();
            foreach ($actionNode->attributes() as $key => $value) {
                $action->setParam($key, (string) $value);
            }

            if ($actionNode['use']) {
                $container = $profile->getContainer((string) $actionNode['use']);
            } else {
                $action->setParam('class', $this->getClassNameByType((string) $actionNode['type']));
                $container = $action->getContainer();
            }
            $action->setContainer($container);
            if ($action->getParam('name')) {
                $this->addContainer($action->getParam('name'), $container);
            }
            foreach ($actionNode->var as $varNode) {
                $container->setVar((string) $varNode['name'], (string) $varNode);
            }
        }

        return $this;
    }
}
