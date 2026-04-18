<?php

/**
 * Maho
 *
 * @package    Mage_Core
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2025 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * @method ?Mage_Core_Controller_Varien_Action getAction()
 * @method $this setAction(Mage_Core_Controller_Varien_Action $value)
 * @method bool getNoRender()
 */
class Mage_Core_Controller_Varien_Front extends \Maho\DataObject
{
    protected $_defaults = [];

    /**
     * Available routers array
     *
     * @var array
     */
    protected $_routers = [];

    protected $_urlCache = [];

    public const XML_STORE_ROUTERS_PATH = 'web/routers';

    /**
     * Maximum number of router match iterations before bailing out.
     * Guards against infinite _forward() loops between controllers.
     */
    public const MAX_MATCH_ITERATIONS = 100;

    /**
     * @param array|string $key
     * @param string|null $value
     * @return $this
     */
    public function setDefault($key, $value = null)
    {
        if (is_array($key)) {
            $this->_defaults = $key;
        } else {
            $this->_defaults[$key] = $value;
        }
        return $this;
    }

    /**
     * @param string|null $key
     * @return array|false
     */
    public function getDefault($key = null)
    {
        if (is_null($key)) {
            return $this->_defaults;
        }
        return $this->_defaults[$key] ?? false;
    }

    /**
     * Retrieve request object
     *
     * @return Mage_Core_Controller_Request_Http
     */
    public function getRequest()
    {
        return Mage::app()->getRequest();
    }

    /**
     * Retrieve response object
     *
     * @return Mage_Core_Controller_Response_Http
     */
    public function getResponse()
    {
        return Mage::app()->getResponse();
    }

    /**
     * Adding new router
     *
     * @param   string $name
     * @return  Mage_Core_Controller_Varien_Front
     */
    public function addRouter($name, Mage_Core_Controller_Varien_Router_Abstract $router)
    {
        $router->setFront($this);
        $this->_routers[$name] = $router;
        return $this;
    }

    /**
     * Retrieve router by name
     *
     * @param   string $name
     * @return  Mage_Core_Controller_Varien_Router_Abstract|false
     */
    public function getRouter($name)
    {
        return $this->_routers[$name] ?? false;
    }

    /**
     * Retrieve routers collection
     *
     * @return array
     */
    public function getRouters()
    {
        return $this->_routers;
    }

    /**
     * Init Front Controller
     *
     * @return $this
     */
    public function init()
    {
        Mage::dispatchEvent('controller_front_init_before', ['front' => $this]);

        $routersInfo = Mage::app()->getStore()->getConfig(self::XML_STORE_ROUTERS_PATH);

        if ($routersInfo) {
            foreach ($routersInfo as $routerCode => $routerInfo) {
                if (isset($routerInfo['disabled']) && $routerInfo['disabled']) {
                    continue;
                }
                if (isset($routerInfo['class'])) {
                    $router = new $routerInfo['class']();
                    if ($router instanceof Mage_Core_Controller_Varien_Router_Abstract) {
                        $this->addRouter($routerCode, $router);
                    }
                }
            }
        }

        Mage::dispatchEvent('controller_front_init_routers', ['front' => $this]);

        // Add default router at the last
        $default = new Mage_Core_Controller_Varien_Router_Default();
        $this->addRouter('default', $default);

        return $this;
    }

    /**
     * @return $this
     * @throws Mage_Core_Exception
     */
    public function dispatch()
    {
        $request = $this->getRequest();
        $response = $this->getResponse();

        $request->setPathInfo()->setDispatched(false);

        Mage::dispatchEvent('controller_front_dispatch_before', ['front' => $this]);

        if (!$response->isRedirect()) {
            $this->_matchRoutes($request);
        }

        // This event gives possibility to launch something before sending output (allow cookie setting)
        Mage::dispatchEvent('controller_front_send_response_before', ['front' => $this]);
        \Maho\Profiler::start('mage::app::dispatch::send_response');
        $response->sendResponse();
        \Maho\Profiler::stop('mage::app::dispatch::send_response');
        Mage::dispatchEvent('controller_front_send_response_after', ['front' => $this]);
        return $this;
    }

    /**
     * Execute the router match loop.
     */
    protected function _matchRoutes(Mage_Core_Controller_Request_Http $request): void
    {
        \Maho\Profiler::start('mage::dispatch::routers_match');
        $i = 0;
        while (!$request->isDispatched() && $i++ < self::MAX_MATCH_ITERATIONS) {
            foreach ($this->_routers as $router) {
                /** @var Mage_Core_Controller_Varien_Router_Abstract $router */
                if ($router->match($request)) {
                    break;
                }
            }
        }
        \Maho\Profiler::stop('mage::dispatch::routers_match');
        if ($i > self::MAX_MATCH_ITERATIONS) {
            Mage::throwException(sprintf('Front controller reached %d router match iterations', self::MAX_MATCH_ITERATIONS));
        }
    }

}
