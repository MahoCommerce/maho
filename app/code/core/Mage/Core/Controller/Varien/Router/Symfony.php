<?php

/**
 * Maho
 *
 * @package    Mage_Core
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Core_Controller_Varien_Router_Symfony extends Mage_Core_Controller_Varien_Router_Abstract
{
    /**
     * Match the request using Symfony's UrlMatcher.
     *
     * Strategy 1 — Forward dispatch: if module/controller/action are already set
     *   (by CMS, URL rewrite, or _forward()), resolve via reverse lookup map and dispatch.
     *
     * Strategy 2 — URL matching: match the request path against compiled #[Route] attributes.
     *
     * Strategy 3 — Legacy path: on ResourceNotFoundException, parse the path as
     *   frontName/controller/action/key/value (used by DB URL rewrites stored in legacy format).
     */
    #[\Override]
    public function match(Mage_Core_Controller_Request_Http $request): bool
    {
        \Maho\Profiler::start('mage::dispatch::symfony_match');

        $dispatcher = new \Maho\Routing\ControllerDispatcher();

        try {
            if ($request->getModuleName() && $request->getControllerName() && $request->getActionName()) {
                $result = $dispatcher->dispatchForward($request, Mage::app()->getResponse());
                // If forward dispatch failed, return false so legacy routers can handle it.
                // Don't fall through to URL matching — that would re-match the original URL.
                return $result;
            }

            $collection = (new \Maho\Routing\RouteCollectionBuilder())->build();
            $context = new \Symfony\Component\Routing\RequestContext();
            $context->fromRequest($request->getSymfonyRequest());
            $matcher = new \Symfony\Component\Routing\Matcher\UrlMatcher($collection, $context);

            $pathInfo = $request->getPathInfo();
            $normalizedPath = (strlen($pathInfo) > 1) ? rtrim($pathInfo, '/') : $pathInfo;

            $params = $matcher->match($normalizedPath);

            return $dispatcher->dispatch($params, $request, Mage::app()->getResponse());
        } catch (\Symfony\Component\Routing\Exception\ResourceNotFoundException) {
            return $dispatcher->dispatchLegacyPath($request, Mage::app()->getResponse());
        } catch (\Symfony\Component\Routing\Exception\MethodNotAllowedException) {
            return false;
        } finally {
            \Maho\Profiler::stop('mage::dispatch::symfony_match');
        }
    }
}
