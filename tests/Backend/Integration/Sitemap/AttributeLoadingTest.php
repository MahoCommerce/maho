<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

beforeEach(function (): void {
    $this->connection = Mage::getSingleton('core/resource')->getConnection('core_write');
    $this->entityTable = null;
    $this->entityIds = [];
    $this->fixturePrefix = 'sitemap-attributes-' . bin2hex(random_bytes(8));
    $this->entityMarkers = [];
    $this->markerColumn = null;
});

afterEach(function (): void {
    if ($this->entityIds !== []) {
        $this->connection->delete($this->entityTable . '_varchar', ['entity_id IN (?)' => $this->entityIds]);
    }
    if ($this->entityMarkers !== []) {
        $this->connection->delete($this->entityTable, [$this->markerColumn . ' IN (?)' => $this->entityMarkers]);
    }
});

it('preserves sitemap attribute fallback and ordered entity keys', function (string $type, bool $defaultStore): void {
    $connection = $this->connection;
    $storeId = (int) Mage::app()->getDefaultStoreView()->getId();
    $entityType = Mage::getSingleton('eav/config')->getEntityType('catalog_' . $type);
    $resource = Mage::getResourceModel('sitemap/catalog_' . $type);
    $table = $resource->getMainTable();
    $this->entityTable = $table;
    $this->markerColumn = $type === 'product' ? 'sku' : 'path';
    $attributes = [];
    foreach (['name', 'image'] as $code) {
        $attributes[$code] = (int) Mage::getSingleton('eav/config')->getAttribute('catalog_' . $type, $code)->getId();
    }

    $ids = [];
    $expected = [];
    foreach (['default', 'override', 'empty', 'null', 'store_only', 'missing', 'zero'] as $case) {
        $marker = $this->fixturePrefix . '-' . $case;
        $entity = [
            'entity_type_id' => $entityType->getId(),
            'attribute_set_id' => $entityType->getDefaultAttributeSetId(),
        ];
        if ($type === 'product') {
            $entity += ['sku' => $marker];
        } else {
            $entity += ['path' => $marker, 'position' => 0, 'children_count' => 0];
        }
        $this->entityMarkers[] = $marker;
        $connection->insert($table, $entity);
        $id = (int) $connection->lastInsertId($table);
        $this->entityIds[] = $id;
        $ids[] = $id;
        foreach ($attributes as $code => $attributeId) {
            $values = [];
            if (!in_array($case, ['store_only', 'missing'], true)) {
                $values[0] = 'Default ' . $code . ' & <title>';
            }
            if (!in_array($case, ['default', 'missing'], true)) {
                $values[$storeId] = match ($case) {
                    'empty' => '',
                    'null' => null,
                    'zero' => '0',
                    default => 'Store ' . $code . ' & <title>',
                };
            }
            foreach ($values as $scope => $value) {
                $connection->insert($table . '_varchar', [
                    'entity_type_id' => $entityType->getId(),
                    'attribute_id' => $attributeId,
                    'store_id' => $scope,
                    'entity_id' => $id,
                    'value' => $value,
                ]);
            }
            if ($defaultStore) {
                if (array_key_exists(0, $values)) {
                    $expected[$id][$code] = $values[0];
                }
            } elseif ($values) {
                $expected[$id][$code] = $values[$storeId] ?? $values[0];
            }
        }
    }

    $requestedIds = [...array_reverse($ids), $ids[0], 2147483647];
    expect($resource->loadAttributesForIds($requestedIds, $defaultStore ? 0 : $storeId))
        ->toBe($expected)
        ->and($resource->loadAttributesForIds([], $storeId))->toBe([]);
})->with([
    'product store fallback' => ['product', false],
    'product default store' => ['product', true],
    'category store fallback' => ['category', false],
    'category default store' => ['category', true],
]);

it('reads the attribute rows without fetchAll', function (string $class): void {
    $connection = Mage::getSingleton('core/resource')->getConnection('core_read');
    $adapter = $this->createMock(Maho\Db\Adapter\AdapterInterface::class);
    foreach (new ReflectionClass(Maho\Db\Adapter\AdapterInterface::class)->getMethods() as $method) {
        if ($method->getName() !== 'fetchAll') {
            $adapter->method($method->getName())->willReturnCallback($connection->{$method->getName()}(...));
        }
    }
    $adapter->method('fetchAll')->willThrowException(new LogicException('fetchAll'));
    $subclass = $class === 'product'
        ? new class ($adapter) extends Mage_Sitemap_Model_Resource_Catalog_Product {
            public function __construct(private Maho\Db\Adapter\AdapterInterface $adapter)
            {
                parent::__construct();
            }

            #[\Override]
            protected function _getReadAdapter()
            {
                return $this->adapter;
            }
        }
    : new class ($adapter) extends Mage_Sitemap_Model_Resource_Catalog_Category {
        public function __construct(private Maho\Db\Adapter\AdapterInterface $adapter)
        {
            parent::__construct();
        }

        #[\Override]
        protected function _getReadAdapter()
        {
            return $this->adapter;
        }
    };

    expect($subclass->loadAttributesForIds([2147483647], 0))->toBe([]);
})->with(['product', 'category']);
