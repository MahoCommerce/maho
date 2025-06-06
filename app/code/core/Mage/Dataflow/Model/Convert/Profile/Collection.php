<?php

/**
 * Maho
 *
 * @package    Mage_Dataflow
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2025 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Dataflow_Model_Convert_Profile_Collection
{
    protected $_xml;

    protected $_containers;

    protected $_profiles = [];

    protected $_simplexmlDefaultClass = 'Varien_Simplexml_Element';

    protected $_profileDefaultClass = 'Mage_Dataflow_Model_Convert_Profile';

    protected $_profileCollectionDefaultClass = 'Mage_Dataflow_Model_Convert_Profile_Collection';

    protected $_containerDefaultClass = 'Mage_Dataflow_Model_Convert_Container_Generic';

    protected $_containerCollectionDefaultClass = 'Mage_Dataflow_Model_Convert_Container_Collection';

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

    public function addContainer($name, Mage_Dataflow_Model_Convert_Container_Interface $container)
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

    public function addProfile($name, ?Mage_Dataflow_Model_Convert_Profile_Interface $profile = null)
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

            $country = '';

            /** @var Varien_Simplexml_Element $varNode */
            foreach ($actionNode->var as $key => $varNode) {
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
