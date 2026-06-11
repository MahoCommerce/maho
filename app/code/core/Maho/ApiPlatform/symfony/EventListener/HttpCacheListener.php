<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

namespace Maho\ApiPlatform\EventListener;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * HTTP Cache Listener - Adds ETag, Cache-Control, and 304 Not Modified support
 *
 * Cache tiers:
 * - Public endpoints (unauthenticated GET): Cache-Control: public, max-age=3600
 * - Authenticated collections: Cache-Control: private, max-age=60, must-revalidate
 * - Authenticated single resources: Cache-Control: private, max-age=300, must-revalidate
 */
#[AsEventListener(event: KernelEvents::RESPONSE, priority: -10)]
class HttpCacheListener
{
    /** Public endpoints that can be cached by CDN/proxies */
    private const PUBLIC_PATHS = [
        '/api/rest/v2/store-config',
        '/api/rest/v2/countries',
        '/api/rest/v2/categories',
    ];

    public function __construct(private readonly Security $security) {}

    public function __invoke(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        $response = $event->getResponse();

        // Only apply to GET/HEAD returning 200
        if (!in_array($request->getMethod(), ['GET', 'HEAD'], true)) {
            return;
        }

        if ($response->getStatusCode() !== Response::HTTP_OK) {
            return;
        }

        // If another listener explicitly set Cache-Control to something other than
        // API Platform's default 'no-cache, private', respect that value
        if ($response->headers->has('Cache-Control')
            && $response->headers->get('Cache-Control') !== 'no-cache, private') {
            return;
        }

        // Generate ETag from response content
        $content = $response->getContent();
        if ($content === false || $content === '') {
            return;
        }

        $etag = '"' . md5($content) . '"';
        $response->headers->set('ETag', $etag);

        // Check If-None-Match for 304 Not Modified
        $ifNoneMatch = $request->headers->get('If-None-Match');
        if ($ifNoneMatch !== null && $ifNoneMatch === $etag) {
            $event->setResponse(new Response('', Response::HTTP_NOT_MODIFIED, [
                'ETag' => $etag,
                'Vary' => 'Authorization, Accept, X-Store-Code',
            ]));
            return;
        }

        // Determine cache tier from the actual security context. The
        // Authorization header is unreliable: Basic-Auth-protected staging
        // sites send it for unrelated reasons, and admin-cookie auth (the
        // bridge listener) authenticates without it.
        $isAuthenticated = $this->security->getUser() !== null;
        $path = $request->getPathInfo();

        if (!$isAuthenticated && $this->isPublicPath($path)) {
            // Public endpoints: CDN-cacheable
            $response->headers->set('Cache-Control', 'public, max-age=3600');
        } elseif ($isAuthenticated && $this->isCollectionPath($path)) {
            // Authenticated collections: short cache, must revalidate
            $response->headers->set('Cache-Control', 'private, max-age=60, must-revalidate');
        } elseif ($isAuthenticated) {
            // Authenticated single resources: moderate cache
            $response->headers->set('Cache-Control', 'private, max-age=300, must-revalidate');
        } else {
            // Unauthenticated non-public: no store
            $response->headers->set('Cache-Control', 'no-store');
        }

        // Always add Vary header
        $response->headers->set('Vary', 'Authorization, Accept, X-Store-Code');
    }

    private function isPublicPath(string $path): bool
    {
        foreach (self::PUBLIC_PATHS as $publicPath) {
            if (str_starts_with($path, $publicPath)) {
                return true;
            }
        }
        return false;
    }

    private function isCollectionPath(string $path): bool
    {
        // Collection endpoints typically don't end with a numeric ID
        // e.g., /api/rest/v2/products is a collection, /api/rest/v2/products/123 is a single resource
        return !preg_match('#/\d+$#', $path);
    }
}
