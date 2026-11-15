<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2026 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 */

namespace Maho\Convert\Profile;

use SimpleXMLElement;
use Maho\Convert\Action\AbstractAction;
use Maho\Convert\Container\ContainerInterface;
use Maho\Simplexml\Element;

class Collection
{
    protected ?\SimpleXMLElement $_xml = null;
    protected ?object $_containers = null;
    protected array $_profiles = [];

    protected string $_simplexmlDefaultClass = \Maho\Simplexml\Element::class;
    /** @var class-string<AbstractProfile> */
    protected string $_profileDefaultClass = \Maho\Convert\Profile::class;
    protected string $_profileCollectionDefaultClass = \Maho\Convert\Profile\Collection::class;
    /** @var class-string<ContainerInterface> */
    protected string $_containerDefaultClass = \Maho\Convert\Container\Generic::class;
    /** @var class-string<\Maho\Convert\Container\Collection> */
    protected string $_containerCollectionDefaultClass = \Maho\Convert\Container\Collection::class;

    public function getContainers(): \Maho\Convert\Container\Collection
    {
        if (!$this->_containers) {
            $this->_containers = new $this->_containerCollectionDefaultClass();
            $this->_containers->setDefaultClass($this->_containerDefaultClass);
        }
        return $this->_containers;
    }

    public function getContainer(string|int $name): ContainerInterface
    {
        return $this->getContainers()->getItem($name);
    }

    public function addContainer(string|int|null $name, ContainerInterface $container): ContainerInterface
    {
        $container = $this->getContainers()->addItem($name, $container);
        return $container;
    }

    public function getProfiles(): array
    {
        return $this->_profiles;
    }

    public function getProfile(string $name): ?AbstractProfile
    {
        if (!isset($this->_profiles[$name])) {
            $this->importProfileXml($name);
        }
        return $this->_profiles[$name] ?? null;
    }

    public function addProfile(string $name, ?AbstractProfile $profile = null): AbstractProfile
    {
        $profile ??= new $this->_profileDefaultClass();
        $this->_profiles[$name] = $profile;
        return $profile;
    }

    public function run(string $profile): static
    {
        $this->getProfile($profile)->run();
        return $this;
    }

    public function getClassNameByType(string $type): string
    {
        return $type;
    }

    public function importXml(string|SimpleXMLElement $xml): static
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
            $class = (string) $this->getClassNameByType((string) $containerNode['type']);
            AbstractAction::validateContainerClass($class);
            $container = $this->addContainer((string) $containerNode['name'], new $class());
            foreach ($containerNode->var as $varNode) {
                $container->setVar((string) $varNode['name'], (string) $varNode);
            }
        }
        return $this;
    }

    public function importProfileXml(string $name): static
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
