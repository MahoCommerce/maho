<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('Mage::getService()', function () {
    it('resolves an alias through the services class group of the module', function () {
        expect(Mage::getService('customersegmentation/segment'))
            ->toBeInstanceOf(Maho_CustomerSegmentation_Service_Segment::class);
    });

    it('returns one instance for each alias', function () {
        expect(Mage::getService('customersegmentation/segment'))
            ->toBe(Mage::getService('customersegmentation/segment'));
    });

    it('uses the class of a rewrite instead of the class of the group', function () {
        $config = Mage::getConfig();
        $config->setNode('global/services/pestrewrite/class', 'Pest_Missing_Service');
        $config->setNode('global/services/pestrewrite/rewrite/segment', Maho_CustomerSegmentation_Service_Segment::class);

        expect(Mage::getService('pestrewrite/segment'))
            ->toBeInstanceOf(Maho_CustomerSegmentation_Service_Segment::class);
    });

    it('refuses an alias without a class', function () {
        expect(fn() => Mage::getService('customersegmentation/missing'))->toThrow(RuntimeException::class)
            ->and(fn() => Mage::getService('customersegmentation'))->toThrow(RuntimeException::class);
    });
});
