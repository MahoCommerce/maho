<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_ApiPlatform
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * API Platform v2 Controller
 * Routes all /api/v2/* requests to Symfony/API Platform
 */
class Maho_ApiPlatform_V2Controller extends Mage_Core_Controller_Front_Action
{
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

        // Register API Platform namespace
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
