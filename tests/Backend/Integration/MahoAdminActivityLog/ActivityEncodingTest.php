<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_AdminActivityLog
 */

declare(strict_types=1);

use Maho\Event;
use Maho\Event\Observer;

uses(Tests\MahoBackendTestCase::class);

it('records an update when a value contains invalid UTF-8', function (
    string $oldContent,
    string $newContent,
    string $expectedOldContent,
    string $expectedNewContent,
): void {
    $session = Mage::getSingleton('admin/session');
    $previousUser = $session->getUser();
    $admin = Mage::getResourceModel('admin/user_collection')->setPageSize(1)->getFirstItem();
    $block = Mage::getModel('cms/block')
        ->setId(random_int(100_000, 2_000_000_000))
        ->setTitle('Résumé')
        ->setContent($oldContent)
        ->setUpdateTime('2026-01-01 10:00:00')
        ->setOrigData();
    $event = new Observer(['event' => new Event(['object' => $block])]);
    $observer = new Maho_AdminActivityLog_Model_Observer();
    $activity = Mage::getModel('adminactivitylog/activity');

    try {
        $session->setUser($admin);
        $observer->logAdminActivityBefore($event);
        $block->setTitle('Café')->setContent($newContent)->setUpdateTime('2026-01-02 10:00:00');
        $observer->logAdminActivityAfter($event);

        $activity = $activity->getCollection()
            ->addFieldToFilter('entity_type', 'cms/block')
            ->addFieldToFilter('entity_id', $block->getId())
            ->getFirstItem();

        expect($activity->getId())->not->toBeNull()
            ->and($activity->getActionType())->toBe('update')
            ->and($activity->getOldData())->toBe(['title' => 'Résumé', 'content' => $expectedOldContent])
            ->and($activity->getNewData())->toBe(['title' => 'Café', 'content' => $expectedNewContent]);
    } finally {
        $session->setUser($previousUser);
        if ($activity->getId()) {
            $activity->delete();
        }
    }
})->with([
    'in the old value' => ["Before \xFF", 'After', 'Before �', 'After'],
    'in the new value' => ['Before', "After \xFF", 'Before', 'After �'],
]);
