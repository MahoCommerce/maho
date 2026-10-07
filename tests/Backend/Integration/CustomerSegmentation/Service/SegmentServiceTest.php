<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

function pestSegmentService(): Maho_CustomerSegmentation_Service_Segment
{
    /** @var Maho_CustomerSegmentation_Service_Segment */
    return Mage::getService('customersegmentation/segment');
}

describe('Customer segment service', function () {
    it('reports every problem of a segment with its field and saves nothing', function () {
        $segment = Mage::getModel('customersegmentation/segment')
            ->setName('')
            ->setWebsiteIds([1, 999])
            ->setCustomerGroupIds([998])
            ->setRefreshMode('often')
            ->setPriority(-1);

        try {
            pestSegmentService()->save($segment);
            $this->fail('The service saved an invalid segment');
        } catch (Mage_Core_Exception_Input $e) {
            expect(array_column($e->getErrors(), 'field'))
                ->toBe(['name', 'website_ids', 'customer_group_ids', 'refresh_mode', 'priority'])
                ->and(explode("\n", $e->getMessage()))->toHaveCount(5);
        }

        expect($segment->getId())->toBeNull();
    });

    it('saves a segment and keeps the fields that a later change does not touch', function () {
        $segment = pestSegmentService()->save(
            Mage::getModel('customersegmentation/segment')
                ->setName('Pest service ' . uniqid())
                ->setDescription('First')
                ->setWebsiteIds([1])
                ->setCustomerGroupIds([1])
                ->setRefreshMode('manual')
                ->setPriority(3),
        );

        try {
            pestSegmentService()->save(pestSegmentService()->getById((int) $segment->getId())->setIsActive(false));

            $reloaded = pestSegmentService()->getById((int) $segment->getId());
            expect($reloaded->getIsActive())->toBeFalse()
                ->and($reloaded->getDescription())->toBe('First')
                ->and($reloaded->getWebsiteIds())->toBe([1])
                ->and($reloaded->getCustomerGroupIds())->toBe([1])
                ->and($reloaded->getRefreshMode())->toBe('manual')
                ->and($reloaded->getPriority())->toBe(3);
        } finally {
            pestSegmentService()->delete($segment);
        }

        expect(fn() => pestSegmentService()->getById((int) $segment->getId()))
            ->toThrow(Mage_Core_Exception_NoSuchEntity::class);
    });
});
