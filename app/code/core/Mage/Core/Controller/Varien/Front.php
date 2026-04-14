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

        // Build route registry from config.xml (replaces Router_Standard/Admin metadata)
        \Maho\Routing\RouteRegistry::init();

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
        while (!$request->isDispatched() && $i++ < 100) {
            // Try Symfony route matcher first (attribute routes + XML catch-alls)
            // Don't break — a controller may _forward() which unsets dispatched,
            // and the while loop needs to continue to process the forward.
            if (!$this->_matchSymfonyRoute($request)) {
                // Fall back to legacy router loop (CMS, custom routers, default/404)
                foreach ($this->_routers as $router) {
                    /** @var Mage_Core_Controller_Varien_Router_Abstract $router */
                    if ($router->match($request)) {
                        break;
                    }
                }
            }
        }
        \Maho\Profiler::stop('mage::dispatch::routers_match');
        if ($i > 100) {
            Mage::throwException('Front controller reached 100 router match iterations');
        }
    }

    /**
     * Try to match the request using Symfony's UrlMatcher.
     *
     * Two matching strategies:
     * 1. URL-based: matches the request path against compiled #[Route] attributes and XML catch-alls
     * 2. Forward-based: when another router (CMS, Default) has set module/controller/action on the
     *    request, resolves via the reverse lookup map and dispatches the attributed controller
     */
    protected function _matchSymfonyRoute(Mage_Core_Controller_Request_Http $request): bool
    {
        \Maho\Profiler::start('mage::dispatch::symfony_match');

        try {
            $dispatcher = new \Maho\Routing\ControllerDispatcher();

            // Strategy 1: If another router has set module/controller/action, dispatch via reverse lookup
            // This handles _forward() calls where the controller/action changed but the URL didn't
            if ($request->getModuleName() && $request->getControllerName() && $request->getActionName()) {
                $result = $dispatcher->dispatchForward($request, Mage::app()->getResponse());
                if ($result) {
                    return true;
                }
                // If forward dispatch failed, let legacy routers handle it — don't fall through
                // to URL matching which would re-match the original URL, not the forwarded action
                return false;
            }

            // Strategy 2: URL path matching (strip trailing slash for matching)
            $collection = (new \Maho\Routing\RouteCollectionBuilder())->build();
            $context = new \Symfony\Component\Routing\RequestContext();
            $context->fromRequest($request->getSymfonyRequest());
            $matcher = new \Symfony\Component\Routing\Matcher\UrlMatcher($collection, $context);

            $pathInfo = $request->getPathInfo();
            $normalizedPath = (strlen($pathInfo) > 1) ? rtrim($pathInfo, '/') : $pathInfo;

            $params = $matcher->match($normalizedPath);

            return $dispatcher->dispatch(
                $params,
                $request,
                Mage::app()->getResponse(),
            );
        } catch (\Symfony\Component\Routing\Exception\ResourceNotFoundException) {
            // Strategy 3: Parse legacy internal paths (frontName/controller/action/key/value)
            // These come from URL rewrites which store target paths in legacy format
            return $dispatcher->dispatchLegacyPath($request, Mage::app()->getResponse());
        } catch (\Symfony\Component\Routing\Exception\MethodNotAllowedException) {
            return false;
        } finally {
            \Maho\Profiler::stop('mage::dispatch::symfony_match');
        }
    }

}
