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
                ->toBe(['name', 'refresh_mode', 'priority', 'website_ids', 'customer_group_ids'])
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

    it('keeps a stored customer group that was deleted later, and still refuses a new unknown one', function () {
        $group = Mage::getModel('customer/group')->setCode('pest_cseg_' . uniqid())->setTaxClassId(3)->save();
        $groupId = (int) $group->getId();
        $segment = pestSegmentService()->save(
            pestSegmentService()->newSegment()
                ->setName('Pest deleted group ' . uniqid())
                ->setWebsiteIds([1])
                ->setCustomerGroupIds([1, $groupId]),
        );
        $group->delete();

        try {
            expect(pestSegmentService()->save(pestSegmentService()->getById((int) $segment->getId())->setPriority(2))->getPriority())->toBe(2)
                ->and(fn() => pestSegmentService()->save(pestSegmentService()->getById((int) $segment->getId())->setCustomerGroupIds([1, $groupId, 99999])))
                ->toThrow(Mage_Core_Exception_Input::class);
        } finally {
            pestSegmentService()->delete($segment);
        }
    });

    it('treats a segment outside the given websites as missing', function () {
        $segment = pestSegmentService()->save(
            pestSegmentService()->newSegment()->setName('Pest scope ' . uniqid())->setWebsiteIds([1]),
        );

        try {
            expect(pestSegmentService()->getById((int) $segment->getId(), [1])->getId())->toBe($segment->getId())
                ->and(fn() => pestSegmentService()->getById((int) $segment->getId(), [999]))
                ->toThrow(Mage_Core_Exception_NoSuchEntity::class)
                ->and(fn() => pestSegmentService()->getById((int) $segment->getId(), []))
                ->toThrow(Mage_Core_Exception_NoSuchEntity::class);
        } finally {
            pestSegmentService()->delete($segment);
        }
    });

    it('refuses the email automation of a segment without an email sequence', function () {
        $segment = pestSegmentService()->save(
            pestSegmentService()->newSegment()->setName('Pest automation ' . uniqid())->setWebsiteIds([1]),
        );

        try {
            pestSegmentService()->save($segment->setAutoEmailActive());
            $this->fail('The service turned on the email automation of a segment without a sequence');
        } catch (Mage_Core_Exception_Input $e) {
            expect(array_column($e->getErrors(), 'field'))->toBe(['auto_email_active'])
                ->and(pestSegmentService()->getById((int) $segment->getId())->getAutoEmailActive())->toBeFalse();
        } finally {
            pestSegmentService()->delete($segment);
        }
    });

    it('checks the name, the websites, the refresh mode and the priority on a direct model save', function () {
        $segment = Mage::getModel('customersegmentation/segment')
            ->setName(str_repeat('n', 256))
            ->setWebsiteIds([])
            ->setRefreshMode('often')
            ->setPriority(-1);

        try {
            $segment->save();
            $this->fail('The model saved an invalid segment');
        } catch (Mage_Core_Exception_Input $e) {
            expect(array_column($e->getErrors(), 'field'))->toBe(['name', 'website_ids', 'refresh_mode', 'priority']);
        }
        expect($segment->getId())->toBeNull();

        $saved = Mage::getModel('customersegmentation/segment')->setName('  Pest direct ' . uniqid() . '  ')->setWebsiteIds([1]);
        $saved->save();
        try {
            expect($saved->getName())->toBe(trim((string) $saved->getName()))
                ->and(Mage::getModel('customersegmentation/segment')->load($saved->getId())->getName())->toStartWith('Pest direct');
        } finally {
            $saved->delete();
        }
    });

    it('lets a client of some websites change only a segment whose websites are all among them', function () {
        $segment = pestSegmentService()->save(
            pestSegmentService()->newSegment()->setName('Pest forbidden ' . uniqid())->setWebsiteIds([1]),
        );
        $id = (int) $segment->getId();

        try {
            expect(fn() => pestSegmentService()->save(pestSegmentService()->getById($id)->setPriority(4), [999]))
                ->toThrow(Mage_Core_Exception_Forbidden::class)
                ->and(fn() => pestSegmentService()->save(pestSegmentService()->getById($id)->setWebsiteIds([1, 999]), [1]))
                ->toThrow(Mage_Core_Exception_Forbidden::class)
                ->and(fn() => pestSegmentService()->delete(pestSegmentService()->getById($id), [999]))
                ->toThrow(Mage_Core_Exception_Forbidden::class)
                ->and(fn() => pestSegmentService()->refresh(pestSegmentService()->getById($id), websiteIds: [999]))
                ->toThrow(Mage_Core_Exception_Forbidden::class)
                ->and(pestSegmentService()->getById($id)->getPriority())->toBe(0)
                ->and(pestSegmentService()->save(pestSegmentService()->getById($id)->setPriority(4), [1])->getPriority())->toBe(4);
        } finally {
            pestSegmentService()->delete(pestSegmentService()->getById($id));
        }
    });
});
