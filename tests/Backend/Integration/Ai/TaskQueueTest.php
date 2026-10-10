<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

uses(Tests\MahoBackendTestCase::class);

function aiTaskQueueConfigure(bool $asyncEnabled): void
{
    foreach (Mage::app()->getStores(true) as $store) {
        $store->setConfig('ai/general/enabled', '1');
        $store->setConfig('ai/queue/enabled', $asyncEnabled ? '1' : '0');
    }
}

function aiTaskQueueMessages(int $taskId): int
{
    $connection = Mage::getSingleton('core/resource')->getConnection('core_read');

    return (int) $connection->fetchOne(
        $connection->select()
            ->from(\Maho\Queue\QueueManager::tableName(), [new \Maho\Db\Expr('COUNT(*)')])
            ->where('dedupe_key = ?', Maho_Ai_Model_Task::queueKey($taskId)),
    );
}

function aiTaskQueueCleanup(): void
{
    $resource = Mage::getSingleton('core/resource');
    $connection = $resource->getConnection('core_write');
    $connection->delete(\Maho\Queue\QueueManager::tableName(), ['dedupe_key LIKE ?' => 'ai_task_%']);
    $connection->delete($resource->getTableName('ai/task'), ['consumer = ?' => 'pest_task_queue']);
    $connection->delete($resource->getTableName('ai/usage'), ['consumer = ?' => 'pest_task_queue']);
}

beforeEach(function (): void {
    if (!Mage::helper('core')->isModuleEnabled('Maho_Queue')) {
        $this->markTestSkipped('Maho_Queue is disabled');
    }
    aiTaskQueueCleanup();
});

afterEach(function (): void {
    aiTaskQueueCleanup();
});

it('queues a submitted task at once when the async queue is on, and holds it when it is off', function (): void {
    aiTaskQueueConfigure(true);
    $queued = Mage::helper('ai')->submitTask(['consumer' => 'pest_task_queue', 'messages' => [['role' => 'user', 'content' => 'Hello']]]);
    expect(aiTaskQueueMessages($queued))->toBe(1);

    // A second queue call while the message waits adds nothing.
    Mage::getModel('ai/task')->load($queued)->queue();
    expect(aiTaskQueueMessages($queued))->toBe(1);

    aiTaskQueueConfigure(false);
    $held = Mage::helper('ai')->submitTask(['consumer' => 'pest_task_queue', 'messages' => [['role' => 'user', 'content' => 'Hello']]]);
    expect(aiTaskQueueMessages($held))->toBe(0);
});

it('lets the cron job queue a pending task again when its message is gone', function (): void {
    aiTaskQueueConfigure(true);
    $taskId = Mage::helper('ai')->submitTask(['consumer' => 'pest_task_queue', 'messages' => [['role' => 'user', 'content' => 'Hello']]]);
    Mage::getSingleton('core/resource')->getConnection('core_write')
        ->delete(\Maho\Queue\QueueManager::tableName(), ['dedupe_key = ?' => Maho_Ai_Model_Task::queueKey($taskId)]);

    new Maho_Ai_Model_TaskRunner()->processQueue();

    expect(aiTaskQueueMessages($taskId))->toBe(1);
    expect(Mage::getModel('ai/task')->load($taskId)->getData('status'))->toBe(Maho_Ai_Model_Task::STATUS_PENDING);
});

it('queues an agent task even when the async queue is off', function (): void {
    aiTaskQueueConfigure(false);
    $task = Mage::getModel('ai/task')->setData([
        'consumer' => 'pest_task_queue',
        'action' => 'job',
        'task_type' => Maho_Ai_Model_Task::TYPE_AGENT,
        'status' => Maho_Ai_Model_Task::STATUS_PENDING,
        'max_retries' => 0,
    ]);
    $task->save();
    $task->queue();
    expect(aiTaskQueueMessages((int) $task->getId()))->toBe(1);
});

