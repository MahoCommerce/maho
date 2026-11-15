<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

function listSwatchAttributeId(string $code): int
{
    return (int) Mage::getSingleton('eav/config')->getAttribute(Mage_Catalog_Model_Product::ENTITY, $code)->getId();
}

/**
 * @return list<int>
 */
function listSwatchParentIds(string $code): array
{
    $adapter = Mage::getSingleton('core/resource')->getConnection('core_read');
    return array_map(intval(...), $adapter->fetchCol(
        $adapter->select()
            ->from(Mage::getSingleton('core/resource')->getTableName('catalog/product_super_attribute'), ['product_id'])
            ->where('attribute_id = ?', listSwatchAttributeId($code))
            ->order('product_id'),
    ));
}

/**
 * Load the products with their children, then attach the swatch values of the listing.
 *
 * @param list<int> $ids
 * @return array<int, Mage_Catalog_Model_Product>
 */
function listSwatchProducts(array $ids): array
{
    $storeId = (int) Mage::app()->getStore()->getId();
    $products = [];
    foreach ($ids as $id) {
        $products[$id] = Mage::getModel('catalog/product')->setStoreId($storeId)->load($id);
    }
    $helper = Mage::helper('configurableswatches/mediafallback');
    $helper->attachChildrenProducts($products, $storeId);
    $helper->attachProductChildrenAttributeMapping($products, $storeId, true);
    return $products;
}

/**
 * @return list<int>
 */
function listSwatchOptionIds(string $code, int $count): array
{
    $options = Mage::getSingleton('eav/config')->getAttribute(Mage_Catalog_Model_Product::ENTITY, $code)
        ->getSource()->getAllOptions(false);
    return array_map(fn(array $option): int => (int) $option['value'], array_slice($options, 0, $count));
}

function listSwatchChild(int $id, array $data): Mage_Catalog_Model_Product
{
    return Mage::getModel('catalog/product')->setId($id)->addData($data)
        ->setStockItem(Mage::getModel('cataloginventory/stock_item')->setIsInStock());
}

beforeEach(function (): void {
    Mage::app()->setCurrentStore((int) Mage::app()->getDefaultStoreView()->getId());
    Mage::app()->getStore()->setConfig(Mage_ConfigurableSwatches_Helper_Data::CONFIG_PATH_ENABLED, '1');
});

describe('the listing swatches with more than one attribute', function (): void {
    it('shows the swatches of each product with the attribute that the product uses', function (): void {
        Mage::app()->getStore()->setConfig(
            Mage_ConfigurableSwatches_Helper_Data::CONFIG_PATH_LIST_SWATCH_ATTRIBUTE,
            listSwatchAttributeId('color') . ',' . listSwatchAttributeId('volume'),
        );
        $colorId = listSwatchParentIds('color')[0];
        $volumeId = listSwatchParentIds('volume')[0];

        $products = listSwatchProducts([$colorId, $volumeId]);

        expect($products[$colorId]->getListSwatchAttrId())->toBe(listSwatchAttributeId('color'))
            ->and(count($products[$colorId]->getListSwatchAttrValues()))->toBeGreaterThan(1)
            ->and($products[$volumeId]->getListSwatchAttrId())->toBe(listSwatchAttributeId('volume'))
            ->and(count($products[$volumeId]->getListSwatchAttrValues()))->toBeGreaterThan(1);
    });

    it('renders the swatches of the attribute that the product uses', function (): void {
        Mage::app()->getStore()->setConfig(
            Mage_ConfigurableSwatches_Helper_Data::CONFIG_PATH_LIST_SWATCH_ATTRIBUTE,
            listSwatchAttributeId('color') . ',' . listSwatchAttributeId('volume'),
        );
        $volumeId = listSwatchParentIds('volume')[0];
        $products = listSwatchProducts([$volumeId]);

        $html = Mage::app()->getLayout()->createBlock('core/template')
            ->setTemplate('configurableswatches/catalog/product/list/swatches.phtml')
            ->setProduct($products[$volumeId])
            ->toHtml();

        expect($html)->toContain('configurable-swatch-volume')
            ->and($html)->not->toContain('configurable-swatch-color');
    });

    it('shows the color picker for an attribute that is only in the listing setting', function (): void {
        $store = Mage::app()->getStore();
        $store->setConfig(Mage_ConfigurableSwatches_Helper_Data::CONFIG_PATH_SWATCH_ATTRIBUTES, '');
        $store->setConfig(Mage_ConfigurableSwatches_Helper_Data::CONFIG_PATH_LIST_SWATCH_ATTRIBUTE, (string) listSwatchAttributeId('volume'));
        Mage::register('entity_attribute', Mage::getSingleton('eav/config')->getAttribute(Mage_Catalog_Model_Product::ENTITY, 'volume'));
        try {
            expect(new Mage_Adminhtml_Block_Catalog_Product_Attribute_Edit_Tab_Options()->isConfigurableSwatchesEnabled())->toBeTrue();
        } finally {
            Mage::unregister('entity_attribute');
        }
    });

    it('keeps the first attribute for the code that reads one attribute', function (): void {
        Mage::app()->getStore()->setConfig(
            Mage_ConfigurableSwatches_Helper_Data::CONFIG_PATH_LIST_SWATCH_ATTRIBUTE,
            listSwatchAttributeId('volume') . ',' . listSwatchAttributeId('color'),
        );
        $helper = Mage::helper('configurableswatches/productlist');

        expect($helper->getSwatchAttributeId())->toBe(listSwatchAttributeId('volume'))
            ->and($helper->getSwatchAttribute()->getAttributeCode())->toBe('volume');
    });

    it('marks the swatch of an active filter on any listing attribute', function (): void {
        Mage::app()->getStore()->setConfig(
            Mage_ConfigurableSwatches_Helper_Data::CONFIG_PATH_LIST_SWATCH_ATTRIBUTE,
            listSwatchAttributeId('color') . ',' . listSwatchAttributeId('volume'),
        );
        [$filtered, $other] = listSwatchOptionIds('volume', 2);
        $filter = Mage::getModel('catalog/layer_filter_attribute')->setAttributeModel(
            Mage::getSingleton('eav/config')->getAttribute(Mage_Catalog_Model_Product::ENTITY, 'volume'),
        );
        Mage::getSingleton('catalog/layer')->getState()->addFilter(
            Mage::getModel('catalog/layer_filter_item')->setFilter($filter)->setValue((string) $filtered),
        );
        $helper = Mage::helper('configurableswatches/productlist');

        expect($helper->swatchMatchesFilter($filtered))->toBeTrue()
            ->and($helper->swatchMatchesFilter($other))->toBeFalse();
    });

    it('gives each product only the swatch prices of its own values', function (): void {
        Mage::app()->getStore()->setConfig(
            Mage_ConfigurableSwatches_Helper_Data::CONFIG_PATH_LIST_SWATCH_ATTRIBUTE,
            (string) listSwatchAttributeId('color'),
        );
        $products = listSwatchProducts(array_slice(listSwatchParentIds('color'), 0, 20));
        $pair = null;
        foreach ($products as $first) {
            foreach ($products as $second) {
                if ($second->getId() > $first->getId()
                    && array_diff($first->getListSwatchAttrValues(), $second->getListSwatchAttrValues())
                ) {
                    $pair = [$first, $second];
                    break 2;
                }
            }
        }
        expect($pair)->not->toBeNull();

        Mage::helper('configurableswatches/list_price')->attachConfigurableProductChildrenPricesMapping($pair);

        expect($pair[1]->getSwatchPrices())->not->toBeEmpty()
            ->and(array_diff(array_keys($pair[1]->getSwatchPrices()), $pair[1]->getListSwatchAttrValues()))->toBe([]);
    });
});

