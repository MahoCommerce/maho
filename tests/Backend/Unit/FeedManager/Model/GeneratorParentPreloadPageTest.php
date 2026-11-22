<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

class ParentPreloadPageCollection extends Mage_Catalog_Model_Resource_Product_Collection
{
    public int $fullIdCalls = 0;
    public int $sizeCalls = 0;
    public array $loadedPages = [];

    public function __construct(private array $pageItems, private array $fullIds, private int $totalCount, private ?Closure $afterLoad = null)
    {
        $this->_itemObjectClass = Mage_Catalog_Model_Product::class;
        $this->_setIdFieldName('entity_id');
    }

    #[\Override]
    public function getSize(): int
    {
        $this->sizeCalls++;
        return $this->totalCount;
    }

    #[\Override]
    public function getAllIds($limit = null, $offset = null)
    {
        $this->fullIdCalls++;
        return $this->fullIds;
    }

    #[\Override]
    public function load($printQuery = false, $logQuery = false)
    {
        if ($this->isLoaded()) {
            return $this;
        }

        $this->loadedPages[] = [$this->_curPage, $this->_pageSize];
        foreach ($this->pageItems as $product) {
            $this->addItem($product);
        }
        $this->_setIsLoaded();
        if ($this->afterLoad !== null) {
            ($this->afterLoad)($this);
        }
        return $this;
    }

    public function remainingItemCount(): int
    {
        return count($this->_items);
    }
}

class ParentPreloadPageMapper extends Maho_FeedManager_Model_Mapper
{
    public array $preloaded = [];

    public function __construct() {}

    #[\Override]
    public function preloadParentMappings(array $productIds): void
    {
        $this->preloaded[] = $productIds;
    }
}

class ParentPreloadPageGenerator extends Maho_FeedManager_Model_Generator
{
    public array $validated = [];
    public array $written = [];
    public array $preloadsAtWrite = [];

    public function __construct(private ParentPreloadPageCollection $collection, ParentPreloadPageMapper $mapper)
    {
        $this->_mapper = $mapper;
        $this->_batchSize = 2;
    }

    public function processPage(int $page): int
    {
        return $this->_processOneBatch($page);
    }

    public function counters(): array
    {
        return [$this->_processedCount, $this->_errorCount];
    }

    #[\Override]
    protected function _getProductCollection(): Mage_Catalog_Model_Resource_Product_Collection
    {
        return $this->collection;
    }

    #[\Override]
    protected function _validateProductConditions(Mage_Catalog_Model_Product $product): bool
    {
        $this->validated[] = $product->getId();
        return (bool) $product->getData('include_in_feed');
    }

    #[\Override]
    protected function _writeProduct(Mage_Catalog_Model_Product $product): void
    {
        $this->written[] = $product->getId();
        $this->preloadsAtWrite[] = $this->_mapper->preloaded;
    }
}

function parentPreloadPageProduct(int $id, bool $include): Mage_Catalog_Model_Product
{
    $product = new Mage_Catalog_Model_Product();
    $product->setIdFieldName('entity_id');
    $product->setData([
        'entity_id' => $id,
        'sku' => "PAGE-$id",
        'include_in_feed' => $include,
    ]);
    return $product;
}

it('preloads the actual page before filtering and preserves loaded item order', function () {
    $parentless = parentPreloadPageProduct(5, true);
    $filtered = parentPreloadPageProduct(2, false);
    $addedOnLoad = parentPreloadPageProduct(7, true);
    $collection = new ParentPreloadPageCollection(
        [$parentless, $filtered],
        [1, 2, 3, 4, 5, 6, 7, 8],
        8,
        static function (ParentPreloadPageCollection $loaded) use ($addedOnLoad): void {
            $loaded->addItem($addedOnLoad);
        },
    );
    $mapper = new ParentPreloadPageMapper();
    $generator = new ParentPreloadPageGenerator($collection, $mapper);

    expect($generator->processPage(3))->toBe(2)
        ->and($mapper->preloaded)->toBe([[5, 2, 7]])
        ->and($generator->validated)->toBe([5, 2, 7])
        ->and($generator->written)->toBe([5, 7])
        ->and($generator->preloadsAtWrite)->toBe([[[5, 2, 7]], [[5, 2, 7]]])
        ->and($generator->counters())->toBe([3, 0])
        ->and($collection->loadedPages)->toBe([[3, 2]])
        ->and($collection->sizeCalls)->toBeGreaterThan(0)
        ->and($collection->fullIdCalls)->toBe(0)
        ->and($collection->isLoaded())->toBeFalse()
        ->and($collection->remainingItemCount())->toBe(0);
});

it('does not preload broader catalog IDs for an empty actual page', function () {
    // The count says three rows, but page two is empty because a product was deleted.
    $collection = new ParentPreloadPageCollection([], [1, 2, 3], 3);
    $mapper = new ParentPreloadPageMapper();
    $generator = new ParentPreloadPageGenerator($collection, $mapper);

    expect($generator->processPage(2))->toBe(0)
        ->and($mapper->preloaded)->toBe([])
        ->and($generator->validated)->toBe([])
        ->and($generator->written)->toBe([])
        ->and($generator->counters())->toBe([0, 0])
        ->and($collection->loadedPages)->toBe([[2, 2]])
        ->and($collection->sizeCalls)->toBeGreaterThan(0)
        ->and($collection->fullIdCalls)->toBe(0)
        ->and($collection->remainingItemCount())->toBe(0);
});

it('does not preload or load a requested page past the current last page', function (): void {
    $lastProduct = parentPreloadPageProduct(3, true);
    $collection = new ParentPreloadPageCollection([$lastProduct], [1, 2, 3], 3);
    $mapper = new ParentPreloadPageMapper();
    $generator = new ParentPreloadPageGenerator($collection, $mapper);

    expect($generator->processPage(9))->toBe(0)
        ->and($collection->getCurPage())->toBe(2)
        ->and($collection->sizeCalls)->toBeGreaterThan(0)
        ->and($collection->loadedPages)->toBe([])
        ->and($collection->fullIdCalls)->toBe(0)
        ->and($collection->isLoaded())->toBeFalsy()
        ->and($collection->remainingItemCount())->toBe(0)
        ->and($mapper->preloaded)->toBe([])
        ->and($generator->validated)->toBe([])
        ->and($generator->written)->toBe([])
        ->and($generator->counters())->toBe([0, 0]);
});
