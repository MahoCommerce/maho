<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class GooglePricingTest extends TestCase
{
    private Maho_FeedManager_Model_Mapper $mapper;
    private Mage_Catalog_Model_Product $product;
    private array $transformerState;

    #[\Override]
    protected function setUp(): void
    {
        $this->transformerState = new ReflectionProperty(Maho_FeedManager_Model_Transformer::class, '_instances')->getValue();
        $this->mapper = new ReflectionClass(Maho_FeedManager_Model_Mapper::class)->newInstanceWithoutConstructor();
        new ReflectionProperty($this->mapper, '_priceCurrency')->setValue($this->mapper, 'USD');
        $this->product = new ReflectionClass(Mage_Catalog_Model_Product::class)->newInstanceWithoutConstructor();
    }

    #[\Override]
    protected function tearDown(): void
    {
        new ReflectionProperty(Maho_FeedManager_Model_Transformer::class, '_instances')->setValue(null, $this->transformerState);
    }

    public static function currentPrices(): array
    {
        return [
            'expired special' => [100, 100, 50, '2020-01-01', '2020-01-31', '100.00 USD', ''],
            'current special' => [100, 50, 50, '2020-01-01', '2099-01-31', '100.00 USD', '50.00 USD'],
            'future special' => [100, 100, 50, '2099-01-01', '2099-01-31', '100.00 USD', ''],
            'native discount below special' => [100, 40, 50, '2020-01-01', '2099-01-31', '100.00 USD', '40.00 USD'],
            'native discount without special' => [100, 70, null, null, null, '100.00 USD', '70.00 USD'],
            'no end date' => [100, 50, 50, '2020-01-01', null, '100.00 USD', '50.00 USD'],
            'no start date' => [100, 50, 50, null, '2099-01-31', '100.00 USD', '50.00 USD'],
            'no date bounds' => [100, 50, 50, null, null, '100.00 USD', '50.00 USD'],
            'same emitted cents' => [10.004, 10.001, 10.001, null, null, '10.00 USD', ''],
            'missing regular price' => [null, 50, null, null, null, '50.00 USD', ''],
        ];
    }

    #[DataProvider('currentPrices')]
    public function testDefaultsExportTheCurrentNativePrice(
        ?float $regular,
        float $final,
        ?float $special,
        ?string $from,
        ?string $to,
        string $price,
        string $sale,
    ): void {
        $raw = ['regular_price' => $regular, 'final_price' => $final, 'price' => $final,
            'special_price' => $special, 'special_from_date' => $from, 'special_to_date' => $to,
            '_feed' => ['price_currency' => 'USD', 'price_decimals' => 3, 'price_decimal_point' => ',', 'price_thousands_sep' => ' ']];
        self::assertSame(['price' => $price, 'sale_price' => $sale, 'sale_price_effective_date' => ''], $this->resolveDefaults($raw));
    }

    public static function parentModes(): array
    {
        return [
            'child' => ['', 80, 60, '80.00 USD', '60.00 USD'],
            'parent' => ['always', 80, 60, '100.00 USD', '50.00 USD'],
            'fallback' => ['if_empty', null, null, '100.00 USD', '50.00 USD'],
        ];
    }

    #[DataProvider('parentModes')]
    public function testThePairedSourceUsesTheSelectedParentMode(string $mode, ?float $regular, ?float $final, string $price, string $sale): void
    {
        $raw = ['regular_price' => $regular, 'final_price' => $final, 'price' => $final, 'special_price' => $final,
            'parent_regular_price' => 100, 'parent_final_price' => 50, 'parent_price' => 50, 'parent_special_price' => 50,
            '_feed' => ['price_currency' => 'USD']];
        $result = $this->resolveDefaults($raw, $mode);
        self::assertSame($price, $result['price']);
        self::assertSame($sale, $result['sale_price']);
    }

    public static function payloads(): array
    {
        return [
            'finite scientific notation' => ['["1e2","5e1"]', 'price', '100.00 USD'],
            'scientific sale' => ['["1e2","5e1"]', 'sale_price', '50.00 USD'],
            'missing regular' => ['["","50"]', 'price', '50.00 USD'],
            'missing final' => ['["100",""]', 'price', ''],
            'nonfinite final' => ['["100","INF"]', 'price', ''],
            'overflow final' => ['["100","1e999"]', 'price', ''],
            'zero final' => ['["100","0"]', 'price', ''],
            'negative final' => ['["100","-1"]', 'price', ''],
            'nonfinite regular' => ['["NaN","50"]', 'price', '50.00 USD'],
            'malformed pair' => ['not-json', 'price', ''],
            'wrong shape' => ['["100","50","25"]', 'price', ''],
            'invalid selector' => ['["100","50"]', 'other', ''],
        ];
    }

    #[DataProvider('payloads')]
    public function testTheRegisteredSelectorDoesNotInventAnAmount(string $payload, string $part, string $expected): void
    {
        self::assertSame($expected, Maho_FeedManager_Model_Transformer::apply(
            $payload,
            'google_price',
            ['part' => $part],
            ['regular_price' => 999, 'final_price' => 1, '_feed' => ['price_currency' => 'usd']],
        ));
    }

    public function testExistingCustomSourcesAndTransformersKeepTheirValues(): void
    {
        $raw = ['price' => 37, 'regular_price' => 100, 'final_price' => 50, 'special_price' => 80];
        self::assertSame('37.00 USD', $this->mapper->resolveFieldValue(['source_type' => 'attribute', 'source_value' => 'price'], $raw, $this->product));
        self::assertSame('80.00 USD', $this->mapper->resolveFieldValue(['source_type' => 'attribute', 'source_value' => 'special_price'], $raw, $this->product));
        self::assertSame('37,000', $this->mapper->resolveFieldValue(['source_type' => 'attribute', 'source_value' => 'price',
            'transformers' => [['transformer' => 'format_price', 'options' => ['decimals' => '3', 'decimal_separator' => ',']]]], $raw, $this->product));
        self::assertSame(['price' => 37, 'regular_price' => 100, 'final_price' => 50, 'special_price' => 80], $raw);
    }

    public function testPresetsCarryTheSamePriceChainsAndKeepEmptyDates(): void
    {
        $adapter = new Maho_FeedManager_Model_Platform_Google();
        $defaults = $adapter->getDefaultMappings();
        $xml = array_column($adapter->getDefaultXmlStructure(), null, 'tag');
        foreach (['price', 'sale_price'] as $field) {
            $chain = Maho_FeedManager_Model_Transformer::parseChainString($defaults[$field]['transformers'] ?? '');
            self::assertSame('google_price', $chain[0]['transformer'] ?? null);
            self::assertSame(['part' => $field], $chain[0]['options']);
            self::assertSame($defaults[$field]['transformers'], $xml['g:' . $field]['transformers']);
            self::assertSame('combined', $xml['g:' . $field]['source_type']);
        }
        self::assertSame('', $defaults['sale_price_effective_date']['source_value']);
        self::assertEmpty($defaults['sale_price_effective_date']['transformers'] ?? []);
    }

    private function resolveDefaults(array $raw, string $parentMode = ''): array
    {
        $defaults = new Maho_FeedManager_Model_Platform_Google()->getDefaultMappings();
        $result = [];
        foreach (['price', 'sale_price', 'sale_price_effective_date'] as $field) {
            $result[$field] = $this->mapper->resolveFieldValue($defaults[$field] + ['use_parent' => $parentMode], $raw, $this->product);
        }
        return $result;
    }
}
