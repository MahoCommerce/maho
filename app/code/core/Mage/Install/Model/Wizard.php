<?php

/**
 * Maho
 *
 * @package    Mage_Install
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Install_Model_Wizard
{
    /**
     * Wizard configuration
     *
     * @var array
     */
    protected $_steps = [];

    public function __construct()
    {
        $this->_steps = Mage::getSingleton('install/config')->getWizardSteps();

        foreach ($this->_steps as $index => $step) {
            $this->_steps[$index]->setUrl(
                $this->_getUrl($this->_steps[$index]->getController(), $this->_steps[$index]->getAction()),
            );

            if (isset($this->_steps[$index + 1])) {
                $this->_steps[$index]->setNextUrl(
                    $this->_getUrl($this->_steps[$index + 1]->getController(), $this->_steps[$index + 1]->getAction()),
                );
                $this->_steps[$index]->setNextUrlPath(
                    $this->_getUrlPath($this->_steps[$index + 1]->getController(), $this->_steps[$index + 1]->getAction()),
                );
            }
            if (isset($this->_steps[$index - 1])) {
                $this->_steps[$index]->setPrevUrl(
                    $this->_getUrl($this->_steps[$index - 1]->getController(), $this->_steps[$index - 1]->getAction()),
                );
                $this->_steps[$index]->setPrevUrlPath(
                    $this->_getUrlPath($this->_steps[$index - 1]->getController(), $this->_steps[$index - 1]->getAction()),
                );
            }
        }
    }

    /**
     * Get wizard step by request
     *
     * @return  Varien_Object | false
     */
    public function getStepByRequest(Mage_Core_Controller_Request_Http $request)
    {
        foreach ($this->_steps as $step) {
            if ($step->getController() == $request->getControllerName()
                    && $step->getAction() == $request->getActionName()
            ) {
                return $step;
            }
        }
        return false;
    }

    /**
     * Get wizard step by name
     *
     * @param   string $name
     * @return  Varien_Object | false
     */
    public function getStepByName($name)
    {
        foreach ($this->_steps as $step) {
            if ($step->getName() == $name) {
                return $step;
            }
        }
        return false;
    }

    /**
     * Get all wizard steps
     *
     * @return array
     */
    public function getSteps()
    {
        return $this->_steps;
    }

    protected function _getUrl($controller, $action)
    {
        return Mage::getUrl($this->_getUrlPath($controller, $action));
    }

    /**
     * Retrieve Url Path
     *
     * @param string $controller
     * @param string $action
     * @return string
     */
    protected function _getUrlPath($controller, $action)
    {
        return 'install/' . $controller . '/' . $action;
    }
}
