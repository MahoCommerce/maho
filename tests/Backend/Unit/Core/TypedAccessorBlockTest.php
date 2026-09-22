<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * Wave 7 converted the blocks. A block also takes data from layout XML and from
 * a {{block}} template directive, and both of those hand over strings, so the
 * typed setters must keep working through them.
 */

dataset('block round trips', [
    'grid column flag' => ['adminhtml/widget_grid_column', 'copyable', '1', true],
    'grid column string' => ['adminhtml/widget_grid_column', 'index', 'entity_id', 'entity_id'],
    'product list int' => ['catalog/product_list', 'category_id', '5', 5],
    'product list string' => ['catalog/product_list', 'sort_by', 'name', 'name'],
    'swatch int' => ['configurableswatches/catalog_layer_state_swatch', 'swatch_inner_width', '30', 30],
    'head flag' => ['page/html_head', 'can_load_wysiwyg', '1', true],
    'cart int' => ['checkout/cart', 'items_count', '4', 4],
    'tag id' => ['tag/customer_view', 'tag_id', '9', 9],
]);

it('returns typed values from string input', function (string $type, string $key, string $in, int|bool|string $out) {
    $block = Mage::app()->getLayout()->createBlock($type);
    $block->setData($key, $in);

    $getter = 'get' . str_replace('_', '', ucwords($key, '_'));

    expect($block->$getter())->toBe($out);
})->with('block round trips');

it('returns null for a key the block does not hold', function () {
    $layout = Mage::app()->getLayout();

    expect($layout->createBlock('catalog/product_list')->getCategoryId())->toBeNull()
        ->and($layout->createBlock('adminhtml/widget_grid_column')->getCopyable())->toBeNull()
        ->and($layout->createBlock('core/text_tag')->getTagName())->toBeNull();
});

it('keeps a cms block identifier usable as a string and as an id', function () {
    $layout = Mage::app()->getLayout();

    expect($layout->createBlock('cms/block')->setBlockId('footer_links')->getBlockId())->toBe('footer_links')
        ->and($layout->createBlock('cms/block')->setBlockId(7)->getBlockId())->toBe(7);
});

it('writes the template on the property and the class on the data', function () {
    $template = Mage::app()->getLayout()->createBlock('core/template')->setTemplate('page/html.phtml');
    $text = Mage::app()->getLayout()->createBlock('core/text')->setTemplate('unused.phtml');

    expect($template->getTemplate())->toBe('page/html.phtml')
        ->and($text->getData('template'))->toBe('unused.phtml');
});

it('decodes a layout action flag through the json attribute', function () {
    $layout = Mage::getModel('core/layout');
    $layout->loadString(
        '<layout><block type="adminhtml/widget_grid" name="typed.grid">'
        . '<action method="setUseAjax" json="flag"><flag>true</flag></action>'
        . '</block></layout>',
    );
    $layout->generateBlocks();

    expect($layout->getBlock('typed.grid')->getUseAjax())->toBeTrue();
});

it('passes a layout action string straight into a typed string setter', function () {
    $layout = Mage::getModel('core/layout');
    $layout->loadString(
        '<layout><block type="cms/block" name="typed.cms">'
        . '<action method="setBlockId"><block_id>footer_links</block_id></action>'
        . '</block></layout>',
    );
    $layout->generateBlocks();

    expect($layout->getBlock('typed.cms')->getBlockId())->toBe('footer_links');
});

it('defaults a block flag setter to true and still takes false', function () {
    $head = Mage::app()->getLayout()->createBlock('page/html_head');

    expect($head->setCanLoadWysiwyg()->getCanLoadWysiwyg())->toBeTrue()
        ->and($head->setCanLoadWysiwyg(false)->getCanLoadWysiwyg())->toBeFalse();
});
