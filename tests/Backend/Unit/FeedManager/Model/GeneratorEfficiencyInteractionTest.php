<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

/** Retains native item access, iteration and clear; replaces database loading. */
class EfficiencyInteractionCollection extends Mage_Catalog_Model_Resource_Product_Collection
{
    public array $loadedPages = [];
    public int $fullIdCalls = 0;
    public int $sizeCalls = 0;

    public function __construct(private array $pages, private int $totalCount)
    {
        $this->_itemObjectClass = Mage_Catalog_Model_Product::class;
        $this->_setIdFieldName('entity_id');
    }

    #[\Override]
    public function getSize(): int
    {
        // Synthetic cached count; keep native page clamping without database counting.
        $this->sizeCalls++;
        return $this->totalCount;
    }

    #[\Override]
    public function getAllIds($limit = null, $offset = null)
    {
        $this->fullIdCalls++;
        return [5, 2, 9, 7, 99];
    }

    #[\Override]
    public function load($printQuery = false, $logQuery = false)
    {
        if (!$this->isLoaded()) {
            $this->loadedPages[] = [$this->_curPage, $this->_pageSize];
            foreach ($this->pages[$this->_curPage] ?? [] as $product) {
                $this->addItem($product);
            }
            $this->_setIsLoaded();
        }
        return $this;
    }

    public function remainingItemCount(): int
    {
        return count($this->_items);
    }
}

/** Supplies synthetic parent rows; keeps the real resolver and transformer pipeline. */
class EfficiencyInteractionMapper extends Maho_FeedManager_Model_Mapper
{
    public array $preloads = [];
    public array $extractedIds = [];
    public array $resolvedNames = [];

    public function __construct(private array $parentSkus) {}

    #[\Override]
    public function preloadParentMappings(array $productIds): void
    {
        $this->preloads[] = $productIds;
    }

    #[\Override]
    public function extractProductData(Mage_Catalog_Model_Product $product): array
    {
        $id = (int) $product->getId();
        $latestPreload = array_last($this->preloads) ?? [];
        if (!in_array($id, $latestPreload, true)) {
            throw new LogicException('The current page must preload before extraction.');
        }
        $this->extractedIds[] = $id;
        return [...$product->getData(), 'parent_sku' => $this->parentSkus[$id] ?? null];
    }

    #[\Override]
    public function resolveFieldValue(array $config, array $rawData, Mage_Catalog_Model_Product $product): mixed
    {
        if (($config['source_value'] ?? '') === 'name') {
            $this->resolvedNames[] = [$product->getId(), $config['transformers'] ?? null];
        }
        return parent::resolveFieldValue($config, $rawData, $product);
    }
}

/** Uses the real batch and renderer; output stays in memory without stock or file I/O. */
class EfficiencyInteractionGenerator extends Maho_FeedManager_Model_Generator
{
    public array $xml = [];
    public array $preloadsAtWrite = [];

    public function __construct(
        private EfficiencyInteractionCollection $collection,
        private EfficiencyInteractionMapper $mapper,
        Maho_FeedManager_Model_Feed $feed,
        string $template,
    ) {
        $this->_mapper = $mapper;
        $this->_feed = $feed;
        $this->_batchSize = 2;
        $this->_xmlItemTemplate = $template;
    }

    public function processPage(int $page): int
    {
        return $this->_processOneBatch($page);
    }

    public function render(Mage_Catalog_Model_Product $product, ?string $template = null): string
    {
        if ($template !== null) {
            $this->_xmlItemTemplate = $template;
        }
        return $this->_renderItemTemplate($this->_xmlItemTemplate, $product, $this->_feed);
    }

    public function counters(): array
    {
        return [$this->_processedCount, $this->_productCount, $this->_errorCount, $this->_errors];
    }

    #[\Override]
    protected function _getProductCollection(): Mage_Catalog_Model_Resource_Product_Collection
    {
        return $this->collection;
    }

    #[\Override]
    protected function _validateProductConditions(Mage_Catalog_Model_Product $product): bool
    {
        return (bool) $product->getData('include_in_feed');
    }

    #[\Override]
    protected function _writeProduct(Mage_Catalog_Model_Product $product): void
    {
        $this->preloadsAtWrite[] = $this->mapper->preloads;
        $this->xml[] = $this->render($product);
        $this->_productCount++;
    }

