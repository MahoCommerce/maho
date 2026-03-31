<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_ApiPlatform
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * API Platform v2 Controller
 * Routes all /api/v2/* requests to Symfony/API Platform.
 * Legacy paths (soap, v2_soap, xmlrpc, jsonrpc) are forwarded to the original Mage_Api controllers.
 */
class Maho_ApiPlatform_V2Controller extends Mage_Core_Controller_Front_Action
{
    private const LEGACY_CONTROLLERS = [
        'soap'    => 'Mage_Api_SoapController',
        'v2_soap' => 'Mage_Api_V2_SoapController',
        'xmlrpc'  => 'Mage_Api_XmlrpcController',
        'jsonrpc' => 'Mage_Api_JsonrpcController',
    ];

    #[\Override]
    public function preDispatch(): static
    {
        parent::preDispatch();

        $pathInfo = trim($this->getRequest()->getPathInfo(), '/');
        $parts = explode('/', $pathInfo);
        $controllerName = $parts[1] ?? '';

        if (isset(self::LEGACY_CONTROLLERS[$controllerName])) {
            $controllerClass = self::LEGACY_CONTROLLERS[$controllerName];
            $controller = new $controllerClass($this->getRequest(), $this->getResponse());
            $controller->dispatch('index');

            $this->setFlag('', self::FLAG_NO_DISPATCH, true);
            $this->setFlag('', self::FLAG_NO_POST_DISPATCH, true);
        }

        return $this;
    }

    /**
     * Catch-all action for API Platform
     */
    public function indexAction(): void
    {
        if (!Mage::helper('maho_apiplatform')->isEnabled()) {
            $this->getResponse()
                ->setHttpResponseCode(503)
                ->setHeader('Content-Type', 'application/json')
                ->setBody(json_encode([
                    'error' => Mage::helper('maho_apiplatform')->__('API Platform is not enabled'),
                    'message' => Mage::helper('maho_apiplatform')->__('Please enable API Platform in System > Configuration > Services > API Platform'),
                ]));
            return;
        }

        // Load Symfony autoloader
        $symfonyDir = Mage::getModuleDir('', 'Maho_ApiPlatform') . '/symfony';

        // Register API Platform namespace (once only)
        static $autoloaderRegistered = false;
        if (!$autoloaderRegistered) {
            $autoloaderRegistered = true;
            spl_autoload_register(function ($class) use ($symfonyDir): void {
                $prefix = 'Maho\\ApiPlatform\\';
                $len = strlen($prefix);

                if (strncmp($prefix, $class, $len) !== 0) {
                    return;
                }

                $relativeClass = substr($class, $len);
                $file = $symfonyDir . '/src/' . str_replace('\\', '/', $relativeClass) . '.php';

                if (file_exists($file)) {
                    require_once $file;
                }
            });
        }

        // Hand off to Symfony
        require $symfonyDir . '/public/index.php';

        // Prevent Maho from sending additional response
        $this->setFlag('', self::FLAG_NO_POST_DISPATCH, true);
        $this->setFlag('', self::FLAG_NO_DISPATCH, true);
    }

    /**
     * GraphQL endpoint
     */
    public function graphqlAction(): void
    {
        $this->indexAction();
    }

    /**
     * Documentation endpoint
     */
    public function docsAction(): void
    {
        $this->indexAction();
    }
}
