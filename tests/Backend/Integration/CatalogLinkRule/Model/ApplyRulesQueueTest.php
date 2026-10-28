<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

function clqMessages(string $dedupeKey): array
{
    $resource = Mage::getSingleton('core/resource');
    $adapter = $resource->getConnection('core_read');

    return $adapter->fetchAll(
        $adapter->select()
            ->from($resource->getTableName('queue/message'), ['queue', 'message_class', 'status'])
            ->where('dedupe_key = ?', $dedupeKey),
    );
}

function clqDeleteMessages(string $dedupeKey): void
{
    $resource = Mage::getSingleton('core/resource');
    $resource->getConnection('core_write')->delete($resource->getTableName('queue/message'), ['dedupe_key = ?' => $dedupeKey]);
}

/**
 * Processor whose run fails, to check the notice of a failed run.
 */
class Maho_CatalogLinkRule_Test_FailingProcessor extends Maho_CatalogLinkRule_Model_Processor
{
    #[\Override]
    public function applyRules(?int $linkTypeId = null): void
    {
        throw new RuntimeException('clq-failure');
    }
}

/**
 * @return array<int, array{severity: string, title: string, description: string}>
 */
function clqTakeNotices(int $adminUserId): array
{
    $resource = Mage::getSingleton('core/resource');
    $adapter = $resource->getConnection('core_write');
    $table = $resource->getTableName('adminnotification/inbox');
    $where = ['admin_user_id = ?' => $adminUserId, 'title LIKE ?' => 'The product relationship rules%'];

    $select = $adapter->select()->from($table, ['severity', 'title', 'description']);
    foreach ($where as $condition => $value) {
        $select->where($condition, $value);
    }
    $notices = $adapter->fetchAll($select);
    $adapter->delete($table, $where);

    return $notices;
}

function clqAdminUserId(): int
{
    return (int) Mage::getResourceModel('admin/user_collection')->setPageSize(1)->getFirstItem()->getId();
}

describe('CatalogLinkRule apply from the admin', function () {
    test('the apply buttons queue one run for each link type', function () {
        $linkType = Mage_Catalog_Model_Product_Link::LINK_TYPE_UPSELL;
        $dedupeKey = 'cataloglinkrule_apply_' . $linkType;
        clqDeleteMessages($dedupeKey);

        $processor = Mage::getModel('cataloglinkrule/processor');
        $processor->queueApplyRules($linkType);
        $processor->queueApplyRules($linkType);
        $messages = clqMessages($dedupeKey);
        clqDeleteMessages($dedupeKey);

        expect($messages)->toHaveCount(1)
            ->and($messages[0]['queue'])->toBe(Maho_CatalogLinkRule_Model_Processor::QUEUE_NAME)
            ->and($messages[0]['message_class'])->toBe(Maho_CatalogLinkRule_Model_ApplyRulesMessage::class);
    });

    test('a finished run sends a notice to the administrator who started it', function () {
        $adminUserId = clqAdminUserId();
        clqTakeNotices($adminUserId);

        // No rule has this link type, so the run is empty and fast
        Mage::getModel('cataloglinkrule/processor')
            ->handleApplyRules(new Maho_CatalogLinkRule_Model_ApplyRulesMessage(999, $adminUserId));
        $notices = clqTakeNotices($adminUserId);

        expect($notices)->toHaveCount(1)
            ->and((int) $notices[0]['severity'])->toBe(Mage_AdminNotification_Model_Inbox::SEVERITY_NOTICE)
            ->and($notices[0]['title'])->toBe('The product relationship rules have been applied');
    });

    test('a failed run sends a major notice and lets the queue try again', function () {
        $adminUserId = clqAdminUserId();
        clqTakeNotices($adminUserId);

        $run = fn() => (new Maho_CatalogLinkRule_Test_FailingProcessor())
            ->handleApplyRules(new Maho_CatalogLinkRule_Model_ApplyRulesMessage(null, $adminUserId));

        expect($run)->toThrow(RuntimeException::class, 'clq-failure');
        $notices = clqTakeNotices($adminUserId);
        expect($notices)->toHaveCount(1)
            ->and((int) $notices[0]['severity'])->toBe(Mage_AdminNotification_Model_Inbox::SEVERITY_MAJOR)
            ->and($notices[0]['description'])->toContain('clq-failure');
    });

    test('a run of the rules releases the lock at its end', function () {
        $lock = Mage::getSingleton('core/lock');

        Mage::getModel('cataloglinkrule/processor')->applyRules(999999);
        $free = $lock->acquire('cataloglinkrule_apply');
        $lock->release('cataloglinkrule_apply');

        expect($free)->toBeTrue();
    });

    test('a second run of the rules waits for the run that holds the lock', function () {
        if ((string) Mage::getConfig()->getNode(Mage_Core_Model_Lock::XML_PATH_BACKEND) === 'db') {
            $this->markTestSkipped('This test needs the file lock backend.');
        }

        $path = Mage::getConfig()->getVarDir('locks') . DS . 'cataloglinkrule_apply.lock';
        $child = proc_open(
            [PHP_BINARY, '-r', '$h = fopen($argv[1], "c"); flock($h, LOCK_EX); echo "locked\n"; sleep(2);', $path],
            [1 => ['pipe', 'w']],
            $pipes,
        );
        expect(trim((string) fgets($pipes[1])))->toBe('locked');
        $startedAt = microtime(true);
        try {
            expect(fn() => Mage::getModel('cataloglinkrule/processor')->applyRules(999999))
                ->not->toThrow(RuntimeException::class);
        } finally {
            fclose($pipes[1]);
            proc_close($child);
        }

        expect(microtime(true) - $startedAt)->toBeGreaterThan(1.0);
    });

    test('a queue worker has a handler for the apply message', function () {
        expect(Maho\Queue\HandlerRegistry::allowedMessageClasses())
            ->toContain(Maho_CatalogLinkRule_Model_ApplyRulesMessage::class);
    });
});