    #[\Override]
    protected function _checkErrorThreshold(int $errorCount, int $processed): void
    {
        // Fail the component test directly; never consult application configuration.
        throw new LogicException(implode('; ', $this->_errors));
    }
}

function efficiencyInteractionProduct(int $id, string $sku, string $name, string $amount, bool $include = true): Mage_Catalog_Model_Product
{
    $product = new ReflectionClass(Mage_Catalog_Model_Product::class)->newInstanceWithoutConstructor();
    $product->setIdFieldName('entity_id');
    return $product->setData([
        'entity_id' => $id, 'sku' => $sku, 'name' => $name, 'amount' => $amount,
        'include_in_feed' => $include,
    ]);
}

it('uses each loaded page while rendering current row values and changed templates on one generator', function (): void {
    $first = efficiencyInteractionProduct(5, '', 'Alpha', '1.25');
    $filtered = efficiencyInteractionProduct(2, 'FILTERED', 'Not rendered', '9.99', false);
    $second = efficiencyInteractionProduct(9, '', 'Beta', '2.50');
    $third = efficiencyInteractionProduct(7, 'CHILD-THREE', 'Gamma', '3.75');
    $collection = new EfficiencyInteractionCollection([1 => [$first, $filtered], 2 => [$second, $third]], 4);
    $mapper = new EfficiencyInteractionMapper([5 => 'PARENT-ONE', 9 => 'PARENT-TWO', 7 => 'UNUSED-PARENT']);
    $feed = new ReflectionClass(Maho_FeedManager_Model_Feed::class)->newInstanceWithoutConstructor();
    $feed->setData(['price_currency' => 'USD', 'price_decimals' => 2]);
    $template = '<item><sku>{type="attribute" value="sku" parent="yes" format="lowercase"}</sku>'
        . '<name>{type="attribute" value="name" transformers="uppercase"}</name>'
        . '<amount>{type="attribute" value="amount" format="price"}</amount></item>';
    $generator = new EfficiencyInteractionGenerator($collection, $mapper, $feed, $template);

    expect($generator->processPage(1))->toBe(1)
        ->and($generator->processPage(2))->toBe(2)
        ->and($mapper->preloads)->toBe([[5, 2], [9, 7]])
        ->and($generator->preloadsAtWrite)->toBe([[[5, 2]], [[5, 2], [9, 7]], [[5, 2], [9, 7]]])
        ->and($generator->xml)->toBe([
            '<item><sku>parent-one</sku><name>ALPHA</name><amount>1.25 USD</amount></item>',
            '<item><sku>parent-two</sku><name>BETA</name><amount>2.50 USD</amount></item>',
            '<item><sku>child-three</sku><name>GAMMA</name><amount>3.75 USD</amount></item>',
        ])
        ->and($mapper->extractedIds)->toBe([5, 9, 7])
        ->and($mapper->resolvedNames)->toBe([[5, 'uppercase'], [9, 'uppercase'], [7, 'uppercase']])
        ->and($generator->counters())->toBe([4, 3, 0, []])
        ->and($collection->fullIdCalls)->toBe(0)
        ->and($collection->loadedPages)->toBe([[1, 2], [2, 2]])
        ->and($collection->sizeCalls)->toBeGreaterThan(0)
        ->and($collection->isLoaded())->toBeFalse()
        ->and($collection->remainingItemCount())->toBe(0);

    // Same template and instance, different feed settings: formatting remains per row.
    $feed->setData('price_currency', 'EUR')->setData('price_decimals', 1);
    expect($generator->render($second))
        ->toBe('<item><sku>parent-two</sku><name>BETA</name><amount>2.5 EUR</amount></item>');

    $changed = '<changed>{type="attribute" value="name" format="lowercase"}:'
        . '{type="attribute" value="sku" parent="yes"}</changed>';
    expect($generator->render($second, $changed))->toBe('<changed>beta:PARENT-TWO</changed>')
        ->and($mapper->extractedIds)->toBe([5, 9, 7, 9, 9])
        ->and($mapper->resolvedNames)->toBe([
            [5, 'uppercase'], [9, 'uppercase'], [7, 'uppercase'], [9, 'uppercase'], [9, null],
        ]);
});
