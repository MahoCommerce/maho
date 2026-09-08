<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

uses(Tests\MahoFrontendTestCase::class);

function previewDesign(array $query, string $area = 'frontend'): Mage_Core_Model_Design_Package
{
    $request = Mage::app()->getRequest();
    foreach ([Mage_Core_Model_Design_Package::PREVIEW_PACKAGE_PARAM, Mage_Core_Model_Design_Package::PREVIEW_SKIN_PARAM] as $name) {
        $request->setQuery($name, $query[$name] ?? null);
    }
    Mage::app()->getStore()->setConfig('design/package/name', 'base');
    Mage::app()->getStore()->setConfig('design/theme/skin', '');
    return Mage::getModel('core/design_package')->setArea($area)->setStore(Mage::app()->getStore());
}

it('renders the skin named in the query', function () {
    expect(previewDesign(['___skin' => 'food'])->getTheme('skin'))->toBe('food');
});

it('renders the package named in the query', function () {
    expect(previewDesign(['___package' => 'legacy'])->getPackageName())->toBe('legacy');
});

it('keeps the configured design for a skin or package that does not exist', function () {
    $design = previewDesign(['___package' => 'nope', '___skin' => '../../etc']);
    expect($design->getPackageName())->toBe('base')
        ->and($design->getTheme('skin'))->toBe('default');
});

it('ignores the query outside the frontend area', function () {
    expect(previewDesign(['___skin' => 'food'], 'adminhtml')->getTheme('skin'))->toBe('default');
});

it('bans the block cache for a previewed request', function () {
    Mage::app()->getCache()->unbanUse(Mage_Core_Block_Abstract::CACHE_GROUP);
    previewDesign(['___skin' => 'food'])->getTheme('skin');
    expect(Mage::app()->getCache()->canUse(Mage_Core_Block_Abstract::CACHE_GROUP))->toBeFalse();
});
