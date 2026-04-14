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

use Maho\Http\MiddlewareInterface;

class TrailingSlashMiddleware implements MiddlewareInterface
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

        $requestUri = $request->getRequestUri();

        $canonicalUri = preg_replace('#/{2,}#', '/', $requestUri);
        $canonicalUri = \Mage::helper('core/url')->addOrRemoveTrailingSlash($canonicalUri);

        if ($canonicalUri !== $requestUri) {
            $response->setRedirect($canonicalUri, 301);
            return;
        }

        $next();
    }

    private function shouldProcess(\Mage_Core_Controller_Request_Http $request): bool
    {
        if (!\Mage::isInstalled()) {
            return false;
        }

        if ($request->getPost() || strtolower($request->getMethod()) === 'post') {
            return false;
        }

        if (\Mage::helper('adminhtml')->isAdminFrontNameMatched($request->getPathInfo())) {
            return false;
        }

        return true;
    }
}
