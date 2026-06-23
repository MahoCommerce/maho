<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * The newsletter unsubscribe route must accept POST in addition to GET so that
 * RFC 8058 one-click unsubscribe works: a mail client that honours the
 * List-Unsubscribe-Post header sends a server-to-server POST to the URL, which
 * would 405 against a GET-only route. The compiled matcher is asserted directly
 * (no dispatch/session) for both verbs.
 */

function unsubscribeRouteMatches(string $httpMethod): array
{
    $context = new \Symfony\Component\Routing\RequestContext();
    $context->setMethod($httpMethod);
    $matcher = \Maho\Routing\RouteCollectionBuilder::createMatcher($context);

    return $matcher->match('/newsletter/subscriber/unsubscribe');
}

describe('Newsletter unsubscribe route accepts both GET and POST', function () {
    it('matches under GET (the link a human clicks)', function () {
        $params = unsubscribeRouteMatches('GET');

        expect($params)->toBeArray();
        expect($params['_route'] ?? null)->toBe('newsletter.subscriber.unsubscribe');
    });

    it('matches under POST (RFC 8058 one-click)', function () {
        $params = unsubscribeRouteMatches('POST');

        expect($params)->toBeArray();
        expect($params['_route'] ?? null)->toBe('newsletter.subscriber.unsubscribe');
    });
});
