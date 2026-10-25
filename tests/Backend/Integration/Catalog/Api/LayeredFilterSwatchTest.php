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
 *
 * @return array<string, array{value: string, label: string, count: int, swatch: array{type: string, value: string}|null}>
 */
function layeredColorOptions(): array
{
    $provider = new LayeredFilterProvider();
    $categories = Mage::getResourceModel('catalog/category_collection')
        ->addAttributeToFilter('level', ['gteq' => 2])
        ->addIsActiveFilter();

    foreach ($categories as $category) {
        $filters = $provider->provide(new GetCollection(), [], ['filters' => ['categoryId' => $category->getId()]]);
        foreach ($filters as $filter) {
            $options = array_column($filter->options, null, 'label');
            if ($filter->code === 'color' && isset($options['Black']) && count($options) > 1) {
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
        $options = layeredColorOptions();
        $black = $options['Black'];
        unset($options['Black']);

        $resource = Mage::getSingleton('core/resource');
        $colors = $resource->getConnection('core_read')->fetchPairs(
            $resource->getConnection('core_read')->select()
                ->from($resource->getTableName('eav/attribute_option_swatch'), ['option_id', 'value'])
                ->where('option_id IN (?)', array_map(intval(...), array_column($options, 'value'))),
        );
        $other = array_find($options, fn(array $option): bool => !empty($colors[$option['value']]));
        $color = $colors[$other['value'] ?? ''] ?? null;

        expect($color)->not->toBeEmpty()
            ->and($black['swatch']['type'] ?? null)->toBe('image')
            ->and($black['swatch']['value'])->toStartWith($this->mount->publicUrl('wysiwyg/swatches/'))
            ->and($other['swatch'])->toBe(['type' => 'color', 'value' => $color]);
    });
});