it('fails an agent task whose worker died, and tells its conversation, but never runs it again', function (): void {
    aiTaskQueueConfigure(false);
    $conversation = Mage::getModel('ai/conversation')->setData(['admin_user_id' => 1, 'store_id' => 0, 'title' => 'Pest lost run', 'status' => Maho_Ai_Model_Conversation::STATUS_RUNNING]);
    $conversation->save();
    $task = Mage::getModel('ai/task')->setData([
        'consumer' => 'pest_task_queue',
        'action' => 'job',
        'task_type' => Maho_Ai_Model_Task::TYPE_AGENT,
        'status' => Maho_Ai_Model_Task::STATUS_PROCESSING,
        'started_at' => Mage::app()->getLocale()->formatDateForDb('-10 minutes'),
        'max_retries' => 0,
        'conversation_id' => (int) $conversation->getId(),
    ]);
    $task->save();

    try {
        expect($conversation->isRunning())->toBeFalse();
        new Maho_Ai_Model_TaskRunner()->processQueue();

        $task = Mage::getModel('ai/task')->load((int) $task->getId());
        expect($task->getData('status'))->toBe(Maho_Ai_Model_Task::STATUS_FAILED);
        expect(aiTaskQueueMessages((int) $task->getId()))->toBe(0);
        $conversation = Mage::getModel('ai/conversation')->load((int) $conversation->getId());
        expect($conversation->getStatus())->toBe(Maho_Ai_Model_Conversation::STATUS_ACTIVE);
        expect($conversation->messagesCollection()->getLastItem()->getToolStatus())->toBe(Maho_Ai_Model_Conversation_Message::TOOL_ERROR);
    } finally {
        $conversation->delete();
    }
});

it('keeps the tokens of an agent run out of the daily usage, since the assistant records them per call', function (): void {
    $yesterday = Mage::app()->getLocale()->formatDateForDb('-1 day', withTime: false) . ' 12:00:00';
    foreach ([Maho_Ai_Model_Task::TYPE_COMPLETION, Maho_Ai_Model_Task::TYPE_AGENT] as $type) {
        Mage::getModel('ai/task')->setData([
            'consumer' => 'pest_task_queue',
            'action' => $type,
            'task_type' => $type,
            'status' => Maho_Ai_Model_Task::STATUS_COMPLETE,
            'platform' => 'pest',
            'model' => 'pest-model',
            'input_tokens' => 100,
            'output_tokens' => 10,
            'completed_at' => $yesterday,
        ])->save();
    }

    new Maho_Ai_Model_TaskRunner()->aggregateUsage();

    $connection = Mage::getSingleton('core/resource')->getConnection('core_read');
    $row = $connection->fetchRow(
        $connection->select()
            ->from(Mage::getSingleton('core/resource')->getTableName('ai/usage'), ['request_count', 'input_tokens'])
            ->where('consumer = ?', 'pest_task_queue'),
    );
    expect((int) $row['request_count'])->toBe(1);
    expect((int) $row['input_tokens'])->toBe(100);
});

it('finds the next run of a schedule in the time zone of the store, across a daylight saving change', function (): void {
    $store = Mage::app()->getStore(0);
    $timezone = $store->getConfig('general/locale/timezone');
    $store->setConfig('general/locale/timezone', 'Europe/Rome');
    try {
        $schedule = Mage::getModel('ai/task_schedule')->setStoreId(0);

        // Sunday noon UTC: the next Monday 9:30 in Rome is 7:30 UTC in summer time.
        $schedule->setCronExpr('30 9 * * 1');
        expect($schedule->computeNextRun('2026-10-04 12:00:00'))->toBe('2026-10-05 07:30:00');

        // Summer time ends on 25 October 2026: 9:30 in Rome is 8:30 UTC from that day.
        $schedule->setCronExpr('30 9 * * *');
        expect($schedule->computeNextRun('2026-10-24 12:00:00'))->toBe('2026-10-25 08:30:00');

        // The minute itself is in the past: the next run is the following day.
        expect($schedule->computeNextRun('2026-10-23 07:30:00'))->toBe('2026-10-24 07:30:00');

        // A day that never comes gives no next run.
        $schedule->setCronExpr('0 8 31 2 *');
        expect($schedule->computeNextRun('2026-10-04 12:00:00'))->toBeNull();
    } finally {
        $store->setConfig('general/locale/timezone', $timezone);
    }
});

it('refuses a cron expression that is not five readable fields', function (string $expr): void {
    expect(fn() => Maho_Ai_Model_Task_Schedule::validateCronExpr($expr))->toThrow(Mage_Core_Exception::class);
})->with(['0 8 * *', '61 * * * *', '0 8 * * * *', 'every day']);
