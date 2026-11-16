<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Tests\Helpers\AdminRoleFixture;

uses(Tests\MahoBackendTestCase::class);

/**
 * A saved report is private to its owner or shared with every administrator. Only the owner changes or deletes it.
 */

beforeEach(function () {
    $this->owner = AdminRoleFixture::login(['report']);
    $this->other = AdminRoleFixture::login(['report']);
    $this->service = Mage::getService('reports/savedReport');
    $this->access = Mage_Reports_Model_Explorer_Access::full();
});

afterEach(function () {
    AdminRoleFixture::delete($this->owner);
    AdminRoleFixture::delete($this->other);
});

function savedReportFor(int $adminUserId, bool $shared): Mage_Reports_Model_SavedReport
{
    $report = Mage::getService('reports/savedReport')->newReport()
        ->setName('  Monthly sales  ')
        ->setIsShared($shared)
        ->setRequestData(['dataset' => 'sales_orders', 'metrics' => ['orders'], 'dimensions' => ['period:month'], 'offset' => 100]);
    return Mage::getService('reports/savedReport')->save($report, $adminUserId, Mage_Reports_Model_Explorer_Access::full());
}

it('saves a report for its owner with a trimmed name and no page offset', function () {
    $report = savedReportFor((int) $this->owner->getId(), false);

    $loaded = $this->service->getById((int) $report->getId(), (int) $this->owner->getId());
    expect($loaded->getName())->toBe('Monthly sales')
        ->and($loaded->getAdminUserId())->toBe((int) $this->owner->getId())
        ->and($loaded->getRequestData())->not->toHaveKey('offset');
});

it('hides a private report from the other administrators', function () {
    $report = savedReportFor((int) $this->owner->getId(), false);

    expect(fn() => $this->service->getById((int) $report->getId(), (int) $this->other->getId()))
        ->toThrow(Mage_Core_Exception_NoSuchEntity::class)
        ->and(array_map(static fn($r): int => (int) $r->getId(), $this->service->listFor((int) $this->other->getId(), $this->access)))
        ->not->toContain((int) $report->getId());
});

it('shows a shared report to the other administrators, who cannot change or delete it', function () {
    $report = savedReportFor((int) $this->owner->getId(), true);
    $other = (int) $this->other->getId();

    $seen = $this->service->getById((int) $report->getId(), $other);
    expect($seen->getName())->toBe('Monthly sales')
        ->and(fn() => $this->service->save($seen->setName('Mine now'), $other, $this->access))->toThrow(Mage_Core_Exception_Forbidden::class)
        ->and(fn() => $this->service->delete($seen, $other))->toThrow(Mage_Core_Exception_Forbidden::class);
});

it('refuses a report without a name or with an unknown metric', function () {
    $owner = (int) $this->owner->getId();
    $noName = $this->service->newReport()->setName(' ')->setRequestData(['dataset' => 'sales_orders', 'metrics' => ['orders']]);
    $badMetric = $this->service->newReport()->setName('Bad')->setRequestData(['dataset' => 'sales_orders', 'metrics' => ['nothing']]);

    expect(fn() => $this->service->save($noName, $owner, $this->access))->toThrow(Mage_Core_Exception_Input::class)
        ->and(fn() => $this->service->save($badMetric, $owner, $this->access))->toThrow(Mage_Core_Exception_Input::class);
});

it('deletes the reports of an administrator with the administrator', function () {
    $report = savedReportFor((int) $this->other->getId(), true);
    AdminRoleFixture::delete($this->other);
    $this->other = AdminRoleFixture::login(['report']);

    expect(Mage::getModel('reports/savedReport')->load($report->getId())->getId())->toBeNull();
});
