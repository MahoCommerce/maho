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
 * HTTP Cache Listener - Adds ETag, Cache-Control, and 304 Not Modified support.
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

        // Admin reads must always be fresh. Never cache them, not even with a
        // 304 against a privately-cached copy, an admin acting on stale data
        // is worse than the round-trip saved.
        if ($this->security->getUser() !== null && $this->security->isGranted('ROLE_ADMIN')) {
            $response->headers->set('Cache-Control', 'no-store');
            $response->headers->set('Vary', 'Authorization, Accept, X-Store-Code');
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
            if ($request->query->has('store')) {
                // Store selected via the `store` query parameter. These public
                // endpoints (categories, store-config, countries) are
                // store-specific, but a shared/CDN cache keyed on URL + Vary
                // cannot distinguish stores here: Vary cannot express a query
                // parameter and many CDNs strip/normalize the query string, so a
                // `public` response could be served to a client targeting a
                // different store (cross-store cache poisoning). Keep it private.
                // Clients that want CDN-cacheable responses must select the store
                // via the X-Store-Code header, which Vary covers.
                $response->headers->set('Cache-Control', 'private, max-age=3600');
            } else {
                // Public endpoints: CDN-cacheable
                $response->headers->set('Cache-Control', 'public, max-age=3600');
            }
        } elseif ($isAuthenticated) {
            // Authenticated reads (collections and single resources): moderate
            // cache. Tag invalidation bounds staleness on writes regardless of
            // TTL, so there's no benefit to a shorter window for collections.
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
        return array_any(self::PUBLIC_PATHS, fn($publicPath) => str_starts_with($path, $publicPath));
    }
}
