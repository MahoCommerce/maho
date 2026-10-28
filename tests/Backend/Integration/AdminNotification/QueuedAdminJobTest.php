<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * An admin action that can take minutes queues its work, and the administrator who clicked gets
 * the result in the inbox.
 */
function qajAdminUserId(): int
{
    return (int) Mage::getResourceModel('admin/user_collection')->setPageSize(1)->getFirstItem()->getId();
}

/**
 * @return list<array{severity: string, title: string, description: string, admin_user_id: ?string, acl_resource: ?string}>
 */
function qajTakeNotices(string $titlePrefix): array
{
    $resource = Mage::getSingleton('core/resource');
    $adapter = $resource->getConnection('core_write');
    $table = $resource->getTableName('adminnotification/inbox');

    $notices = $adapter->fetchAll(
        $adapter->select()
            ->from($table, ['severity', 'title', 'description', 'admin_user_id', 'acl_resource'])
            ->where('title LIKE ?', $titlePrefix . '%'),
    );
    $adapter->delete($table, ['title LIKE ?' => $titlePrefix . '%']);

    return $notices;
}

/**
 * @return list<array{queue: string, message_class: string, body: string}>
 */
function qajTakeMessages(string $dedupeKey): array
{
    $resource = Mage::getSingleton('core/resource');
    $adapter = $resource->getConnection('core_write');
    $table = $resource->getTableName('queue/message');

    $messages = $adapter->fetchAll(
        $adapter->select()->from($table, ['queue', 'message_class', 'body'])->where('dedupe_key = ?', $dedupeKey),
    );
    $adapter->delete($table, ['dedupe_key = ?' => $dedupeKey]);

    return $messages;
}

describe('runAndNotify()', function () {
    test('a finished job sends its text as a notice to the administrator', function () {
        $title = 'qaj-done-' . uniqid();
        $adminUserId = qajAdminUserId();

        Mage::helper('adminnotification')->runAndNotify(
            fn(): string => 'qaj-text',
            $title,
            'qaj-failed',
            'adminhtml/dashboard/index',
            $adminUserId,
            'system/config',
        );
        $notices = qajTakeNotices($title);

        expect($notices)->toHaveCount(1)
            ->and((int) $notices[0]['severity'])->toBe(Mage_AdminNotification_Model_Inbox::SEVERITY_NOTICE)
            ->and($notices[0]['description'])->toBe('qaj-text')
            ->and((int) $notices[0]['admin_user_id'])->toBe($adminUserId)
            ->and($notices[0]['acl_resource'])->toBeNull();
    });

    test('a failed job sends a major message and throws again, so the queue retries', function () {
        $title = 'qaj-failed-' . uniqid();

        $run = fn() => Mage::helper('adminnotification')->runAndNotify(
            fn(): string => throw new RuntimeException('qaj-error'),
            'qaj-done',
            $title,
            'adminhtml/dashboard/index',
            null,
            'system/config',
        );

        expect($run)->toThrow(RuntimeException::class, 'qaj-error');
        $notices = qajTakeNotices($title);
        expect($notices)->toHaveCount(1)
            ->and((int) $notices[0]['severity'])->toBe(Mage_AdminNotification_Model_Inbox::SEVERITY_MAJOR)
            ->and($notices[0]['description'])->toContain('qaj-error')
            ->and($notices[0]['admin_user_id'])->toBeNull()
            ->and($notices[0]['acl_resource'])->toBe('system/config');
    });

    test('the link of a message has no secret key', function () {
        expect(Mage::helper('adminnotification')->getAdminUrl('adminhtml/promo_catalog/index'))
            ->not->toContain('/key/');
    });
});

