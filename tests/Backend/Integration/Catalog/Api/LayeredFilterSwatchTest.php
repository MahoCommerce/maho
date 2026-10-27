<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use ApiPlatform\Metadata\GetCollection;
use Mage\Catalog\Api\LayeredFilterProvider;
use Maho\ApiPlatform\Service\StoreContext;

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

        // The sample data has an image for each color, so the tests see only their own black image
        $this->swatchDir = Mage::getBaseDir('media') . '/' . Mage_ConfigurableSwatches_Helper_Productimg::SWATCH_FALLBACK_MEDIA_DIR;
        $this->savedSwatchDir = $this->swatchDir . '_' . uniqid();
        if (is_dir($this->swatchDir)) {
            rename($this->swatchDir, $this->savedSwatchDir);
        }
        mkdir($this->swatchDir, 0777, true);
        Maho::encodeImage(Maho::getImageManager()->createImage(80, 40)->fill('111111'))
            ->save($this->swatchDir . '/black' . Mage_ConfigurableSwatches_Helper_Productimg::getSwatchFileExt());
        Mage::helper('configurableswatches/productimg')->clearSwatchesCache();
    });

    afterEach(function (): void {
        Mage::app()->getCache()->clean(['API_LAYERED_FILTERS']);
        Mage::helper('configurableswatches/productimg')->clearSwatchesCache();
        (new \Maho\Io\File())->rmdir($this->swatchDir, true);
        if (is_dir($this->savedSwatchDir)) {
            rename($this->savedSwatchDir, $this->swatchDir);
        }
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
            ->and($black['swatch']['value'])->toStartWith(Mage::getBaseUrl('media') . Mage_ConfigurableSwatches_Helper_Productimg::SWATCH_CACHE_DIR . '/')
            ->and($other['swatch']['value'])->toBe('#' . ltrim($color, '#'));
    });

    it('returns the color of an option after the storefront cached its swatch image', function (): void {
        $helper = Mage::helper('configurableswatches/productimg');
        $isColor = fn(array $option): bool => ($helper->getGlobalSwatch(null, $option['label'], 24, 24)['type'] ?? null) === 'color';
        $other = array_find(layeredColorOptions(fn(array $options): bool => array_find($options, $isColor) !== null), $isColor);
        $swatch = $helper->getGlobalSwatch(null, $other['label'], 24, 24);

        expect($helper->getGlobalSwatchUrl(null, $other['label'], 24, 24))->not->toBe('')
            ->and($helper->getGlobalSwatch(null, $other['label'], 24, 24))->toBe($swatch);
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

    it('returns no swatch when the Configurable Swatches module is disabled', function (): void {
        Mage::getConfig()->setNode('modules/Mage_ConfigurableSwatches/active', 'false');
        foreach (['configurableswatches', 'configurableswatches/productimg', 'configurableswatches/swatchdimensions'] as $alias) {
            Mage::unregister("_helper/{$alias}");
            Mage::register("_helper/{$alias}", false);
        }

        expect(array_column(layeredColorOptions(), 'swatch'))->each->toBeNull();
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
