<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

final class CatalogUnusedPathTestResource
{
    public array $lookups = [];
    public array $incrementQueries = [];

    public function __construct(private array $paths) {}

    public function getRewriteByRequestPath(string $path, int $storeId): Maho\DataObject|false
    {
        $this->lookups[] = [$path, $storeId];
        if (count($this->lookups) >= 3) {
            throw new RuntimeException('The URL generator repeated its fallback lookup.');
        }
        if (!isset($this->paths[$path])) {
            return false;
        }
        return new Maho\DataObject(['id' => 1, 'id_path' => $this->paths[$path], 'request_path' => $path]);
    }

    public function getLastUsedRewriteRequestIncrement(string $prefix, string $suffix, int $storeId): int
    {
        $this->incrementQueries[] = [$prefix, $suffix, $storeId];
        $maximum = 0;
        foreach (array_keys($this->paths) as $path) {
            if (preg_match('#^' . preg_quote($prefix, '#') . '([0-9]+)' . preg_quote($suffix, '#') . '$#', $path, $match)) {
                $maximum = max($maximum, (int) $match[1]);
            }
        }
        return $maximum;
    }
}

final class CatalogUnusedPathTestUrl extends Mage_Catalog_Model_Url
{
    public function __construct(private CatalogUnusedPathTestResource $resource, private string $suffix) {}

    #[\Override]
    public function getResource(): CatalogUnusedPathTestResource
    {
        return $this->resource;
    }

    #[\Override]
    public function getProductUrlSuffix($storeId): string
    {
        return $this->suffix;
    }

    #[\Override]
    public function getCategoryUrlSuffix($storeId): string
    {
        return $this->suffix;
    }

    public function cacheRewrite(string $idPath, string $requestPath): void
    {
        $this->_rewrites[$idPath] = new Maho\DataObject(['request_path' => $requestPath]);
    }
}

it('resolves an occupied path that no longer matches the URL key', function (string $path, string $key, string $suffix, string $expected): void {
    $resource = new CatalogUnusedPathTestResource([$path => 'product/2', '-' => 'product/3']);
    $url = new CatalogUnusedPathTestUrl($resource, $suffix);

    expect($url->getUnusedPathByUrlKey(1, $path, 'product/1', $key))->toBe($expected)
        ->and($resource->lookups)->toBe([[$path, 1]]);
})->with([
    'legacy path without suffix' => ['legacy-path', 'desired-key', '', 'desired-key-1'],
    'legacy path with suffix' => ['legacy-path.html', 'desired-key', '.html', 'desired-key-1.html'],
    'occupied fallback without suffix' => ['-', 'desired-key', '', 'desired-key-1'],
    'occupied fallback with suffix' => ['-', 'desired-key', '.html', 'desired-key-1.html'],
    'zero URL key' => ['legacy-path', '0', '', '0-1'],
]);

it('uses the next occupied fallback increment in the requested store', function (string $idPath): void {
    $resource = new CatalogUnusedPathTestResource([
        'legacy-path' => 'product/2',
        '-' => 'product/3',
        'desired-key-9.html' => 'product/4',
    ]);
    $url = new CatalogUnusedPathTestUrl($resource, '.html');

    expect($url->getUnusedPathByUrlKey(7, 'legacy-path', $idPath, 'desired-key'))->toBe('desired-key-10.html')
        ->and($resource->incrementQueries)->toBe([['desired-key-', '.html', 7]]);
})->with(['product/1', 'category/1']);

it('bounds a long fallback key and retains its suffix and increment', function (): void {
    $key = str_repeat('a', 300);
    $suffix = '.html';
    $prefix = str_repeat('a', Mage_Catalog_Model_Url::MAX_REQUEST_PATH_LENGTH - strlen($suffix));
    $occupiedPath = str_repeat('a', Mage_Catalog_Model_Url::MAX_REQUEST_PATH_LENGTH);
    $resource = new CatalogUnusedPathTestResource([
        $occupiedPath => 'product/2',
        '-' => 'product/3',
        $prefix . '-7' . $suffix => 'product/4',
    ]);
    $url = new CatalogUnusedPathTestUrl($resource, $suffix);

    $path = $url->getUnusedPathByUrlKey(1, $key . $suffix, 'product/1', $key);

    expect($path)->toBe($prefix . '-8' . $suffix)
        ->and(strlen($path))->toBeLessThanOrEqual(
            Mage_Catalog_Model_Url::MAX_REQUEST_PATH_LENGTH + Mage_Catalog_Model_Url::ALLOWED_REQUEST_PATH_OVERFLOW,
        )
        ->and($resource->lookups)->toBe([[$occupiedPath, 1]]);
});

it('increments an empty URL key without losing an existing suffix', function (string $suffix): void {
    $path = '-' . $suffix;
    $resource = new CatalogUnusedPathTestResource([$path => 'product/2']);
    $url = new CatalogUnusedPathTestUrl($resource, $suffix);

    expect($url->getUnusedPathByUrlKey(1, $suffix, 'product/1', ''))->toBe('-1' . $suffix);
})->with(['', '.html']);

it('preserves the directory and numeric increment of a matching path', function (): void {
    $resource = new CatalogUnusedPathTestResource([
        'parent/desired-key-7.html' => 'product/2',
        'parent/desired-key-10.html' => 'product/3',
    ]);
    $url = new CatalogUnusedPathTestUrl($resource, '.html');

    expect($url->getUnusedPathByUrlKey(1, 'parent/desired-key-7.html', 'product/1', 'desired-key'))
        ->toBe('parent/desired-key-11.html');
});

it('keeps an unchanged path belonging to the same entity', function (): void {
    $resource = new CatalogUnusedPathTestResource(['legacy-path.html' => 'product/1']);
    $url = new CatalogUnusedPathTestUrl($resource, '.html');

    expect($url->getUnusedPathByUrlKey(1, 'legacy-path.html', 'product/1', 'desired-key'))->toBe('legacy-path.html')
        ->and($resource->incrementQueries)->toBe([]);
});

it('keeps a cached unchanged rewrite without consulting the resource', function (): void {
    $resource = new CatalogUnusedPathTestResource([]);
    $url = new CatalogUnusedPathTestUrl($resource, '.html');
    $url->cacheRewrite('product/1', 'legacy-path.html');

    expect($url->getUnusedPathByUrlKey(1, 'legacy-path.html', 'product/1', 'desired-key'))->toBe('legacy-path.html')
        ->and($resource->lookups)->toBe([]);
});

it('keeps an unoccupied path even when it does not match the key', function (): void {
    $resource = new CatalogUnusedPathTestResource([]);
    $url = new CatalogUnusedPathTestUrl($resource, '.html');

    expect($url->getUnusedPathByUrlKey(1, 'legacy-path.html', 'product/1', 'desired-key'))->toBe('legacy-path.html')
        ->and($resource->incrementQueries)->toBe([]);
});
