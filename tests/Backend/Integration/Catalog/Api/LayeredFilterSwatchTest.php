<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use ApiPlatform\Metadata\GetCollection;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Mage\Catalog\Api\LayeredFilterProvider;
use Maho\ApiPlatform\Service\StoreContext;
use Maho\Storage\Mount;
use Maho\Storage\MountRegistry;
use Maho\Storage\Url\StoreBaseUrlGenerator;

uses(Tests\MahoBackendTestCase::class);

/**
 * The options of the color filter of the first category that lists Black and one more color, by label.
 * When $accept is set, the options must also pass it.
 *
 * @param (Closure(array<string, array<string, mixed>>): bool)|null $accept
 * @return array<string, array{value: string, label: string, count: int, swatch: array{type: string, value: string}|null}>
 */
function layeredColorOptions(?Closure $accept = null): array
{
    $provider = new LayeredFilterProvider();
    $categories = Mage::getResourceModel('catalog/category_collection')
        ->addAttributeToFilter('level', ['gteq' => 2])
        ->addIsActiveFilter();

    foreach ($categories as $category) {
        $filters = $provider->provide(new GetCollection(), [], ['filters' => ['categoryId' => $category->getId()]]);
        foreach ($filters as $filter) {
            $options = array_column($filter->options, null, 'label');
            if ($filter->code === 'color' && isset($options['Black']) && count($options) > 1 && ($accept === null || $accept($options))) {
                return $options;
            }
        }
    }

    test()->fail('No category has a color filter that lists Black and one more color');
}

describe('the swatches of the layered filters API', function (): void {
    beforeEach(function (): void {
        StoreContext::ensureStore(1);
        Mage::app()->getCache()->clean(['API_LAYERED_FILTERS']);

        $this->root = sys_get_temp_dir() . '/maho_layered_swatch_' . uniqid();
        mkdir($this->root, 0777, true);
        $this->mount = new Mount('media', new LocalFilesystemAdapter($this->root), $this->root, new StoreBaseUrlGenerator('media'));
        MountRegistry::register($this->mount);
        Mage::getResourceSingleton('core/image_size')->deleteFolder('media', 'wysiwyg/swatches');
        Mage::unregister('_singleton/core/image_size');

        $png = Maho::getImageManager()->createImage(80, 40)->fill('111111')
            ->encodeUsingFormat(\Intervention\Image\Format::PNG)->toString();
        $this->mount->write('wysiwyg/swatches/black' . Mage_ConfigurableSwatches_Helper_Productimg::getSwatchFileExt(), $png);
    });

    afterEach(function (): void {
        Mage::app()->getCache()->clean(['API_LAYERED_FILTERS']);
        Mage::getResourceSingleton('core/image_size')->deleteFolder('media', 'wysiwyg/swatches');
        Mage::unregister('_singleton/core/image_size');
        MountRegistry::reset();
        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($this->root);
    });

    it('returns the image in wysiwyg/swatches, and otherwise the color of the option', function (): void {
        $isColor = fn(array $option): bool => ($option['swatch']['type'] ?? null) === 'color';
        $options = layeredColorOptions(fn(array $options): bool => array_find($options, $isColor) !== null);
        $black = $options['Black'];
        $other = array_find($options, $isColor);

        $resource = Mage::getSingleton('core/resource');
        $adapter = $resource->getConnection('core_read');
        $color = (string) $adapter->fetchOne(
            $adapter->select()
                ->from($resource->getTableName('eav/attribute_option_swatch'), ['value'])
                ->where('option_id = ?', (int) $other['value']),
        );

        expect($color)->not->toBeEmpty()
            ->and($black['swatch']['type'] ?? null)->toBe('image')
            ->and($black['swatch']['value'])->toStartWith($this->mount->publicUrl('wysiwyg/swatches/'))
            ->and($other['swatch']['value'])->toBe('#' . ltrim($color, '#'));
    });

    it('returns no swatch when Configurable Swatches is disabled', function (): void {
        $store = Mage::app()->getStore(1);
        $enabled = $store->getConfig(Mage_ConfigurableSwatches_Helper_Data::CONFIG_PATH_ENABLED);
        $store->setConfig(Mage_ConfigurableSwatches_Helper_Data::CONFIG_PATH_ENABLED, '0');
        Mage::unregister('_helper/configurableswatches');

        try {
            expect(array_column(layeredColorOptions(), 'swatch'))->each->toBeNull();
        } finally {
            $store->setConfig(Mage_ConfigurableSwatches_Helper_Data::CONFIG_PATH_ENABLED, $enabled);
            Mage::unregister('_helper/configurableswatches');
        }
    });

    it('uses the admin label of the option when its store label has no swatch', function (): void {
        $attribute = Mage::getSingleton('eav/config')->getAttribute(Mage_Catalog_Model_Product::ENTITY, 'color');
        $optionId = layeredColorOptions()['Black']['value'];
        $adminLabel = array_column($attribute->getSource()->getAllOptions(false, true), 'label', 'value')[$optionId];
        $item = Mage::getModel('catalog/layer_filter_item')
            ->setFilter(Mage::getModel('catalog/layer_filter_attribute')->setAttributeModel($attribute))
            ->setValue($optionId);
        $helper = Mage::helper('configurableswatches/productimg');

        expect($helper->getGlobalSwatch($item, 'No Such Label', 24, 24))
            ->not->toBeNull()
            ->toBe($helper->getGlobalSwatch(null, (string) $adminLabel, 24, 24));
    });
});
