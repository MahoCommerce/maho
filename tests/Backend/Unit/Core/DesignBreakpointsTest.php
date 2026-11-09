<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

function designBreakpoints(array $widths = []): Mage_Core_Model_Design_Breakpoints
{
    $store = Mage::app()->getStore();
    foreach (['mobile' => '767', 'tablet' => '1023', ...$widths] as $code => $width) {
        $store->setConfig(Mage_Core_Model_Design_Breakpoints::CONFIG_PATH . '/' . $code, $width);
    }
    return Mage::getModel('core/design_breakpoints');
}

/** Run the save checks of the backend model on $field, with the posted values of the group. */
function saveBreakpoint(string $field, string $value, array $posted = []): void
{
    $model = Mage::getModel('adminhtml/system_config_backend_design_breakpoint')
        ->setPath(Mage_Core_Model_Design_Breakpoints::CONFIG_PATH . '/' . $field)
        ->setField($field)
        ->setValue($value)
        ->setScope('default')
        ->setScopeId(0)
        ->setFieldsetData([$field => $value, ...$posted]);
    (fn() => $this->_beforeSave())->call($model);
}

afterEach(function () {
    designBreakpoints();
});

it('reads a width as a whole number in range', function (mixed $value, ?int $expected) {
    expect(Mage_Core_Model_Design_Breakpoints::toWidth($value))->toBe($expected);
})->with([
    'a number' => ['767', 767],
    'spaces around it' => [' 767 ', 767],
    'the lowest width' => ['1', 1],
    'the highest width' => ['9999', 9999],
    'zero' => ['0', null],
    'above the range' => ['10000', null],
    'a decimal' => ['767.5', null],
    'a unit' => ['767px', null],
    'empty' => ['', null],
    'missing' => [null, null],
]);

it('writes the media query of a preset from the configured width', function () {
    expect(designBreakpoints()->getMediaQuery('mobile'))->toBe('(max-width: 767px)');
    expect(designBreakpoints(['tablet' => '1100'])->getMediaQuery('tablet'))->toBe('(max-width: 1100px)');
});

it('writes a query that matches no device for an unknown preset or a width that is not valid', function () {
    expect(designBreakpoints()->getMediaQuery('watch'))->toBe(Mage_Core_Model_Design_Breakpoints::NO_MATCH);
    expect(designBreakpoints(['mobile' => 'wide'])->getMediaQuery('mobile'))->toBe(Mage_Core_Model_Design_Breakpoints::NO_MATCH);
});

it('gives the editor the presets that have a valid width, from the narrowest', function () {
    expect(designBreakpoints()->getEditorPresets())->toBe([
        ['code' => 'mobile', 'label' => 'Mobile', 'width' => 767],
        ['code' => 'tablet', 'label' => 'Tablet', 'width' => 1023],
    ]);
    expect(array_column(designBreakpoints(['mobile' => ''])->getEditorPresets(), 'code'))->toBe(['tablet']);
});

it('resolves the directive in the media attribute of a source in CMS content', function () {
    designBreakpoints(['mobile' => '700']);
    $html = '<picture><source media="{{breakpoint code=mobile}}" srcset="a.jpg"><img src="b.jpg" alt=""></picture>';

    expect(Mage::helper('cms')->getPageTemplateProcessor()->filter($html))
        ->toContain('<source media="(max-width: 700px)" srcset="a.jpg">');
});

it('resolves the directive in a product description', function () {
    designBreakpoints(['tablet' => '1100']);

    expect(Mage::helper('catalog')->getPageTemplateProcessor()->filter('<source media="{{breakpoint code=tablet}}">'))
        ->toBe('<source media="(max-width: 1100px)">');
});

it('keeps the directive when the sanitizer runs on save', function () {
    $html = '<picture><source media="{{breakpoint code=mobile}}" srcset="a.jpg"><img src="b.jpg" alt=""></picture>';
    $result = Mage::getSingleton('core/input_filter_maliciousCode')
        ->filterPreservingDirectives($html, false, Mage::helper('cms')->getPageTemplateProcessor());

    expect($result)->toContain('media="{{breakpoint code=mobile}}"');
});

it('keeps the directive as an invalid media query on a path that does not resolve it', function () {
    // The browser reads the unresolved text as a query that matches no device, so it shows the default image
    expect(Mage_Core_Model_Input_Filter_MaliciousCode::stripDirectives('<source media="{{breakpoint code=mobile}}">'))
        ->toBe('<source media="{{breakpoint code=mobile}}">');
});

it('saves a preset width in range', function () {
    designBreakpoints();
    saveBreakpoint('mobile', '600');
})->throwsNoExceptions();

it('refuses a preset width that is not a whole number in range', function (string $value) {
    designBreakpoints();
    saveBreakpoint('mobile', $value);
})->with(['0', '10000', '767px', ''])->throws(Mage_Core_Exception::class, 'Enter a whole number between 1 and 9999.');

it('refuses a preset that is not wider than the preset above it', function () {
    designBreakpoints();
    saveBreakpoint('tablet', '700', ['mobile' => '767']);
})->throws(Mage_Core_Exception::class, 'Each breakpoint must be wider than the breakpoint above it.');

it('compares with the inherited width of a preset that the form does not post', function () {
    designBreakpoints();
    saveBreakpoint('mobile', '1100');
})->throws(Mage_Core_Exception::class, 'Each breakpoint must be wider than the breakpoint above it.');