describe('the listing attribute of a product that uses two of them', function (): void {
    beforeEach(function (): void {
        $this->adapter = Mage::getSingleton('core/resource')->getConnection('core_write');
        $this->table = Mage::getSingleton('core/resource')->getTableName('catalog/product_super_attribute');
        $this->parentId = listSwatchParentIds('color')[0];
        $this->colorPosition = (int) $this->adapter->fetchOne(
            $this->adapter->select()->from($this->table, ['position'])
                ->where('product_id = ?', $this->parentId)
                ->where('attribute_id = ?', listSwatchAttributeId('color')),
        );
        $this->adapter->insert($this->table, [
            'product_id' => $this->parentId,
            'attribute_id' => listSwatchAttributeId('size'),
            'position' => 0,
        ]);
        Mage::app()->getStore()->setConfig(
            Mage_ConfigurableSwatches_Helper_Data::CONFIG_PATH_LIST_SWATCH_ATTRIBUTE,
            listSwatchAttributeId('size') . ',' . listSwatchAttributeId('color'),
        );
    });

    afterEach(function (): void {
        $this->adapter->delete($this->table, [
            'product_id = ?' => $this->parentId,
            'attribute_id = ?' => listSwatchAttributeId('size'),
        ]);
        $this->adapter->update($this->table, ['position' => $this->colorPosition], [
            'product_id = ?' => $this->parentId,
            'attribute_id = ?' => listSwatchAttributeId('color'),
        ]);
    });

    /**
     * @param array{color: int, size: int} $positions
     * @param list<array{color: int, size: int}> $children option index of each child
     */
    $run = function (array $positions, array $children): Mage_Catalog_Model_Product {
        foreach ($positions as $code => $position) {
            $this->adapter->update($this->table, ['position' => $position], [
                'product_id = ?' => $this->parentId,
                'attribute_id = ?' => listSwatchAttributeId($code),
            ]);
        }
        $colors = listSwatchOptionIds('color', 2);
        $sizes = listSwatchOptionIds('size', 2);
        $childProducts = [];
        foreach ($children as $index => $child) {
            $childProducts[] = listSwatchChild(900000 + $index, [
                'color' => $colors[$child['color']],
                'size' => $sizes[$child['size']],
            ]);
        }
        $parent = Mage::getModel('catalog/product')->setId($this->parentId)->setChildrenProducts($childProducts);
        Mage::helper('configurableswatches/mediafallback')->attachProductChildrenAttributeMapping(
            [$this->parentId => $parent],
            (int) Mage::app()->getStore()->getId(),
            true,
        );
        return $parent;
    };

    it('takes the attribute that comes first on the Associated Products tab', function (array $positions, string $expected) use ($run): void {
        $parent = $run->call($this, $positions, [
            ['color' => 0, 'size' => 0],
            ['color' => 1, 'size' => 1],
        ]);

        expect($parent->getListSwatchAttrId())->toBe(listSwatchAttributeId($expected))
            ->and($parent->getListSwatchAttrValues())->toHaveCount(2)
            ->and($parent->getListSwatchAttrStockValues())->toBe(array_combine(listSwatchOptionIds($expected, 2), [true, true]));
    })->with([
        'color first' => [['color' => 0, 'size' => 1], 'color'],
        'size first' => [['color' => 1, 'size' => 0], 'size'],
    ]);

    it('skips an attribute that has only one value on the product', function () use ($run): void {
        $parent = $run->call($this, ['color' => 0, 'size' => 1], [
            ['color' => 0, 'size' => 0],
            ['color' => 0, 'size' => 1],
        ]);

        expect($parent->getListSwatchAttrId())->toBe(listSwatchAttributeId('size'));
    });

    it('takes the first attribute with one value when no attribute has two', function () use ($run): void {
        $parent = $run->call($this, ['color' => 1, 'size' => 0], [
            ['color' => 0, 'size' => 0],
            ['color' => 0, 'size' => 0],
        ]);

        expect($parent->getListSwatchAttrId())->toBe(listSwatchAttributeId('size'))
            ->and($parent->getListSwatchAttrValues())->toHaveCount(1);
    });

    it('takes the attribute that the product got first when two attributes have the same position', function () use ($run): void {
        $parent = $run->call($this, ['color' => 0, 'size' => 0], [
            ['color' => 0, 'size' => 0],
            ['color' => 1, 'size' => 1],
        ]);

        expect($parent->getListSwatchAttrId())->toBe(listSwatchAttributeId('color'));
    });

    it('gives no listing attribute to a product that uses none of them', function () use ($run): void {
        Mage::app()->getStore()->setConfig(
            Mage_ConfigurableSwatches_Helper_Data::CONFIG_PATH_LIST_SWATCH_ATTRIBUTE,
            (string) listSwatchAttributeId('volume'),
        );
        $wasDeveloperMode = Mage::getIsDeveloperMode();
        $errorLevel = error_reporting(E_ALL);
        Mage::setIsDeveloperMode(true);
        try {
            $parent = $run->call($this, ['color' => 0, 'size' => 1], [
                ['color' => 0, 'size' => 0],
                ['color' => 1, 'size' => 1],
            ]);
        } finally {
            Mage::setIsDeveloperMode($wasDeveloperMode);
            error_reporting($errorLevel);
        }

        expect($parent->getListSwatchAttrId())->toBeNull()
            ->and($parent->getListSwatchAttrValues())->toBe([]);
    });

    it('lists the child products of a label shared by two attributes as a JSON array', function (): void {
        $resource = Mage::getSingleton('core/resource');
        $optionTable = $resource->getTableName('eav/attribute_option');
        $valueTable = $resource->getTableName('eav/attribute_option_value');
        $sharedColorId = listSwatchOptionIds('color', 1)[0];
        $this->adapter->insert($optionTable, ['attribute_id' => listSwatchAttributeId('size'), 'sort_order' => 99]);
        $sizeId = (int) $this->adapter->lastInsertId($optionTable);
        try {
            foreach ($this->adapter->fetchAll(
                $this->adapter->select()->from($valueTable, ['store_id', 'value'])->where('option_id = ?', $sharedColorId),
            ) as $row) {
                $this->adapter->insert($valueTable, ['option_id' => $sizeId, 'store_id' => $row['store_id'], 'value' => $row['value']]);
            }
            foreach (['color' => 0, 'size' => 1] as $code => $position) {
                $this->adapter->update($this->table, ['position' => $position], [
                    'product_id = ?' => $this->parentId,
                    'attribute_id = ?' => listSwatchAttributeId($code),
                ]);
            }
            $otherColorId = listSwatchOptionIds('color', 2)[1];
            $parent = Mage::getModel('catalog/product')->setId($this->parentId)->setChildrenProducts([
                listSwatchChild(900000, ['color' => $sharedColorId, 'size' => $sizeId]),
                listSwatchChild(900001, ['color' => $otherColorId, 'size' => $sizeId]),
            ]);
            Mage::helper('configurableswatches/mediafallback')->attachProductChildrenAttributeMapping(
                [$this->parentId => $parent],
                (int) Mage::app()->getStore()->getId(),
                true,
            );
            $mapping = $parent->getChildAttributeLabelMapping();
        } finally {
            $this->adapter->delete($valueTable, ['option_id = ?' => $sizeId]);
            $this->adapter->delete($optionTable, ['option_id = ?' => $sizeId]);
        }

        expect($mapping)->toHaveCount(2)
            ->and(array_map(fn(array $entry): bool => array_is_list($entry['product_ids']), $mapping))->not->toContain(false);
    });
});
