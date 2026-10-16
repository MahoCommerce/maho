<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoFrontendTestCase::class);

/** Makes every attribute a swatch attribute. */
final class LayerStateSwatchTest_DataHelper extends Mage_ConfigurableSwatches_Helper_Data
{
    #[\Override]
    public function isEnabled()
    {
        return true;
    }

    #[\Override]
    public function attrIsSwatchType($attr)
    {
        return true;
    }
}

/** Gives a swatch image to the "Yellow" option only. */
final class LayerStateSwatchTest_ProductimgHelper extends Mage_ConfigurableSwatches_Helper_Productimg
{
    #[\Override]
    public function getGlobalSwatchUrl($object, $value, $width = self::SWATCH_DEFAULT_WIDTH, $height = self::SWATCH_DEFAULT_HEIGHT, $fileExt = null)
    {
        return $value === 'Yellow' ? 'https://example.com/media/yellow.webp' : '';
    }
}

beforeEach(function (): void {
    Mage::unregister('_helper/configurableswatches');
    Mage::unregister('_helper/configurableswatches/productimg');
    Mage::register('_helper/configurableswatches', new LayerStateSwatchTest_DataHelper());
    Mage::register('_helper/configurableswatches/productimg', new LayerStateSwatchTest_ProductimgHelper());
});

afterEach(function (): void {
    Mage::unregister('_helper/configurableswatches');
    Mage::unregister('_helper/configurableswatches/productimg');
});

it('renders the swatch of each active filter, not the swatch of the filter before it', function (): void {
    $item = function (string $attributeCode, string $label): Mage_Catalog_Model_Layer_Filter_Item {
        $filter = Mage::getModel('catalog/layer_filter_attribute')
            ->setAttributeModel(Mage::getModel('catalog/resource_eav_attribute')->setAttributeCode($attributeCode));
        return Mage::getModel('catalog/layer_filter_item')->setFilter($filter)->setLabel($label);
    };

    /** @var Mage_ConfigurableSwatches_Block_Catalog_Layer_State_Swatch $renderer */
    $renderer = Mage::app()->getLayout()->createBlock('configurableswatches/catalog_layer_state_swatch');

    expect($renderer->shouldRender($item('color', 'Yellow')))->toBeTrue()
        ->and($renderer->getSwatchUrl())->toBe('https://example.com/media/yellow.webp')
        ->and($renderer->shouldRender($item('size', '40')))->toBeFalse();
});
