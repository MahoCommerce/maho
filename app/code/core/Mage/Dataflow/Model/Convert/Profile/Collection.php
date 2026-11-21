<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2026 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Dataflow
 */

class Mage_Dataflow_Model_Convert_Profile_Collection
{
    protected ?\SimpleXMLElement $_xml = null;

    protected ?object $_containers = null;

    protected array $_profiles = [];

    protected string $_simplexmlDefaultClass = \Maho\Simplexml\Element::class;

    /** @var class-string<Mage_Dataflow_Model_Convert_Profile_Abstract> */
    protected string $_profileDefaultClass = 'Mage_Dataflow_Model_Convert_Profile';

    protected string $_profileCollectionDefaultClass = 'Mage_Dataflow_Model_Convert_Profile_Collection';

    /** @var class-string<Mage_Dataflow_Model_Convert_Container_Interface> */
    protected string $_containerDefaultClass = 'Mage_Dataflow_Model_Convert_Container_Generic';

    /** @var class-string<Mage_Dataflow_Model_Convert_Container_Collection> */
    protected string $_containerCollectionDefaultClass = 'Mage_Dataflow_Model_Convert_Container_Collection';

    public function getContainers(): Mage_Dataflow_Model_Convert_Container_Collection
    {
        if (!$this->_containers) {
            $this->_containers = new $this->_containerCollectionDefaultClass();
            $this->_containers->setDefaultClass($this->_containerDefaultClass);
        }
        return $this->_containers;
    }

    public function getContainer(string|int $name): Mage_Dataflow_Model_Convert_Container_Interface
    {
        return $this->getContainers()->getItem($name);
    }

    public function addContainer(string|int|null $name, Mage_Dataflow_Model_Convert_Container_Interface $container): Mage_Dataflow_Model_Convert_Container_Interface
    {
        $container = $this->getContainers()->addItem($name, $container);
        return $container;
    }

    public function getProfiles(): array
    {
        return $this->_profiles;
    }

    public function getProfile(string $name): ?Mage_Dataflow_Model_Convert_Profile_Interface
    {
        if (!isset($this->_profiles[$name])) {
            $this->importProfileXml($name);
        }
        return $this->_profiles[$name] ?? null;
    }

    public function addProfile(string $name, ?Mage_Dataflow_Model_Convert_Profile_Interface $profile = null): Mage_Dataflow_Model_Convert_Profile_Interface
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
            $xml = @simplexml_load_string($xml, $this->_simplexmlDefaultClass);
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
            Mage_Dataflow_Model_Convert_Action_Abstract::validateContainerClass($class);
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
        $profileNode = array_find(
            $this->_xml->xpath('//profile') ?: [],
            static fn(SimpleXMLElement $node): bool => isset($node['name']) && (string) $node['name'] === $name,
        );
        if ($profileNode === null) {
            return $this;
        }

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

            $country = '';

            /** @var \Maho\Simplexml\Element $varNode */
            foreach ($actionNode->var as $varNode) {
                if ($varNode['name'] == 'map') {
                    $mapData = [];
                    foreach ($varNode->map as $mapNode) {
                        $mapData[(string) $mapNode['name']] = (string) $mapNode;
                    }
                    $container->setVar((string) $varNode['name'], $mapData);
                } else {
                    $value = (string) $varNode;

                    /**
                     * Get state name from directory by iso name
                     * (only for US)
                     */
                    if ($value && (string) $varNode['name'] == 'filter/country') {
                        /**
                         * Save country for convert state iso to name (for US only)
                         */
                        $country = $value;
                    } elseif ($value && (string) $varNode['name'] == 'filter/region' && $country == 'US') {
                        /**
                         * Get state name by iso for US
                         */
                        /** @var Mage_Directory_Model_Region $region */
                        $region = Mage::getModel('directory/region');

                        $state = $region->loadByCode($value, $country)->getDefaultName();
                        if ($state) {
                            $value = $state;
                        }
                    }

                    $container->setVar((string) $varNode['name'], $value);
                }
            }
        }

        return $this;
    }
}
