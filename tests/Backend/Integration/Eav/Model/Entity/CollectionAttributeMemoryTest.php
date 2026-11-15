<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

beforeEach(function () {
    $this->connection = Mage::getSingleton('core/resource')->getConnection('core_write');
    $this->connection->beginTransaction();
    $setup = new Mage_Catalog_Model_Resource_Setup('core_setup');
    $eav = Mage::getSingleton('eav/config');
    $type = $eav->getEntityType('catalog_product');
    $this->attributeCodes = [];
    $this->attributeIds = [];
    $suffix = bin2hex(random_bytes(4));
    for ($i = 0; $i < 20; $i++) {
        $code = "collection_memory_{$suffix}_{$i}";
        $setup->addAttribute('catalog_product', $code, [
            'type' => $i < 10 ? 'varchar' : 'text', 'input' => 'text',
            'label' => $code, 'required' => false, 'user_defined' => true, 'global' => 0,
        ]);
        $this->attributeCodes[] = $code;
        $this->attributeIds[] = (int) $setup->getAttributeId('catalog_product', $code);
    }
    $eav->clear();
    $first = (int) $this->connection->fetchOne('SELECT MAX(entity_id) FROM ' . $setup->getTable('catalog/product')) + 1;
    $this->productIds = range($first, $first + 999);
    foreach (array_chunk($this->productIds, 100) as $ids) {
        $entities = $varchar = $text = [];
        foreach ($ids as $id) {
            $entities[] = ['entity_id' => $id, 'entity_type_id' => $type->getId(), 'attribute_set_id' => $type->getDefaultAttributeSetId(), 'sku' => "collection-memory-{$suffix}-{$id}", 'type_id' => 'simple'];
            foreach ($this->attributeIds as $index => $attributeId) {
                $row = ['entity_type_id' => $type->getId(), 'entity_id' => $id, 'attribute_id' => $attributeId, 'store_id' => 0, 'value' => "value-{$id}-{$index}"];
                if ($index < 10) {
                    $varchar[] = $row;
                } else {
                    $text[] = $row;
                }
            }
        }
        $this->connection->insertMultiple($setup->getTable('catalog/product'), $entities);
        $this->connection->insertMultiple($setup->getTable('catalog_product_entity_varchar'), $varchar);
        $this->connection->insertMultiple($setup->getTable('catalog_product_entity_text'), $text);
    }
});

afterEach(function () {
    $this->connection->rollBack();
    Mage::getSingleton('eav/config')->clear();
});

it('releases consumed attribute rows before fetching the next backend table', function () {
    $connection = Mage::getSingleton('core/resource')->getConnection('core_read');
    $adapter = $this->createMock(Maho\Db\Adapter\AdapterInterface::class);
    foreach (new ReflectionClass(Maho\Db\Adapter\AdapterInterface::class)->getMethods() as $method) {
        if ($method->getName() !== 'fetchAll') {
            $adapter->method($method->getName())->willReturnCallback($connection->{$method->getName()}(...));
        }
    }
    $sentinels = [];
    $releasedBeforeFetch = [];
    $adapter->method('fetchAll')->willReturnCallback(function (...$arguments) use ($connection, &$sentinels, &$releasedBeforeFetch): array {
        $previousReleased = $sentinels === [] || end($sentinels)->get() === null;
        $rows = $connection->fetchAll(...$arguments);
        if (count($rows) > 1 && isset($rows[0]['attribute_id'])) {
            if ($sentinels !== []) {
                $releasedBeforeFetch[] = $previousReleased;
            }
            // The last row can remain in the foreach variable after its result array is released.
            // An ignored field in the first row tracks the result array without changing values.
            $sentinel = new stdClass();
            $rows[0]['_lifetime_sentinel'] = $sentinel;
            $sentinels[] = WeakReference::create($sentinel);
        }
        return $rows;
    });
    $collection = new class extends Mage_Catalog_Model_Resource_Product_Collection {
        public ?Maho\Db\Adapter\AdapterInterface $attributeAdapter = null;

        #[\Override]
        public function getConnection(): Maho\Db\Adapter\AdapterInterface
        {
            return $this->attributeAdapter ?? parent::getConnection();
        }
    };
    $collection->attributeAdapter = $adapter;
    $collection->setStoreId(0)->addIdFilter($this->productIds)->addAttributeToSelect($this->attributeCodes);
    $collection->load();

    expect($sentinels)->toHaveCount(2)
        ->and($releasedBeforeFetch)->toBe([true])
        ->and(array_map(static fn(WeakReference $sentinel): ?object => $sentinel->get(), $sentinels))->toBe([null, null]);
    expect($collection->count())->toBe(1000);
    foreach ($collection as $product) {
        $expected = [];
        foreach ($this->attributeCodes as $index => $code) {
            $expected[$code] = "value-{$product->getId()}-{$index}";
        }
        expect(array_intersect_key($product->getData(), $expected))->toEqual($expected);
    }
});

it('preserves store overrides and callbacks across backend tables', function () {
    $resource = Mage::getSingleton('core/resource');
    $values = ['Store override', '', '0', null, 'absent'];
    foreach (['varchar' => 0, 'text' => 10] as $backend => $index) {
        foreach ($values as $i => $value) {
            if ($value === 'absent') {
                continue;
            }
            $this->connection->insert($resource->getTableName('catalog_product_entity_' . $backend), [
                'entity_type_id' => Mage::getSingleton('eav/config')->getEntityType('catalog_product')->getId(),
                'entity_id' => $this->productIds[$i], 'attribute_id' => $this->attributeIds[$index],
                'store_id' => 1, 'value' => $value,
            ]);
        }
    }
    $collection = new class extends Mage_Catalog_Model_Resource_Product_Collection {
        public array $seen = [];

        #[\Override]
        protected function _setItemAttributeValue($valueInfo)
        {
            $this->seen[] = $valueInfo;
            return parent::_setItemAttributeValue($valueInfo);
        }
    };
    $collection->setStoreId(1)->addIdFilter(array_slice($this->productIds, 0, 5))
        ->addAttributeToSelect([$this->attributeCodes[0], $this->attributeCodes[10]])->load();

    expect($collection->seen)->toHaveCount(10);
    foreach ($values as $i => $value) {
        $product = $collection->getItemById($this->productIds[$i]);
        foreach ([0, 10] as $index) {
            $code = $this->attributeCodes[$index];
            expect($product->getData($code))->toBe($value === 'absent' ? "value-{$product->getId()}-{$index}" : $value);
            expect($product->getExistsStoreValueFlag($code))->toBe($value !== null && $value !== 'absent');
        }
    }
});
