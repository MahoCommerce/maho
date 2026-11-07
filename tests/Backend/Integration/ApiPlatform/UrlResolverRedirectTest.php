<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use ApiPlatform\Metadata\Get;
use Mage\Core\Api\UrlResolverProvider;
use Maho\ApiPlatform\Service\StoreContext;

uses(Tests\MahoBackendTestCase::class);

it('resolves a saved redirect with its configured HTTP status', function (string $options, int $status, bool $graphql): void {
    $storeId = (int) Mage::app()->getDefaultStoreView()->getId();
    StoreContext::setStore($storeId);
    $path = 'pest-resolver-' . bin2hex(random_bytes(6));
    $target = 'https://example.com/redirect-target';
    $rewrite = Mage::getModel('core/url_rewrite')
        ->setStoreId($storeId)
        ->setIdPath($path)
        ->setRequestPath($path)
        ->setTargetPath($target)
        ->setOptions($options)
        ->setIsSystem(false)
        ->save();

    try {
        $result = new UrlResolverProvider()->provide(
            new Get(name: $graphql ? 'resolve' : 'url_resolver'),
            context: [$graphql ? 'args' : 'filters' => ['path' => '/' . $path]],
        );

        expect($result->type)->toBe('redirect')
            ->and($result->redirectUrl)->toBe($target)
            ->and($result->redirectType)->toBe($status);
    } finally {
        $rewrite->delete();
        new StoreContext()->reset();
    }
})->with([
    'temporary REST redirect' => ['R', 302, false],
    'permanent REST redirect' => ['RP', 301, false],
    'temporary GraphQL redirect' => ['R', 302, true],
    'permanent GraphQL redirect' => ['RP', 301, true],
]);