describe('Admin actions that run in the queue', function () {
    test('Apply Rules of the catalog price rules queues one run', function () {
        $dedupeKey = Mage_CatalogRule_Model_ApplyAllMessage::DEDUPE_KEY;
        qajTakeMessages($dedupeKey);

        Mage::getModel('catalogrule/rule')->queueApplyAll(qajAdminUserId());
        Mage::getModel('catalogrule/rule')->queueApplyAll(qajAdminUserId());
        $messages = qajTakeMessages($dedupeKey);

        expect($messages)->toHaveCount(1)
            ->and($messages[0]['queue'])->toBe(Mage_CatalogRule_Model_ApplyAllMessage::QUEUE_NAME)
            ->and($messages[0]['message_class'])->toBe(Mage_CatalogRule_Model_ApplyAllMessage::class);
    });

    test('Generate of a sitemap queues one run for each sitemap', function () {
        $sitemap = Mage::getModel('sitemap/sitemap');
        $sitemap->setId(990001);
        $dedupeKey = (new Mage_Sitemap_Model_GenerateMessage(990001))->getDedupeKey();
        qajTakeMessages($dedupeKey);

        $sitemap->queueGenerate(qajAdminUserId());
        $sitemap->queueGenerate(qajAdminUserId());
        $messages = qajTakeMessages($dedupeKey);

        expect($messages)->toHaveCount(1)
            ->and($messages[0]['message_class'])->toBe(Mage_Sitemap_Model_GenerateMessage::class);
    });

    test('a queued sitemap that an administrator deleted sends no message', function () {
        (new Mage_Sitemap_Model_GenerateMessageHandler())
            ->generate(new Mage_Sitemap_Model_GenerateMessage(990002, qajAdminUserId()));

        expect(qajTakeNotices('The sitemap'))->toBe([]);
    });

    test('Refresh of a segment queues one run and marks the segment pending', function () {
        $segment = Mage::getModel('customersegmentation/segment');
        $segment->setName('qaj-segment-' . uniqid());
        $segment->setIsActive();
        $segment->setWebsiteIds([1]);
        $segment->setCustomerGroupIds([0, 1]);
        $segment->setConditionsSerialized(Mage::helper('core')->jsonEncode([
            'type' => 'customersegmentation/segment_condition_combine',
            'aggregator' => 'all',
            'value' => 1,
            'conditions' => [],
        ]));
        $segment->setRefreshMode('manual');
        $segment->setRefreshStatus(Maho_CustomerSegmentation_Model_Segment::STATUS_COMPLETED);
        $segment->save();
        $segmentId = (int) $segment->getId();
        $dedupeKey = (new Maho_CustomerSegmentation_Model_RefreshMessage($segmentId))->getDedupeKey();

        $service = Mage::getService('customersegmentation/segment');
        $service->queueRefresh($segment, qajAdminUserId());
        $service->queueRefresh($segment, qajAdminUserId());
        $messages = qajTakeMessages($dedupeKey);
        $status = Mage::getModel('customersegmentation/segment')->load($segmentId)->getRefreshStatus();
        $segment->delete();

        expect($messages)->toHaveCount(1)
            ->and($messages[0]['message_class'])->toBe(Maho_CustomerSegmentation_Model_RefreshMessage::class)
            ->and($status)->toBe(Maho_CustomerSegmentation_Model_Segment::STATUS_PENDING);
    });

    test('a queued refresh of a segment that an administrator deleted sends no message', function () {
        (new Maho_CustomerSegmentation_Model_RefreshMessageHandler())
            ->refresh(new Maho_CustomerSegmentation_Model_RefreshMessage(990003, qajAdminUserId()));

        expect(qajTakeNotices('The segment'))->toBe([]);
    });

    test('a lifetime refresh of an unknown report sends no message', function () {
        (new Mage_Reports_Model_Queue_RefreshStatisticsHandler())
            ->__invoke(new Mage_Reports_Model_Queue_RefreshStatistics('qaj-unknown', qajAdminUserId()));

        expect(qajTakeNotices('The lifetime statistics'))->toBe([]);
    });

    test('a queue worker has a handler for each admin message', function () {
        expect(Maho\Queue\HandlerRegistry::allowedMessageClasses())->toContain(
            Mage_CatalogRule_Model_ApplyAllMessage::class,
            Mage_Sitemap_Model_GenerateMessage::class,
            Maho_CustomerSegmentation_Model_RefreshMessage::class,
            Mage_Reports_Model_Queue_RefreshStatistics::class,
        );
    });
});
