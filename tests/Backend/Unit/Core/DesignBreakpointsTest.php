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

/**
 * A backend model for $field in $scope, with the fields of the group as the admin form posts them:
 * `['tablet' => ['value' => '1200'], 'mobile' => ['inherit' => 1]]`.
 */
function breakpointField(string $field, array $fields, string $scope = 'default', int $scopeId = 0): Mage_Adminhtml_Model_System_Config_Backend_Design_Breakpoint
{
    return Mage::getModel('adminhtml/system_config_backend_design_breakpoint')
        ->setPath(Mage_Core_Model_Design_Breakpoints::CONFIG_PATH . '/' . $field)
        ->setField($field)
        ->setValue($fields[$field]['value'] ?? null)
        ->setScope($scope)
        ->setScopeId($scopeId)
        ->setGroupId('breakpoints')
        ->setGroups(['breakpoints' => ['fields' => $fields]])
        ->setFieldsetData(array_map(fn(array $data) => $data['value'] ?? null, $fields));
}

/** Run the protected check $method of the backend model. */
function runBreakpointCheck(Mage_Adminhtml_Model_System_Config_Backend_Design_Breakpoint $model, string $method): void
{
    (fn() => $this->{$method}())->call($model);
}

function deleteBreakpointRows(): void
{
    Mage::getSingleton('core/resource')->getConnection('core_write')
        ->delete('core_config_data', ['path LIKE ?' => Mage_Core_Model_Design_Breakpoints::CONFIG_PATH . '/%']);
}

afterEach(function () {
    deleteBreakpointRows();
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

it('compares with the current width of a preset that the form does not post', function () {
    designBreakpoints();
    saveBreakpoint('mobile', '1100');
})->throws(Mage_Core_Exception::class, 'Each breakpoint must be wider than the breakpoint above it.');

it('uses the value of the parent scope for a field that goes back to it, also when the form posts a value', function () {
    designBreakpoints();
    $model = breakpointField('mobile', ['mobile' => ['value' => '1100'], 'tablet' => ['value' => '1200', 'inherit' => 1]], 'websites', 1);
    runBreakpointCheck($model, '_beforeSave');
})->throws(Mage_Core_Exception::class, 'Each breakpoint must be wider than the breakpoint above it.');

it('refuses to reset a preset to the parent scope when the presets would be out of order', function () {
    designBreakpoints();
    $model = breakpointField('tablet', ['mobile' => ['value' => '1100'], 'tablet' => ['inherit' => 1]], 'websites', 1);
    runBreakpointCheck($model, '_beforeDelete');
})->throws(Mage_Core_Exception::class, 'Each breakpoint must be wider than the breakpoint above it.');

it('resets a preset to the parent scope when the presets stay in order', function () {
    designBreakpoints();
    $model = breakpointField('tablet', ['mobile' => ['value' => '600'], 'tablet' => ['inherit' => 1]], 'websites', 1);
    runBreakpointCheck($model, '_beforeDelete');
})->throwsNoExceptions();

it('refuses a change that puts the presets of a store view out of order', function () {
    designBreakpoints();
    $store = Mage::app()->getDefaultStoreView();
    Mage::getModel('core/config')->saveConfig(Mage_Core_Model_Design_Breakpoints::CONFIG_PATH . '/tablet', '900', 'stores', (int) $store->getId());

    // The store view keeps its Tablet of 900 and takes the new Mobile of 950
    saveBreakpoint('mobile', '950', ['tablet' => '1023']);
})->throws(Mage_Core_Exception::class, 'are not in order');

it('keeps the deleted values when another value of the same save fails its check', function () {
    $store = Mage::app()->getDefaultStoreView();
    $config = Mage::getModel('core/config');
    $path = Mage_Core_Model_Design_Breakpoints::CONFIG_PATH;
    $config->saveConfig("{$path}/mobile", '600', 'stores', (int) $store->getId());
    $config->saveConfig("{$path}/tablet", '700', 'stores', (int) $store->getId());

    $data = new Mage_Adminhtml_Model_Config_Data();
    $data->setSection('design')
        ->setWebsite($store->getWebsite()->getCode())
        ->setStore($store->getCode())
        ->setGroups(['breakpoints' => ['fields' => [
            'mobile' => ['value' => 'wide'],
            'tablet' => ['inherit' => 1],
        ]]]);

    expect(fn() => $data->save())->toThrow(Mage_Core_Exception::class, 'Enter a whole number between 1 and 9999.');

    $adapter = Mage::getSingleton('core/resource')->getConnection('core_read');
    $tablet = $adapter->fetchOne($adapter->select()
        ->from('core_config_data', ['value'])
        ->where('path = ?', "{$path}/tablet")
        ->where('scope = ?', 'stores')
        ->where('scope_id = ?', (int) $store->getId()));
    expect($tablet)->toBe('700');
});
