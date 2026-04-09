<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_ApiPlatform
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * API Platform Controller
 *
 * Routes /api/* requests to Symfony/API Platform.
 * Legacy paths (soap, v2_soap, xmlrpc, jsonrpc) are forwarded to the original Mage_Api controllers.
 */
class Maho_ApiPlatform_IndexController extends Mage_Core_Controller_Front_Action
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
        if (!Mage::getStoreConfigFlag('apiplatform/general/enabled')) {
            $this->getResponse()
                ->setHttpResponseCode(503)
                ->setHeader('Content-Type', 'application/json')
                ->setBody(json_encode([
                    'error' => 'API Platform is not enabled',
                    'message' => 'Please enable API Platform in System > Configuration > Services > API Platform',
                ]));
            return;
        }

        // Set environment variables for Symfony
        $_ENV['APP_SECRET'] = Mage::getStoreConfig('apiplatform/oauth2/secret')
            ?: hash('sha256', (string) Mage::getConfig()->getNode('global/crypt/key') . 'symfony_app_secret');
        $corsOrigins = Mage::getStoreConfig('apiplatform/general/cors_origins');
        if (!$corsOrigins) {
            $baseUrl = (string) Mage::getStoreConfig('web/secure/base_url');
            $parsed = parse_url($baseUrl);
            $corsOrigins = ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? 'localhost')
                . (isset($parsed['port']) ? ':' . $parsed['port'] : '');
        }
        $_ENV['CORS_ALLOW_ORIGIN'] = $corsOrigins;

        // Boot Symfony kernel
        $kernel = new \Maho\ApiPlatform\Kernel('prod', false);
        $request = \Symfony\Component\HttpFoundation\Request::createFromGlobals();
        $response = $kernel->handle($request);
        $response->send();
        $kernel->terminate($request, $response);

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
