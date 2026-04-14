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
use Maho\Http\MiddlewareInterface;
use Maho\Routing\RouteRegistry;

class ConfigRewriteMiddleware implements MiddlewareInterface
{
    #[\Override]
    public function process(
        \Mage_Core_Controller_Request_Http $request,
        \Mage_Core_Controller_Response_Http $response,
        callable $next,
    ): void {
        $config = Mage::getConfig()->getNode('global/rewrite');
        if ($config) {
            foreach ($config->children() as $rewrite) {
                $from = (string) $rewrite->from;
                $to = (string) $rewrite->to;
                if ($from === '' || $to === '') {
                    continue;
                }
                $from = $this->processRewriteUrl($from);
                $to = $this->processRewriteUrl($to);

                $pathInfo = preg_replace($from, $to, $request->getPathInfo());
                if (isset($rewrite->complete)) {
                    $request->setPathInfo($pathInfo);
                } else {
                    $request->rewritePathInfo($pathInfo);
                }
            }
        }

        $next();
    }

    private function processRewriteUrl(string $url): string
    {
        $startPos = strpos($url, '{');
        if ($startPos !== false) {
            $endPos = strpos($url, '}');
            $routeName = substr($url, $startPos + 1, $endPos - $startPos - 1);
            $frontName = RouteRegistry::getFrontNameByRoute($routeName);
            if ($frontName) {
                $url = str_replace('{' . $routeName . '}', $frontName, $url);
            }
        }
        return $url;
    }
}
