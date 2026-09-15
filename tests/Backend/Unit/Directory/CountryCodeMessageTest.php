<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

it('escapes the rejected country code in the error message', function () {
    try {
        Mage::getModel('directory/country')->loadByCode('<b>x</b>');
        $this->fail('An invalid country code must raise an exception.');
    } catch (Mage_Core_Exception $e) {
        expect($e->getMessage())->toContain('&lt;b&gt;x&lt;/b&gt;')->not->toContain('<b>');
    }
});
