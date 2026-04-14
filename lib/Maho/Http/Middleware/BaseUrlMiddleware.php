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

class BaseUrlMiddleware implements MiddlewareInterface
{
    #[\Override]
    public function process(
        \Mage_Core_Controller_Request_Http $request,
        \Mage_Core_Controller_Response_Http $response,
        callable $next,
    ): void {
        if (!$this->shouldProcess($request)) {
            $next();
            return;
        }

        $redirectCode = Mage::getStoreConfigAsInt('web/url/redirect_to_base');
        if (!$redirectCode) {
            $next();
            return;
        }
        if ($redirectCode !== 301) {
            $redirectCode = 302;
        }

        if (Mage::helper('adminhtml')->isAdminFrontNameMatched($request->getPathInfo())) {
            $next();
            return;
        }

        $baseUrl = Mage::getBaseUrl(
            Mage_Core_Model_Store::URL_TYPE_WEB,
            Mage::app()->isCurrentlySecure(),
        );
        if (!$baseUrl) {
            $next();
            return;
        }

        $uri = @parse_url($baseUrl);
        $requestUri = $request->getRequestUri() ?: '/';

        if (
            (isset($uri['scheme']) && $uri['scheme'] !== $request->getScheme())
            || (isset($uri['host']) && $uri['host'] !== $request->getHttpHost())
            || (isset($uri['path']) && !str_contains($requestUri, $uri['path']))
        ) {
            $response->setRedirect($baseUrl, $redirectCode);
            return;
        }

        $next();
    }

    private function shouldProcess(\Mage_Core_Controller_Request_Http $request): bool
    {
        if (!Mage::isInstalled()) {
            return false;
        }

        if ($request->getPost() || strtolower($request->getMethod()) === 'post') {
            return false;
        }

        return true;
    }
}
