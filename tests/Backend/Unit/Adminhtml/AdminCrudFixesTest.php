<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * Each case breaks an admin page that tests/Browser/AdminCrudTest.php opens.
 * A PHP deprecation breaks the page in developer mode, so these cases throw on one.
 */
function throwOnDeprecation(callable $run): mixed
{
    set_error_handler(fn(int $level, string $message) => throw new ErrorException($message, 0, $level));
    try {
        return $run();
    } finally {
        restore_error_handler();
    }
}

it('reads a null data key as a missing key', function () {
    $object = new Maho\DataObject(['name' => 'value']);

    expect(throwOnDeprecation(fn() => $object->getData(null)))->toBeNull();
});

it('reads the tax classes of a tax rule that has no id yet', function () {
    $calculation = Mage::getModel('tax/calculation');

    expect(throwOnDeprecation(fn() => $calculation->getCustomerTaxClasses(null)))->toBe([])
        ->and(throwOnDeprecation(fn() => $calculation->getProductTaxClasses(null)))->toBe([])
        ->and(throwOnDeprecation(fn() => $calculation->getRates(null)))->toBe([]);
});

it('stores an empty numeric form field as no value', function () {
    $attribute = Mage::getSingleton('eav/config')->getAttribute('customer_address', 'region_id');
    $address = Mage::getModel('sales/quote_address');
    $field = Mage_Eav_Model_Attribute_Data::factory($attribute, $address);

    $field->compactValue('');
    expect($address->getRegionId())->toBeNull();

    $field->compactValue('49');
    expect($address->getRegionId())->toBe(49);
});

it('opens a feed and a feed destination that have no platform and no type yet', function () {
    expect(Maho_FeedManager_Model_Platform::getAdapter(null))->toBeNull()
        ->and(Maho_FeedManager_Model_Destination::getRequiredConfigFields(null))->toBe([]);
});

it('keeps the admin allowlist caches apart from the table cache', function () {
    $resource = Mage::getSingleton('core/resource');

    expect(Mage_Admin_Model_Resource_Block::CACHE_ID)->not->toBe($resource->getTableName('admin/permission_block'))
        ->and(Mage_Admin_Model_Resource_Variable::CACHE_ID)->not->toBe($resource->getTableName('admin/permission_variable'));
});

it('logs the admin out only when the custom admin path flag changes', function () {
    $beforeSave = new ReflectionMethod(Mage_Adminhtml_Model_System_Config_Backend_Admin_Usecustompath::class, '_beforeSave');
    $flag = fn(string $value) => Mage::getModel('adminhtml/system_config_backend_admin_usecustompath')
        ->setPath('admin/url/use_custom_path')
        ->setValue($value);

    // A store that never saved the flag holds no value for it, and the form posts '0'
    Mage::getConfig()->setNode(Mage_Adminhtml_Helper_Data::XML_PATH_USE_CUSTOM_ADMIN_PATH, '');
    Mage::unregister('custom_admin_path_redirect');
    $beforeSave->invoke($flag('0'));
    expect(Mage::registry('custom_admin_path_redirect'))->toBeNull();

    $beforeSave->invoke($flag('1'));
    expect(Mage::registry('custom_admin_path_redirect'))->toBeTrue();

    Mage::unregister('custom_admin_path_redirect');
});
