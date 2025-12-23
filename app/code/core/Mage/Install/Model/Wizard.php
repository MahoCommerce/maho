<?php

/**
 * Maho
 *
 * @package    Mage_Install
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Install_Model_Wizard
{
    /**
     * Wizard configuration
     *
     * @var array<Maho\DataObject>
     */
    protected array $_steps = [];

    public function __construct()
    {
        $this->_steps = Mage::getSingleton('install/config')->getWizardSteps();

        foreach (array_keys($this->_steps) as $index) {
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
     */
    public function getStepByRequest(Mage_Core_Controller_Request_Http $request): Maho\DataObject|false
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
     */
    public function getStepByName(string $name): Maho\DataObject|false
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
     * @return array<Maho\DataObject>
     */
    public function getSteps(): array
    {
        return $this->_steps;
    }

    protected function _getUrl(string $controller, string $action): string
    {
        return Mage::getUrl($this->_getUrlPath($controller, $action));
    }

    /**
     * Retrieve Url Path
     */
    protected function _getUrlPath(string $controller, string $action): string
    {
        return 'install/' . $controller . '/' . $action;
    }
}
