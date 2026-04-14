<?php

/**
 * Maho
 *
 * @package    Maho_Http
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace Maho\Http\Middleware;

use Mage;
use Mage_Core_Model_Store;
use Maho\Http\MiddlewareInterface;

class StoreResolutionMiddleware implements MiddlewareInterface
{
    #[\Override]
    public function process(
        \Mage_Core_Controller_Request_Http $request,
        \Mage_Core_Controller_Response_Http $response,
        callable $next,
    ): void {
        if (!Mage::isInstalled()) {
            $next();
            return;
        }

        if (Mage::getStoreConfigFlag(Mage_Core_Model_Store::XML_PATH_STORE_IN_URL)) {
            $this->resolveStoreFromUrl($request);
        }

        $next();
    }

    private function resolveStoreFromUrl(\Mage_Core_Controller_Request_Http $request): void
    {
        $pathInfo = $request->getPathInfo();
        $pathParts = explode('/', ltrim($pathInfo, '/'), 2);
        $storeCode = $pathParts[0];

        if ($request->isDirectAccessFrontendName($storeCode)) {
            return;
        }

        $stores = Mage::app()->getStores(true, true);
        if ($storeCode !== '' && isset($stores[$storeCode])) {
            Mage::app()->setCurrentStore($storeCode);
            $request->setPathInfo('/' . ($pathParts[1] ?? ''));
        } elseif ($storeCode !== '') {
            $request->setActionName('noRoute');
        }
    }
}
