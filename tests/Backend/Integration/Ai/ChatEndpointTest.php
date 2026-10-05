<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Maho\Ai\Api\Agent\McpToolbox;
use Maho\ApiPlatform\Kernel;
use Maho\ApiPlatform\Security\SameOriginGuard;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\AI\Platform\Test\InMemoryPlatform;
use Symfony\AI\Platform\Tool\Tool;
use Symfony\Component\HttpFoundation\Request;
use Tests\MahoBackendTestCase;

uses(MahoBackendTestCase::class);

/*
|--------------------------------------------------------------------------
| Admin assistant chat endpoint
|--------------------------------------------------------------------------
|
| Drives /api/admin/ai/chat in-process with a scripted model. The script decides
| what the "model" answers on each call and records the tools it was offered, so
| the test observes the tool catalog, the in-process tool dispatch, the write
| confirmation round trip and the stored conversation.
|
*/

final class AiChatScript
{
    /** @var list<ResultInterface> */
    public static array $queue = [];

    /** @var list<list<string>> tool names offered on each model call */
    public static array $offeredTools = [];

    /** @var list<array<string, array<string, mixed>>> tool name => parameter schema, per model request */
    public static array $offeredSchemas = [];

    public static function reset(ResultInterface ...$results): void
    {
        self::$queue = array_values($results);
        self::$offeredTools = [];
        self::$offeredSchemas = [];
    }

    /** Thrown by the next model call, once. */
    public static ?\Throwable $throw = null;

    public static function answer(Model $model, array|string|object $input, array $options): ResultInterface
    {
        if (self::$throw !== null) {
            $throw = self::$throw;
            self::$throw = null;
            throw $throw;
        }
        self::$offeredTools[] = array_map(static fn(Tool $tool): string => $tool->getName(), $options['tools'] ?? []);
        $schemas = [];
        foreach ($options['tools'] ?? [] as $tool) {
            $schemas[$tool->getName()] = $tool->getParameters() ?? [];
        }
        self::$offeredSchemas[] = $schemas;

        return array_shift(self::$queue) ?? new TextResult('No scripted answer left.');
    }
}

final class AiChatTestProviderFactory implements Maho_Ai_Model_Platform_ProviderFactoryInterface
{
    #[\Override]
    public function create(?int $storeId = null): Maho_Ai_Model_Platform_ProviderInterface
    {
        return new Maho_Ai_Model_Platform_Symfony(
            new InMemoryPlatform(AiChatScript::answer(...)),
            'pest',
            'pest-model',
        );
    }
}

function aiChatConfigure(bool $enabled = true): void
{
    $config = Mage::getConfig();
    $config->setNode('global/ai/providers/pest/label', 'Pest');
    $config->setNode('global/ai/providers/pest/capabilities', 'chat');
    $config->setNode('global/ai/providers/pest/factory_class', AiChatTestProviderFactory::class);

    foreach (Mage::app()->getStores(true) as $store) {
        $store->setConfig('ai/general/enabled', $enabled ? '1' : '0');
        $store->setConfig('ai/chat/enabled', $enabled ? '1' : '0');
        $store->setConfig('ai/chat/platform', 'pest');
        $store->setConfig('ai/chat/model', 'pest-model');
        $store->setConfig('ai/chat/max_tool_calls', '5');
        $store->setConfig('ai/chat/history_messages', '40');
        $store->setConfig('ai/chat/tool_result_max_chars', '8000');
        $store->setConfig('ai/chat/excluded_sections', 'reports');
    }
}

/**
 * @param list<string> $aclResources
 */
function aiChatAdmin(#[\SensitiveParameter]
    string $username, array $aclResources): Mage_Admin_Model_User
{
    /** @var Mage_Admin_Model_Role $role */
    $role = Mage::getModel('admin/role');
    $role->setData([
        'role_name' => $username . '_role',
        'role_type' => Mage_Admin_Model_Acl::ROLE_TYPE_GROUP,
        'parent_id' => 0,
    ])->save();
    Mage::getModel('admin/rules')->setRoleId($role->getId())->setResources($aclResources)->saveRel();

    /** @var Mage_Admin_Model_User $user */
    $user = Mage::getModel('admin/user');
    $user->setData([
        'username' => $username,
        'firstname' => 'Pest',
        'lastname' => 'Assistant',
        'email' => $username . '@example.test',
        'password' => 'pest-assistant-password-1234',
        'is_active' => 1,
    ])->save();
    Mage::getModel('admin/user')->setRoleId($role->getId())->setUserId($user->getId())->add();

    return Mage::getModel('admin/user')->load($user->getId());
}

function aiChatLogin(Mage_Admin_Model_User $user): void
{
    $session = Mage::getSingleton('admin/session');
    $session->setUser($user);
    $session->refreshAcl($user);
}

function aiChatDeleteAdmin(Mage_Admin_Model_User $user): void
{
    // Log out first: the activity log observer cannot record the deletion of the admin
    // who performs it, and its failed insert rolls the delete back.
    $session = Mage::getSingleton('admin/session');
    $session->setUser(null);
    $session->setAcl(null);

    $role = $user->getRole();
    $user->delete();
    if ($role->getId()) {
        Mage::getModel('admin/role')->load($role->getId())->delete();
    }
}

/**
 * @param array<string, mixed> $payload
 * @return array{status: int, events: list<array{0: string, 1: array<string, mixed>}>, raw: string}
 */
function aiChatRequest(string $path, array $payload): array
{
    $payload['form_key'] = Mage::getSingleton('core/session')->getFormKey();
    $request = Request::create(
        $path,
        'POST',
        [],
        [],
        [],
        ['HTTP_ORIGIN' => SameOriginGuard::allowedOrigins()[0] ?? 'http://localhost', 'CONTENT_TYPE' => 'application/json'],
        (string) Mage::helper('core')->jsonEncode($payload),
    );

    $kernel = new Kernel('prod', false);
    $response = $kernel->handle($request);

    ob_start();
    Maho_Ai_Model_Chat_SseWriter::$keepBufferLevel = ob_get_level();
    try {
        $response->sendContent();
    } finally {
        $raw = (string) ob_get_clean();
        Maho_Ai_Model_Chat_SseWriter::$keepBufferLevel = 0;
    }

    $events = [];
    foreach (explode("\n\n", $raw) as $chunk) {
        $name = null;
        $data = [];
        foreach (explode("\n", $chunk) as $line) {
            if (str_starts_with($line, 'event:')) {
                $name = trim(substr($line, 6));
            } elseif (str_starts_with($line, 'data:')) {
                $data[] = ltrim(substr($line, 5), ' ');
            }
        }
        if ($name !== null) {
            $events[] = [$name, (array) json_decode(implode("\n", $data), true)];
        }
    }

    return ['status' => $response->getStatusCode(), 'events' => $events, 'raw' => $raw];
}

/**
 * @param list<array{0: string, 1: array<string, mixed>}> $events
 * @return list<array<string, mixed>>
 */
function aiChatEvents(array $events, string $name): array
{
    return array_values(array_map(
        static fn(array $event): array => $event[1],
        array_filter($events, static fn(array $event): bool => $event[0] === $name),
    ));
}

function aiChatDeleteConversations(int $adminUserId): void
{
    foreach (Mage::getResourceModel('ai/conversation_collection')->addFieldToFilter('admin_user_id', $adminUserId) as $conversation) {
        $conversation->delete();
    }
}

beforeEach(function (): void {
    if (!mcpPackagesInstalled()) {
        $this->markTestSkipped('MCP packages not installed');
    }
    aiChatConfigure();
    unset($_SERVER['MAHO_ADMIN_USER_ID'], $_SERVER['MAHO_IS_ADMIN'], $_SERVER['MAHO_API_BRIDGE_TOKEN'], $_SERVER['MAHO_STORE_ID'], $_SERVER['MAHO_ADMIN_USERNAME']);
});

it('refuses a request without an admin session', function (): void {
    $result = aiChatRequest('/api/admin/ai/chat', ['message' => 'hello']);

    expect($result['status'])->toBe(401);
});

it('answers 404 when the assistant is disabled', function (): void {
    $admin = aiChatAdmin('ai_chat_disabled', ['all']);
    try {
        aiChatLogin($admin);
        aiChatConfigure(enabled: false);

        $result = aiChatRequest('/api/admin/ai/chat', ['message' => 'hello']);

        expect($result['status'])->toBe(404);
    } finally {
        aiChatDeleteAdmin($admin);
    }
});

it('refuses an admin whose role does not grant the assistant', function (): void {
    $admin = aiChatAdmin('ai_chat_noacl', ['admin/catalog']);
    try {
        aiChatLogin($admin);

        $result = aiChatRequest('/api/admin/ai/chat', ['message' => 'hello']);

        expect($result['status'])->toBe(403);
    } finally {
        aiChatDeleteAdmin($admin);
    }
});

it('turns the API links of an answer into admin page links', function (): void {
    $admin = aiChatAdmin('ai_chat_linker', ['all']);
    try {
        aiChatLogin($admin);
        AiChatScript::reset(new TextResult('See [Blue Shirt](/api/rest/v2/products/12), [the page](/api/rest/v2/cms-pages/2) and [a store](/api/rest/v2/stores/1).'));

        $result = aiChatRequest('/api/admin/ai/chat', ['message' => 'Find the blue shirt']);

        expect($result['status'])->toBe(200);
        $replace = aiChatEvents($result['events'], 'replace');
        expect($replace)->toHaveCount(1);
        expect($replace[0]['text'])->toMatch('~\[Blue Shirt\]\(http://[^)]+/catalog_product/edit/id/12/[^)]*\)~');
        expect($replace[0]['text'])->toMatch('~\[the page\]\(http://[^)]+/cms_page/edit/page_id/2/[^)]*\)~');
        expect($replace[0]['text'])->toContain(' and a store.');
        $stored = Mage::getModel('ai/conversation_message')->getCollection()->addFieldToFilter('role', 'assistant')->setOrder('message_id', 'DESC')->getFirstItem();
        expect((string) $stored->getContent())->toContain('/catalog_product/edit/id/12/');
    } finally {
        aiChatDeleteConversations((int) $admin->getId());
        aiChatDeleteAdmin($admin);
    }
});

it('previews an update with the current values, and undoes it after the administrator confirmed it', function (): void {
    $admin = aiChatAdmin('ai_chat_undoer', ['all']);
    $page = Mage::getModel('cms/page')->setData(['identifier' => 'ai-chat-undo-page', 'title' => 'Before', 'content' => '<p>x</p>', 'is_active' => 1, 'stores' => [0], 'root_template' => 'one_column']);
    $page->save();
    $pageId = (int) $page->getId();
    try {
        aiChatLogin($admin);
        AiChatScript::reset(
            new ToolCallResult([new ToolCall('call_u', 'content_cms_pages_update', ['id' => (string) $pageId, 'title' => 'After', 'content' => '<p>x</p>'])]),
            new TextResult('Renamed.'),
        );
        $first = aiChatRequest('/api/admin/ai/chat', ['message' => 'Rename the page', 'context' => ['route' => 'cms_page/index']]);
        expect($first['status'])->toBe(200);
        $confirm = aiChatEvents($first['events'], 'confirm');
        expect($confirm)->toHaveCount(1);
        $preview = $confirm[0]['calls'][0]['preview'];
        expect($preview['kind'])->toBe('update');
        expect($preview['record'])->toBe('Before');
        expect($preview['scope'])->toBe('');
        expect($preview['changes'])->toBe([['field' => 'title', 'from' => 'Before', 'to' => 'After']]);
        expect($preview)->not->toHaveKey('undo');
        $conversationId = (int) $confirm[0]['conversation_id'];

        $approved = aiChatRequest('/api/admin/ai/chat/confirm', ['conversation_id' => $conversationId, 'decisions' => ['call_u' => true]]);
        expect($approved['status'])->toBe(200);
        $result = aiChatEvents($approved['events'], 'tool_result')[0];
        expect($result['ok'])->toBeTrue($approved['raw']);
        expect($result['undo'])->toBeInt();
        expect((string) Mage::getModel('cms/page')->load($pageId)->getTitle())->toBe('After');

        $undone = aiChatRequest('/api/admin/ai/chat/undo', ['conversation_id' => $conversationId, 'message_id' => $result['undo']]);
        expect($undone['status'])->toBe(200);
        $undoArguments = aiChatEvents($undone['events'], 'tool_call')[0]['arguments'];
        expect(array_keys($undoArguments))->toBe(['id', 'title']);
        expect((int) $undoArguments['id'])->toBe($pageId);
        expect($undoArguments['title'])->toBe('Before');
        expect(aiChatEvents($undone['events'], 'tool_result')[0]['ok'])->toBeTrue($undone['raw']);
        expect(aiChatEvents($undone['events'], 'done')[0]['state'])->toBe('complete');
        expect((string) Mage::getModel('cms/page')->load($pageId)->getTitle())->toBe('Before');

        // An undo is a write of its own and cannot be undone again.
        $again = aiChatRequest('/api/admin/ai/chat/undo', ['conversation_id' => $conversationId, 'message_id' => $result['undo']]);
        expect(aiChatEvents($again['events'], 'done')[0]['state'])->toBe('error');
    } finally {
        Mage::getModel('cms/page')->load($pageId)->delete();
        aiChatDeleteConversations((int) $admin->getId());
        aiChatDeleteAdmin($admin);
    }
});

it('keeps a note the model stores with the remember tool and lists it in the next prompt', function (): void {
    $admin = aiChatAdmin('ai_chat_rememberer', ['all']);
    try {
        aiChatLogin($admin);
        AiChatScript::reset(
            new ToolCallResult([new ToolCall('call_m', 'remember', ['note' => 'Answer in Italian.'])]),
            new TextResult('Noted.'),
        );
        $first = aiChatRequest('/api/admin/ai/chat', ['message' => 'Always answer me in Italian']);
        expect($first['status'])->toBe(200);
        expect(AiChatScript::$offeredTools[0])->toContain('remember', 'forget');
        $result = aiChatEvents($first['events'], 'tool_result')[0];
        expect($result['ok'])->toBeTrue($first['raw']);
        $notes = Maho_Ai_Model_Memory::notesOf((int) $admin->getId());
        expect($notes)->toHaveCount(1);
        expect($notes[0]['note'])->toBe('Answer in Italian.');

        $prompt = new Maho_Ai_Model_Chat_SystemPrompt()->build($admin);
        expect($prompt)->toContain($notes[0]['id'] . '. Answer in Italian.');

        AiChatScript::reset(
            new ToolCallResult([new ToolCall('call_f', 'forget', ['note_id' => $notes[0]['id']])]),
            new TextResult('Forgotten.'),
        );
        $second = aiChatRequest('/api/admin/ai/chat', ['message' => 'Forget that', 'conversation_id' => (int) aiChatEvents($first['events'], 'done')[0]['conversation_id']]);
        expect(aiChatEvents($second['events'], 'tool_result')[0]['ok'])->toBeTrue($second['raw']);
        expect(Maho_Ai_Model_Memory::notesOf((int) $admin->getId()))->toBe([]);
    } finally {
        foreach (Mage::getResourceModel('ai/memory_collection')->addFieldToFilter('admin_user_id', (int) $admin->getId()) as $memory) {
            $memory->delete();
        }
        aiChatDeleteConversations((int) $admin->getId());
        aiChatDeleteAdmin($admin);
    }
});

it('starts a background job after confirmation and runs it in a worker with its writes approved in advance', function (): void {
    $admin = aiChatAdmin('ai_chat_background', ['all']);
    $page = Mage::getModel('cms/page')->setData(['identifier' => 'ai-chat-background-page', 'title' => 'Old', 'content' => '<p>x</p>', 'is_active' => 1, 'stores' => [0], 'root_template' => 'one_column']);
    $page->save();
    $pageId = (int) $page->getId();
    $queue = Mage::getSingleton('core/resource')->getConnection('core_write');
    $queueTable = Mage::getSingleton('core/resource')->getTableName('queue/message');
    $before = (int) $queue->fetchOne("SELECT COUNT(*) FROM {$queueTable}");
    $jobId = null;
    try {
        aiChatLogin($admin);
        AiChatScript::reset(
            new ToolCallResult([new ToolCall('call_b', 'run_in_background', ['title' => 'Rename pages', 'instruction' => 'Rename the page ' . $pageId . ' to New.'])]),
            new TextResult('Started.'),
        );
        $first = aiChatRequest('/api/admin/ai/chat', ['message' => 'Rename all the pages, in the background']);
        expect($first['status'])->toBe(200);
        expect(AiChatScript::$offeredTools[0])->toContain('run_in_background');
        $confirm = aiChatEvents($first['events'], 'confirm');
        expect($confirm)->toHaveCount(1);
        expect($confirm[0]['calls'][0]['name'])->toBe('run_in_background');
        $conversationId = (int) $confirm[0]['conversation_id'];

        $approved = aiChatRequest('/api/admin/ai/chat/confirm', ['conversation_id' => $conversationId, 'decisions' => ['call_b' => true]]);
        $result = aiChatEvents($approved['events'], 'tool_result')[0];
        expect($result['ok'])->toBeTrue($approved['raw']);
        $job = Mage::getResourceModel('ai/conversation_collection')->addFieldToFilter('admin_user_id', (int) $admin->getId())->addFieldToFilter('status', Maho_Ai_Model_Conversation::STATUS_RUNNING)->getFirstItem();
        $jobId = (int) $job->getId();
        expect($jobId)->toBeGreaterThan(0);
        expect((string) $job->getTitle())->toBe('Rename pages');
        expect((int) $queue->fetchOne("SELECT COUNT(*) FROM {$queueTable}"))->toBe($before + 1);
        $task = $job->latestTask();
        expect($task)->not->toBeNull();
        expect($task->getTaskType())->toBe(Maho_Ai_Model_Task::TYPE_AGENT);
        expect($task->getContextArray()['mode'])->toBe('job');
        expect((int) $task->getData('max_retries'))->toBe(0);
        expect($task->isQueued())->toBeTrue();
        expect($job->isRunning())->toBeTrue();
        expect((string) $job->messagesCollection()->getFirstItem()->getContent())->toBe('Rename the page ' . $pageId . ' to New.');

        // A lost queue message: the job can never run, and the panel must not wait forever.
        $lost = Mage::getModel('ai/conversation')->setData(['admin_user_id' => (int) $admin->getId(), 'store_id' => 0, 'status' => Maho_Ai_Model_Conversation::STATUS_RUNNING, 'title' => 'Lost job']);
        $lost->save();
        expect($lost->isRunning())->toBeFalse();
        $lost->reconcileBackgroundJob();
        $lost = Mage::getModel('ai/conversation')->load((int) $lost->getId());
        expect($lost->getStatus())->toBe(Maho_Ai_Model_Conversation::STATUS_ACTIVE);
        expect($lost->messagesCollection()->getLastItem()->getToolStatus())->toBe(Maho_Ai_Model_Conversation_Message::TOOL_ERROR);
        $job->reconcileBackgroundJob();
        expect(Mage::getModel('ai/conversation')->load($jobId)->getStatus())->toBe(Maho_Ai_Model_Conversation::STATUS_RUNNING);

        // The worker: the handler runs the job with the scripted model; the update needs no confirmation.
        AiChatScript::reset(
            new ToolCallResult([new ToolCall('call_w', 'content_cms_pages_update', ['id' => (string) $pageId, 'title' => 'New'])]),
            new TextResult('Renamed one page.'),
        );
        new Maho_Ai_Model_TaskRunner()->processTask((int) $task->getId());

        expect(AiChatScript::$offeredTools[0])->toContain('content_cms_pages_update');
        expect(AiChatScript::$offeredTools[0])->not->toContain('run_in_background', 'admin_open_page');
        expect((string) Mage::getModel('cms/page')->load($pageId)->getTitle())->toBe('New');
        $job = Mage::getModel('ai/conversation')->load($jobId);
        expect($job->getStatus())->toBe(Maho_Ai_Model_Conversation::STATUS_ACTIVE);
        $rows = [];
        foreach ($job->messagesCollection() as $message) {
            $rows[] = [$message->getRole(), $message->getToolStatus(), (bool) $message->getIsWrite(), (string) $message->getContent()];
        }
        expect($rows[0])->toBe(['user', null, false, 'Rename the page ' . $pageId . ' to New.']);
        expect(array_filter($rows, static fn(array $r): bool => $r[0] === 'tool'))->toHaveCount(1);
        $toolRow = array_values(array_filter($rows, static fn(array $r): bool => $r[0] === 'tool'))[0];
        expect($toolRow[1])->toBe('done');
        expect($toolRow[2])->toBeTrue();
        expect(end($rows)[3])->toBe('Renamed one page.');
        $task = Mage::getModel('ai/task')->load((int) $task->getId());
        expect($task->getData('status'))->toBe(Maho_Ai_Model_Task::STATUS_COMPLETE, (string) $task->getData('error_message'));
        expect((string) $task->getData('response'))->toBe('Renamed one page.');

        // A second runner finds the task taken and leaves it alone.
        new Maho_Ai_Model_TaskRunner()->processTask((int) $task->getId());
        expect(Mage::getModel('cms/page')->load($pageId)->getTitle())->toBe('New');
    } finally {
        $queue->delete($queueTable, ['dedupe_key LIKE ?' => 'ai_task_%']);
        Mage::getSingleton('core/resource')->getConnection('core_write')->delete(
            Mage::getSingleton('core/resource')->getTableName('ai/task'),
            ['admin_user_id = ?' => (int) $admin->getId()],
        );
        Mage::getModel('cms/page')->load($pageId)->delete();
        aiChatLogin($admin);
        aiChatDeleteConversations((int) $admin->getId());
        aiChatDeleteAdmin($admin);
    }
});

it('creates a scheduled task through its API tool, and a scheduled run proposes its write and notifies its audience', function (): void {
    $admin = aiChatAdmin('ai_chat_schedule', ['all']);
    $page = Mage::getModel('cms/page')->setData(['identifier' => 'ai-chat-schedule-page', 'title' => 'Old', 'content' => '<p>x</p>', 'is_active' => 1, 'stores' => [0], 'root_template' => 'one_column']);
    $page->save();
    $pageId = (int) $page->getId();
    $resource = Mage::getSingleton('core/resource');
    $connection = $resource->getConnection('core_write');
    $inbox = $resource->getTableName('adminnotification/inbox');
    try {
        aiChatLogin($admin);
        AiChatScript::reset(
            new ToolCallResult([new ToolCall('call_s', 'system_scheduled_tasks_create', ['title' => 'Page check', 'instruction' => 'Check the title of page ' . $pageId . '.', 'cronExpr' => '0 8 * * *', 'notify' => 'admin/cms/page'])]),
            new TextResult('Scheduled.'),
        );
        $first = aiChatRequest('/api/admin/ai/chat', ['message' => 'Check the page title every morning']);
        $confirm = aiChatEvents($first['events'], 'confirm');
        expect($confirm[0]['calls'][0]['name'] ?? null)->toBe('system_scheduled_tasks_create', $first['raw']);
        $approved = aiChatRequest('/api/admin/ai/chat/confirm', ['conversation_id' => (int) $confirm[0]['conversation_id'], 'decisions' => ['call_s' => true]]);
        expect(aiChatEvents($approved['events'], 'tool_result')[0]['ok'])->toBeTrue($approved['raw']);

        /** @var Maho_Ai_Model_Task_Schedule $schedule */
        $schedule = Mage::getModel('ai/task_schedule')->getCollection()->addFieldToFilter('admin_user_id', (int) $admin->getId())->getFirstItem();
        expect($schedule->getCronExpr())->toBe('0 8 * * *');
        expect($schedule->getNotify())->toBe('cms/page');
        expect($schedule->getNextRunAt())->not->toBeNull();

        // The minute comes: the dispatcher queues one run and moves the schedule to its next minute.
        $schedule->setNextRunAt(Mage::app()->getLocale()->formatDateForDb('-1 minute'))->save();
        new Maho_Ai_Model_Task_Scheduler()->runDueSchedules();
        $schedule = Mage::getModel('ai/task_schedule')->load((int) $schedule->getId());
        expect($schedule->getNextRunAt())->toBeGreaterThan(Mage::app()->getLocale()->formatDateForDb('now'));
        $task = Mage::getModel('ai/task')->load((int) $schedule->getLastTaskId());
        expect($task->getContextArray())->toBe(['mode' => 'schedule', 'schedule_id' => (int) $schedule->getId()]);
        expect($task->isQueued())->toBeTrue();

        // The run: the notification goes out at once, the write waits for the administrator.
        AiChatScript::reset(
            new ToolCallResult([
                new ToolCall('call_n', 'notify', ['title' => 'The page title is old', 'text' => 'Page ' . $pageId . ' is still called Old.', 'severity' => 'major']),
                new ToolCall('call_u', 'content_cms_pages_update', ['id' => (string) $pageId, 'title' => 'New']),
            ]),
        );
        new Maho_Ai_Model_TaskRunner()->processTask((int) $task->getId());

        expect(AiChatScript::$offeredTools[0])->toContain('notify');
        expect(AiChatScript::$offeredTools[0])->not->toContain('admin_open_page', 'run_in_background');
        expect((string) Mage::getModel('cms/page')->load($pageId)->getTitle())->toBe('Old');
        $task = Mage::getModel('ai/task')->load((int) $task->getId());
        expect($task->getData('status'))->toBe(Maho_Ai_Model_Task::STATUS_COMPLETE, (string) $task->getData('error_message'));
        $run = Mage::getModel('ai/conversation')->load((int) $task->getConversationId());
        expect($run->getPendingWrites())->toHaveCount(1);
        expect($run->getStatus())->toBe(Maho_Ai_Model_Conversation::STATUS_ACTIVE);

        $rows = $connection->fetchAll($connection->select()->from($inbox, ['title', 'admin_user_id', 'acl_resource', 'url'])->where('url LIKE ?', '%/ai_chat/open/id/' . (int) $run->getId() . '/%'));
        $byTitle = array_column($rows, null, 'title');
        expect($byTitle['The page title is old']['acl_resource'] ?? null)->toBe('cms/page');
        expect($byTitle['The page title is old']['admin_user_id'])->toBeNull();
        expect((int) ($byTitle['The assistant task "Page check" waits for your confirmation']['admin_user_id'] ?? 0))->toBe((int) $admin->getId());
        // The worker runs in a frontend store: its code must not end up in the admin link.
        expect($byTitle['The page title is old']['url'])->not->toContain('/' . Mage::app()->getDefaultStoreView()->getCode() . '/');

        // A run whose write still waits blocks the next one: it would propose the same again.
        $schedule->setNextRunAt(Mage::app()->getLocale()->formatDateForDb('-1 minute'))->save();
        new Maho_Ai_Model_Task_Scheduler()->runDueSchedules();
        expect((int) Mage::getModel('ai/task_schedule')->load((int) $schedule->getId())->getLastTaskId())->toBe((int) $task->getId());
    } finally {
        $connection->delete(\Maho\Queue\QueueManager::tableName(), ['dedupe_key LIKE ?' => 'ai_task_%']);
        $connection->delete($resource->getTableName('ai/task'), ['admin_user_id = ?' => (int) $admin->getId()]);
        $connection->delete($resource->getTableName('ai/task_schedule'), ['admin_user_id = ?' => (int) $admin->getId()]);
        $connection->delete($inbox, ['url LIKE ?' => '%/ai_chat/open/id/%']);
        Mage::getModel('cms/page')->load($pageId)->delete();
        aiChatLogin($admin);
        aiChatDeleteConversations((int) $admin->getId());
        aiChatDeleteAdmin($admin);
    }
});

it('refuses a schedule that runs more than once an hour, or notifies a resource the administrator lacks', function (): void {
    $admin = aiChatAdmin('ai_chat_schedule_limits', ['admin/system/ai/chat', 'admin/cms/page']);
    try {
        aiChatLogin($admin);
        $create = static fn(string $cron, string $notify): Closure => static fn(): Maho_Ai_Model_Task_Schedule => Mage::getModel('ai/task_schedule')
            ->setTitle('Check')->setInstruction('Check the pages.')->setCronExpr($cron)->setNotify($notify)
            ->validateFor($admin);
        expect($create('*/5 * * * *', 'self'))->toThrow(Mage_Core_Exception::class, 'at most once an hour');
        expect($create('0 8 * * *', 'sales/order'))->toThrow(Mage_Core_Exception::class, 'not an ACL resource');
        $schedule = $create('0 8 * * *', 'admin/cms/page')();
        expect($schedule->getAdminUserId())->toBe((int) $admin->getId());
        expect($schedule->getNotify())->toBe('cms/page');
        expect($schedule->notifyAclResource())->toBe('cms/page');
        expect($schedule->notifyAdminUserId())->toBeNull();
    } finally {
        Mage::getSingleton('core/resource')->getConnection('core_write')->delete(
            Mage::getSingleton('core/resource')->getTableName('ai/task_schedule'),
            ['admin_user_id = ?' => (int) $admin->getId()],
        );
        aiChatDeleteAdmin($admin);
    }
});

/**
 * @return array{status: int, json: array<string, mixed>}
 */
function aiChatUpload(string $name, string $content, string $mime): array
{
    $tmp = tempnam(sys_get_temp_dir(), 'aichat');
    file_put_contents($tmp, $content);
    $request = Request::create(
        '/api/admin/ai/chat/upload',
        'POST',
        ['form_key' => Mage::getSingleton('core/session')->getFormKey()],
        [],
        ['file' => new Symfony\Component\HttpFoundation\File\UploadedFile($tmp, $name, $mime, null, true)],
        ['HTTP_ORIGIN' => SameOriginGuard::allowedOrigins()[0] ?? 'http://localhost'],
    );
    $response = new Kernel('prod', false)->handle($request);
    $json = json_decode((string) $response->getContent(), true);

    return ['status' => $response->getStatusCode(), 'json' => is_array($json) ? $json : []];
}

it('attaches a CSV to a message, lets the model read it, and shows an attached image to the model', function (): void {
    $admin = aiChatAdmin('ai_chat_attacher', ['all']);
    try {
        aiChatLogin($admin);
        $csv = aiChatUpload('stock.csv', "sku,qty\nA-1,5\nA-2,0\n", 'text/csv');
        expect($csv['status'])->toBe(200);
        expect($csv['json']['name'])->toBe('stock.csv');
        expect($csv['json']['mime'])->toBe('text/csv');
        expect(aiChatUpload('run.exe', 'MZ', 'application/octet-stream')['status'])->toBe(400);
        expect(aiChatUpload('fake.png', 'not an image', 'image/png')['status'])->toBe(400);
        $png = aiChatUpload('dot.png', (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==', true), 'image/png');
        expect($png['status'])->toBe(200);

        AiChatScript::reset(
            new ToolCallResult([new ToolCall('call_r', 'attachment_read', ['id' => $csv['json']['id']])]),
            new TextResult('Two SKUs, one out of stock.'),
        );
        $result = aiChatRequest('/api/admin/ai/chat', ['message' => 'Which of these are out of stock?', 'attachments' => [$csv['json']['id'], $png['json']['id']]]);
        expect($result['status'])->toBe(200);
        expect(AiChatScript::$offeredTools[0])->toContain('attachment_read');
        $read = aiChatEvents($result['events'], 'tool_result')[0];
        expect($read['ok'])->toBeTrue($result['raw']);
        expect($read['preview'])->toContain('stock.csv')->toContain('A-2,0');

        $conversation = Mage::getModel('ai/conversation')->load((int) aiChatEvents($result['events'], 'done')[0]['conversation_id']);
        $user = $conversation->messagesCollection()->getFirstItem();
        expect((string) $user->getContent())->toContain('Attachments:')->toContain('stock.csv (text/csv')->toContain('dot.png (image/png');
        expect(array_column($user->getAttachments(), 'name'))->toBe(['stock.csv', 'dot.png']);

        $bag = new Maho_Ai_Model_Chat_MessageBagBuilder()->build($conversation, 40);
        $userMessage = $bag->getMessages()[0];
        expect($userMessage)->toBeInstanceOf(Symfony\AI\Platform\Message\UserMessage::class);
        $images = array_filter($userMessage->getContent(), static fn($part): bool => $part instanceof Symfony\AI\Platform\Message\Content\Image);
        expect($images)->toHaveCount(1);

        // A sent file stays when the panel asks to remove it; a file that was never sent goes.
        $spare = aiChatUpload('spare.txt', 'unused', 'text/plain');
        expect(json_decode(aiChatRequest('/api/admin/ai/chat/upload/remove', ['id' => $csv['json']['id']])['raw'], true)['removed'] ?? null)->toBeFalse();
        expect(json_decode(aiChatRequest('/api/admin/ai/chat/upload/remove', ['id' => $spare['json']['id']])['raw'], true)['removed'] ?? null)->toBeTrue();
        expect(Maho_Ai_Model_Chat_Attachment::path((int) $admin->getId(), $csv['json']['id']))->not->toBeNull();
        expect(Maho_Ai_Model_Chat_Attachment::path((int) $admin->getId(), $spare['json']['id']))->toBeNull();

        // The cron keeps a recent file and removes an old one.
        $old = aiChatUpload('old.txt', 'old', 'text/plain');
        touch((string) Maho_Ai_Model_Chat_Attachment::path((int) $admin->getId(), $old['json']['id']), time() - 31 * 86400);
        expect(Maho_Ai_Model_Chat_Attachment::purgeOlderThan(Maho_Ai_Model_Chat_Attachment::KEEP_DAYS))->toBeGreaterThanOrEqual(1);
        expect(Maho_Ai_Model_Chat_Attachment::path((int) $admin->getId(), $old['json']['id']))->toBeNull();
        expect(Maho_Ai_Model_Chat_Attachment::path((int) $admin->getId(), $csv['json']['id']))->not->toBeNull();

        // Another administrator cannot read this administrator's file.
        $other = aiChatAdmin('ai_chat_attacher_other', ['all']);
        try {
            aiChatLogin($other);
            AiChatScript::reset(new ToolCallResult([new ToolCall('call_x', 'attachment_read', ['id' => $csv['json']['id']])]), new TextResult('.'));
            $stolen = aiChatRequest('/api/admin/ai/chat', ['message' => 'read it']);
            expect(aiChatEvents($stolen['events'], 'tool_result')[0]['ok'])->toBeFalse();
        } finally {
            aiChatDeleteConversations((int) $other->getId());
            aiChatDeleteAdmin($other);
        }

        // Deleting the conversation removes its files.
        aiChatLogin($admin);
        aiChatDeleteConversations((int) $admin->getId());
        expect(Maho_Ai_Model_Chat_Attachment::path((int) $admin->getId(), $csv['json']['id']))->toBeNull();
        expect(Maho_Ai_Model_Chat_Attachment::path((int) $admin->getId(), $png['json']['id']))->toBeNull();
    } finally {
        aiChatLogin($admin);
        foreach (glob(Mage::getBaseDir('var') . '/ai/attachments/' . (int) $admin->getId() . '/*') ?: [] as $file) {
            unlink($file);
        }
        @rmdir(Mage::getBaseDir('var') . '/ai/attachments/' . (int) $admin->getId());
        aiChatDeleteConversations((int) $admin->getId());
        aiChatDeleteAdmin($admin);
    }
});

it('runs a read tool at once, streams the answer and stores the conversation', function (): void {
    $admin = aiChatAdmin('ai_chat_reader', ['all']);
    try {
        aiChatLogin($admin);
        AiChatScript::reset(
            new ToolCallResult([new ToolCall('call_1', 'catalog_products_list', ['itemsPerPage' => 1])]),
            new TextResult('The catalog has products.'),
        );

        $result = aiChatRequest('/api/admin/ai/chat', [
            'message' => 'How many products are there?',
            'context' => ['route' => 'catalog_product/index'],
        ]);

        expect($result['status'])->toBe(200);
        $toolCalls = aiChatEvents($result['events'], 'tool_call');
        expect($toolCalls)->toHaveCount(1);
        expect($toolCalls[0]['name'])->toBe('catalog_products_list');
        expect($toolCalls[0]['read_only'])->toBeTrue();
        $toolResults = aiChatEvents($result['events'], 'tool_result');
        expect($toolResults)->toHaveCount(1);
        expect($toolResults[0]['ok'])->toBeTrue($result['raw']);
        expect(implode('', array_column(aiChatEvents($result['events'], 'delta'), 'text')))->toBe('The catalog has products.');
        $done = aiChatEvents($result['events'], 'done');
        expect($done)->toHaveCount(1);
        expect($done[0]['state'])->toBe('complete');

        // The catalog page loads the catalog section; other sections wait for enable_tools.
        expect(AiChatScript::$offeredTools[0])->toContain('catalog_products_list', 'enable_tools', 'admin_open_page');
        expect(AiChatScript::$offeredTools[0])->not->toContain('content_widget_types_list');
        expect(count(AiChatScript::$offeredTools[0]))->toBeLessThanOrEqual(McpToolbox::MAX_TOOLS);
        expect(array_filter(AiChatScript::$offeredTools[0], static fn(string $n): bool => str_starts_with($n, 'reports_')))->toBe([]);
        foreach (AiChatScript::$offeredTools[0] as $name) {
            expect(strlen($name))->toBeLessThanOrEqual(64);
        }
        // A property with an EnumSource and the store argument reach the model as closed lists.
        expect(AiChatScript::$offeredSchemas[0]['catalog_products_list']['properties']['store']['enum'])->toContain(Mage::app()->getDefaultStoreView()->getCode());
        // Gemini refuses a schema keyword outside its subset, such as writeOnly or format.
        $allowed = ['type', 'description', 'enum', 'properties', 'required', 'items', 'anyOf', 'oneOf', 'minimum', 'maximum', 'minLength', 'maxLength', 'minItems', 'maxItems', 'additionalProperties'];
        $check = function (array $schema, string $path) use (&$check, $allowed): void {
            foreach (array_keys($schema) as $key) {
                expect(in_array((string) $key, $allowed, true))->toBeTrue("Unsupported schema keyword \"$key\" at $path");
            }
            foreach ($schema['properties'] ?? [] as $name => $child) {
                $check($child, "$path.$name");
            }
            foreach (['items', 'additionalProperties'] as $one) {
                if (is_array($schema[$one] ?? null)) {
                    $check($schema[$one], "$path.$one");
                }
            }
            foreach (['anyOf', 'oneOf'] as $many) {
                foreach ($schema[$many] ?? [] as $child) {
                    $check($child, "$path.$many");
                }
            }
        };
        foreach (AiChatScript::$offeredSchemas[0] ?? [] as $name => $schema) {
            $check($schema, $name);
        }

        /** @var Maho_Ai_Model_Conversation $conversation */
        $conversation = Mage::getModel('ai/conversation')->load((int) $done[0]['conversation_id']);
        expect($conversation->getAdminUserId())->toBe((int) $admin->getId());
        expect($conversation->getTitle())->toBe('How many products are there?');
        expect($conversation->getContextRoute())->toBe('catalog_product/index');
        $roles = array_map(static fn($m) => $m->getRole(), array_values($conversation->messagesCollection()->getItems()));
        expect($roles)->toBe(['user', 'assistant', 'tool', 'assistant']);
        expect($conversation->getLockedUntil())->toBeNull();
    } finally {
        aiChatDeleteConversations((int) $admin->getId());
        aiChatDeleteAdmin($admin);
    }
});

it('finishes the turn and keeps the answer when the browser closes the stream', function (): void {
    $admin = aiChatAdmin('ai_chat_stopper', ['all']);
    try {
        aiChatLogin($admin);
        AiChatScript::reset(new TextResult('An answer the administrator did not wait for.'));
        Maho_Ai_Model_Chat_SseWriter::$simulateClientGone = true;
        try {
            $left = aiChatRequest('/api/admin/ai/chat', ['message' => 'tell me everything']);
        } finally {
            Maho_Ai_Model_Chat_SseWriter::$simulateClientGone = false;
        }
        expect($left['status'])->toBe(200);
        // Nothing after the first flush reaches a browser that left, but the turn ran to its end.
        expect(aiChatEvents($left['events'], 'done'))->toBe([]);

        $conversation = Mage::getModel('ai/conversation')->getCollection()->addFieldToFilter('admin_user_id', (int) $admin->getId())->getFirstItem();
        expect($conversation->getId())->not->toBeNull();
        expect($conversation->isRunning())->toBeFalse();
        $texts = array_map(static fn($m): string => (string) $m->getContent(), array_values(array_filter($conversation->messagesCollection()->getItems(), static fn($m): bool => $m->getRole() === 'assistant')));
        expect($texts)->toBe(['An answer the administrator did not wait for.']);

        AiChatScript::reset(new TextResult('Next answer.'));
        $next = aiChatRequest('/api/admin/ai/chat', ['message' => 'and now?', 'conversation_id' => (int) $conversation->getId()]);
        expect($next['status'])->toBe(200);
        expect(aiChatEvents($next['events'], 'done')[0]['state'])->toBe('complete');
    } finally {
        aiChatDeleteConversations((int) $admin->getId());
        aiChatDeleteAdmin($admin);
    }
});

it('ends the turn on Stop and leaves a note that the history shows', function (): void {
    $admin = aiChatAdmin('ai_chat_stop_note', ['all']);
    try {
        aiChatLogin($admin);
        AiChatScript::reset(new TextResult('First answer.'));
        $first = aiChatRequest('/api/admin/ai/chat', ['message' => 'hello']);
        $id = (int) aiChatEvents($first['events'], 'start')[0]['conversation_id'];
        expect($id)->toBeGreaterThan(0);

        $stopped = aiChatRequest('/api/admin/ai/chat/stop', ['conversation_id' => $id]);
        expect($stopped['status'])->toBe(200);
        AiChatScript::reset(new TextResult('Never shown.'));
        $turn = aiChatRequest('/api/admin/ai/chat', ['message' => 'go on', 'conversation_id' => $id]);
        expect($turn['status'])->toBe(200);
        expect(aiChatEvents($turn['events'], 'done'))->toBe([]);

        $conversation = Mage::getModel('ai/conversation')->load($id);
        expect($conversation->isRunning())->toBeFalse();
        $last = $conversation->messagesCollection()->getLastItem();
        expect($last->getRole())->toBe('assistant');
        expect($last->getToolStatus())->toBe(Maho_Ai_Model_Conversation_Message::TOOL_CANCELLED);
        expect((string) $last->getContent())->toBe('Stopped.');
        // The note is for the administrator: the model never sees it.
        $bag = new Maho_Ai_Model_Chat_MessageBagBuilder()->build($conversation, 40);
        $contents = array_map(static fn($m) => $m instanceof Symfony\AI\Platform\Message\AssistantMessage ? $m->getContent() : null, $bag->getMessages());
        expect($contents)->not->toContain('Stopped.');
    } finally {
        aiChatDeleteConversations((int) $admin->getId());
        aiChatDeleteAdmin($admin);
    }
});

it('opens the record page of an action the controller inherits without a route attribute', function (): void {
    $admin = aiChatAdmin('ai_chat_invoice_page', ['all']);
    try {
        aiChatLogin($admin);
        AiChatScript::reset(
            new ToolCallResult([new ToolCall('call_1', 'admin_open_page', ['page' => 'sales/invoice', 'record_id' => '274'])]),
            new TextResult('Opening the invoice.'),
        );
        $result = aiChatRequest('/api/admin/ai/chat', ['message' => 'open the invoice']);
        expect($result['status'])->toBe(200);
        expect(aiChatEvents($result['events'], 'tool_result')[0]['ok'])->toBeTrue($result['raw']);
        $navigate = aiChatEvents($result['events'], 'navigate');
        expect($navigate)->toHaveCount(1);
        expect($navigate[0]['url'])->toContain('/sales_invoice/view/invoice_id/274/');
    } finally {
        aiChatDeleteConversations((int) $admin->getId());
        aiChatDeleteAdmin($admin);
    }
});

it('leaves the Stop note when Stop lands during a tool round', function (string $event): void {
    $admin = aiChatAdmin('ai_chat_stop_round', ['all']);
    try {
        aiChatLogin($admin);
        AiChatScript::reset(
            new ToolCallResult([new ToolCall('call_s', 'catalog_products_list', ['itemsPerPage' => 1])]),
            new TextResult('Never shown.'),
        );
        Mage::setIsDeveloperMode(true);
        Maho_Ai_Model_Chat_SseWriter::$simulateStopOnEvent = $event;
        try {
            $turn = aiChatRequest('/api/admin/ai/chat', ['message' => 'list one']);
        } finally {
            Maho_Ai_Model_Chat_SseWriter::$simulateStopOnEvent = null;
            Mage::setIsDeveloperMode(false);
        }
        expect($turn['status'])->toBe(200);
        $conversation = Mage::getModel('ai/conversation')->getCollection()->addFieldToFilter('admin_user_id', (int) $admin->getId())->getFirstItem();
        $last = $conversation->messagesCollection()->getLastItem();
        expect((string) $last->getContent())->toBe('Stopped.');
        expect($last->getToolStatus())->toBe(Maho_Ai_Model_Conversation_Message::TOOL_CANCELLED);
    } finally {
        aiChatDeleteConversations((int) $admin->getId());
        aiChatDeleteAdmin($admin);
    }
})->with(['tool_call', 'tool_result', 'delta']);

it('stores the error of a failed turn in the history and reports a locked conversation as running', function (): void {
    $admin = aiChatAdmin('ai_chat_failer', ['all']);
    try {
        aiChatLogin($admin);
        AiChatScript::$throw = new RuntimeException('The provider exploded.');
        $turn = aiChatRequest('/api/admin/ai/chat', ['message' => 'hello']);
        expect($turn['status'])->toBe(200);
        expect(aiChatEvents($turn['events'], 'done')[0]['state'])->toBe('error');

        $conversation = Mage::getModel('ai/conversation')->getCollection()->addFieldToFilter('admin_user_id', (int) $admin->getId())->getFirstItem();
        $last = $conversation->messagesCollection()->getLastItem();
        expect($last->getToolStatus())->toBe(Maho_Ai_Model_Conversation_Message::TOOL_ERROR);
        expect((string) $last->getContent())->not->toBe('');
        expect((string) $last->getContent())->toBe(aiChatEvents($turn['events'], 'error')[0]['message']);

        expect($conversation->isRunning())->toBeFalse();
        expect($conversation->acquireLock())->toBeTrue();
        expect(Mage::getModel('ai/conversation')->load((int) $conversation->getId())->isRunning())->toBeTrue();
        $conversation->releaseLock();
        expect(Mage::getModel('ai/conversation')->load((int) $conversation->getId())->isRunning())->toBeFalse();
    } finally {
        AiChatScript::$throw = null;
        aiChatDeleteConversations((int) $admin->getId());
        aiChatDeleteAdmin($admin);
    }
});

it('runs a tool call that the stream reported twice only once', function (): void {
    $admin = aiChatAdmin('ai_chat_dedupe', ['all']);
    try {
        aiChatLogin($admin);
        $call = new ToolCall('call_dup', 'catalog_products_list', ['itemsPerPage' => 1]);
        AiChatScript::reset(
            new ToolCallResult([$call, new ToolCall('call_dup', 'catalog_products_list', ['itemsPerPage' => 1])]),
            new TextResult('Done.'),
        );

        $result = aiChatRequest('/api/admin/ai/chat', ['message' => 'List one product']);

        expect(aiChatEvents($result['events'], 'tool_call'))->toHaveCount(1);
        expect(aiChatEvents($result['events'], 'tool_result'))->toHaveCount(1);
        $done = aiChatEvents($result['events'], 'done');
        expect($done[0]['state'])->toBe('complete');

        /** @var Maho_Ai_Model_Conversation $conversation */
        $conversation = Mage::getModel('ai/conversation')->load((int) $done[0]['conversation_id']);
        $messages = array_values($conversation->messagesCollection()->getItems());
        expect(array_map(static fn($m) => $m->getRole(), $messages))->toBe(['user', 'assistant', 'tool', 'assistant']);
        expect($messages[1]->getToolCalls())->toHaveCount(1);
    } finally {
        aiChatDeleteConversations((int) $admin->getId());
        aiChatDeleteAdmin($admin);
    }
});

it('does not offer tools the admin role cannot use and refuses a forced call', function (): void {
    $admin = aiChatAdmin('ai_chat_limited', ['admin/system/ai/chat', 'admin/cms']);
    try {
        aiChatLogin($admin);
        AiChatScript::reset(
            new ToolCallResult([new ToolCall('call_0', 'enable_tools', ['sections' => ['catalog']])]),
            new ToolCallResult([new ToolCall('call_1', 'catalog_product_attributes_list', [])]),
            new TextResult('I cannot read the attributes.'),
        );

        $result = aiChatRequest('/api/admin/ai/chat', ['message' => 'List attributes', 'context' => ['route' => 'cms_page/index']]);

        expect($result['status'])->toBe(200);
        $offered = AiChatScript::$offeredTools[1];
        // Public storefront reads stay available; admin-gated catalog tools do not.
        expect($offered)->toContain('catalog_products_list', 'content_cms_blocks_list');
        $gated = array_values(array_filter($offered, static fn(string $n): bool => str_starts_with($n, 'catalog_product_attributes_') || str_ends_with($n, '_update') && str_starts_with($n, 'catalog_products')));
        expect($gated)->toBe([], 'Offered to a role without catalog access: ' . implode(', ', $gated));
        $toolResults = aiChatEvents($result['events'], 'tool_result');
        expect($toolResults)->toHaveCount(2);
        expect($toolResults[0]['ok'])->toBeTrue();
        expect($toolResults[1]['ok'])->toBeFalse();
        expect(aiChatEvents($result['events'], 'done')[0]['state'])->toBe('complete');
    } finally {
        aiChatDeleteConversations((int) $admin->getId());
        aiChatDeleteAdmin($admin);
    }
});

it('pauses a write for confirmation, then runs or denies it as the admin decides', function (): void {
    $admin = aiChatAdmin('ai_chat_writer', ['all']);
    try {
        aiChatLogin($admin);
        AiChatScript::reset(
            new ToolCallResult([new ToolCall('call_w1', 'catalog_products_update', ['id' => 999999991, 'name' => 'Renamed'])]),
        );

        $first = aiChatRequest('/api/admin/ai/chat', ['message' => 'Rename product 999999991']);

        expect($first['status'])->toBe(200);
        $confirm = aiChatEvents($first['events'], 'confirm');
        expect($confirm)->toHaveCount(1);
        expect($confirm[0]['calls'][0]['id'])->toBe('call_w1');
        expect($confirm[0]['calls'][0]['name'])->toBe('catalog_products_update');
        expect($confirm[0]['calls'][0]['read_only'])->toBeFalse();
        expect(aiChatEvents($first['events'], 'done')[0]['state'])->toBe('awaiting_confirmation');
        expect(aiChatEvents($first['events'], 'tool_result'))->toBe([]);
        $conversationId = (int) $confirm[0]['conversation_id'];

        /** @var Maho_Ai_Model_Conversation $conversation */
        $conversation = Mage::getModel('ai/conversation')->load($conversationId);
        $pending = $conversation->getPendingWrites();
        expect($pending)->toHaveCount(1);
        expect($pending[0]->getToolName())->toBe('catalog_products_update');

        // Denied: the model learns about it and answers without a write.
        AiChatScript::reset(new TextResult('Understood, nothing changed.'));
        $denied = aiChatRequest('/api/admin/ai/chat/confirm', [
            'conversation_id' => $conversationId,
            'decisions' => ['call_w1' => false],
        ]);
        expect($denied['status'])->toBe(200);
        expect(aiChatEvents($denied['events'], 'tool_result')[0]['denied'])->toBeTrue();
        expect(aiChatEvents($denied['events'], 'done')[0]['state'])->toBe('complete');
        expect($conversation->getPendingWrites())->toBe([]);
        $statuses = array_map(
            static fn($m) => $m->getToolStatus(),
            array_values(array_filter($conversation->messagesCollection()->getItems(), static fn($m) => $m->getRole() === 'tool')),
        );
        expect($statuses)->toBe(['denied']);

        // Approved: the write runs through the MCP dispatch; the product does not exist, so the tool reports an error.
        AiChatScript::reset(
            new ToolCallResult([new ToolCall('call_w2', 'catalog_products_update', ['id' => 999999991, 'name' => 'Renamed'])]),
        );
        $second = aiChatRequest('/api/admin/ai/chat', ['conversation_id' => $conversationId, 'message' => 'Try again']);
        expect(aiChatEvents($second['events'], 'done')[0]['state'])->toBe('awaiting_confirmation');

        AiChatScript::reset(new TextResult('That product does not exist.'));
        $approved = aiChatRequest('/api/admin/ai/chat/confirm', [
            'conversation_id' => $conversationId,
            'decisions' => ['call_w2' => true],
        ]);
        $results = aiChatEvents($approved['events'], 'tool_result');
        expect($results)->toHaveCount(1);
        expect($results[0]['id'])->toBe('call_w2');
        expect($results[0]['ok'])->toBeFalse();
        expect(aiChatEvents($approved['events'], 'done')[0]['state'])->toBe('complete');

        // A new message cancels whatever is still pending.
        AiChatScript::reset(
            new ToolCallResult([new ToolCall('call_w3', 'catalog_products_update', ['id' => 999999991, 'name' => 'X'])]),
        );
        aiChatRequest('/api/admin/ai/chat', ['conversation_id' => $conversationId, 'message' => 'Once more']);
        expect($conversation->getPendingWrites())->toHaveCount(1);
        AiChatScript::reset(new TextResult('Okay.'));
        aiChatRequest('/api/admin/ai/chat', ['conversation_id' => $conversationId, 'message' => 'Forget it']);
        expect($conversation->getPendingWrites())->toBe([]);
        $cancelled = array_filter(
            $conversation->messagesCollection()->getItems(),
            static fn($m) => $m->getToolStatus() === Maho_Ai_Model_Conversation_Message::TOOL_CANCELLED,
        );
        expect($cancelled)->toHaveCount(1);
    } finally {
        aiChatDeleteConversations((int) $admin->getId());
        aiChatDeleteAdmin($admin);
    }
});

it('refuses a conversation that belongs to another admin', function (): void {
    $owner = aiChatAdmin('ai_chat_owner', ['all']);
    $other = aiChatAdmin('ai_chat_other', ['all']);
    try {
        /** @var Maho_Ai_Model_Conversation $conversation */
        $conversation = Mage::getModel('ai/conversation');
        $conversation->setAdminUserId((int) $owner->getId())->setStoreId(0)->setStatus('active')->save();

        aiChatLogin($other);
        AiChatScript::reset(new TextResult('Hi'));
        $result = aiChatRequest('/api/admin/ai/chat', ['conversation_id' => (int) $conversation->getId(), 'message' => 'hello']);

        expect($result['status'])->toBe(404);
    } finally {
        aiChatDeleteConversations((int) $owner->getId());
        aiChatDeleteAdmin($owner);
        aiChatDeleteAdmin($other);
    }
});

it('opens an admin page in the browser through the local page tool', function (): void {
    $admin = aiChatAdmin('ai_chat_navigator', ['all']);
    try {
        aiChatLogin($admin);
        AiChatScript::reset(
            new ToolCallResult([new ToolCall('call_1', 'admin_open_page', ['page' => 'cms/page', 'record_id' => '2'])]),
            new TextResult('Opening the page.'),
        );

        $result = aiChatRequest('/api/admin/ai/chat', ['message' => 'Take me to the second CMS page']);

        expect($result['status'])->toBe(200);
        expect(AiChatScript::$offeredTools[0])->toContain('admin_open_page');
        $toolCalls = aiChatEvents($result['events'], 'tool_call');
        expect($toolCalls)->toHaveCount(1);
        expect($toolCalls[0]['read_only'])->toBeTrue();
        expect($toolCalls[0]['title'])->toBe('Open admin page');
        expect(aiChatEvents($result['events'], 'tool_result')[0]['ok'])->toBeTrue($result['raw']);
        $navigate = aiChatEvents($result['events'], 'navigate');
        expect($navigate)->toHaveCount(1);
        expect($navigate[0]['url'])->toContain('/cms_page/edit/page_id/2/');
        expect(aiChatEvents($result['events'], 'done')[0]['state'])->toBe('complete');
    } finally {
        aiChatDeleteConversations((int) $admin->getId());
        aiChatDeleteAdmin($admin);
    }
});

it('ignores the storefront store cookie: reads the default store view and builds admin urls without a store code', function (): void {
    $admin = aiChatAdmin('ai_chat_store_cookie', ['all']);
    $other = null;
    foreach (Mage::app()->getStores() as $candidate) {
        if ((int) $candidate->getId() !== (int) Mage::app()->getDefaultStoreView()->getId()) {
            $other = $candidate;
            break;
        }
    }
    $createdStore = $other === null;
    if ($createdStore) {
        $group = Mage::app()->getDefaultStoreView()->getGroup();
        $other = Mage::getModel('core/store')->setCode('ai_chat_other')->setName('Other')->setWebsiteId($group->getWebsiteId())->setGroupId($group->getId())->setIsActive(1);
        $other->save();
        Mage::app()->reinitStores();
    }
    $product = Mage::getModel('catalog/product')->getCollection()->addAttributeToSelect('name')->setPageSize(1)->getFirstItem();
    $productId = (int) $product->getId();
    $defaultName = (string) $product->getName();
    Mage::getSingleton('catalog/product_action')->updateAttributes([$productId], ['name' => 'Nome nella vista negozio'], (int) $other->getId());
    try {
        aiChatLogin($admin);
        AiChatScript::reset(
            new ToolCallResult([new ToolCall('call_1', 'catalog_products_get', ['id' => (string) $productId, 'store' => ''])]),
            new ToolCallResult([new ToolCall('call_2', 'admin_open_page', ['page' => 'catalog/products', 'record_id' => (string) $productId])]),
            new TextResult('Opening the product.'),
        );
        // What the store cookie does at bootstrap when the administrator visited that storefront.
        Mage::app()->setCurrentStore($other->getCode());

        $result = aiChatRequest('/api/admin/ai/chat', ['message' => 'Improve the SEO of this product', 'context' => ['route' => 'catalog_product/edit', 'entity_type' => 'product', 'entity_id' => $productId]]);

        expect($result['status'])->toBe(200);
        $toolResults = aiChatEvents($result['events'], 'tool_result');
        expect($toolResults[0]['ok'])->toBeTrue($result['raw']);
        expect($toolResults[0]['preview'])->toContain($defaultName);
        expect($toolResults[0]['preview'])->not->toContain('Nome nella vista negozio');
        $navigate = aiChatEvents($result['events'], 'navigate');
        expect($navigate)->toHaveCount(1);
        expect($navigate[0]['url'])->not->toContain('/' . $other->getCode() . '/');
        expect($navigate[0]['url'])->toContain('/catalog_product/edit/id/' . $productId . '/');
    } finally {
        Mage::app()->setCurrentStore(Mage_Core_Model_Store::ADMIN_CODE);
        $attributeId = (int) Mage::getSingleton('eav/config')->getAttribute('catalog_product', 'name')->getId();
        $adapter = Mage::getSingleton('core/resource')->getConnection('core_write');
        $adapter->delete($adapter->getTableName('catalog_product_entity_varchar'), ['entity_id = ?' => $productId, 'store_id = ?' => (int) $other->getId(), 'attribute_id = ?' => $attributeId]);
        if ($createdStore) {
            $other->delete();
            Mage::app()->reinitStores();
        }
        aiChatDeleteConversations((int) $admin->getId());
        aiChatDeleteAdmin($admin);
    }
});

it('offers the content editor guide as a tool once the panel sent one, and keeps it for later requests', function (): void {
    $admin = aiChatAdmin('ai_chat_guide', ['all']);
    $cacheId = Mage::helper('ai')->editorGuideCacheId();
    Mage::app()->removeCache($cacheId);
    try {
        aiChatLogin($admin);
        AiChatScript::reset(new TextResult('Hello.'));
        $without = aiChatRequest('/api/admin/ai/chat', ['message' => 'hello']);
        expect($without['status'])->toBe(200);
        expect(AiChatScript::$offeredTools[0])->not->toContain('admin_content_guide');

        AiChatScript::reset(
            new ToolCallResult([new ToolCall('call_1', 'admin_content_guide', [])]),
            new TextResult('I use two columns.'),
        );
        $guide = "## Columns: 2 Columns\n<div data-type=\"maho-columns\" data-preset=\"2-equal\"></div>";
        $result = aiChatRequest('/api/admin/ai/chat', ['message' => 'Add two columns to the home page', 'context' => ['editor_guide' => $guide]]);

        expect($result['status'])->toBe(200);
        expect(AiChatScript::$offeredTools[0])->toContain('admin_content_guide');
        expect(AiChatScript::$offeredTools[0])->toContain('content_cms_pages_create');
        expect(AiChatScript::$offeredSchemas[0]['content_cms_pages_create']['properties']['pageLayout']['enum'])->toContain('one_column');
        $toolCalls = aiChatEvents($result['events'], 'tool_call');
        expect($toolCalls[0]['read_only'])->toBeTrue();
        expect($toolCalls[0]['title'])->toBe('Content editor guide');
        $toolResult = aiChatEvents($result['events'], 'tool_result')[0];
        expect($toolResult['ok'])->toBeTrue($result['raw']);
        expect($toolResult['preview'])->toContain('data-preset="2-equal"');
        expect(Mage::helper('ai')->editorGuide())->toBe($guide);

        // The next request carries no guide: the server keeps the one it got.
        AiChatScript::reset(new TextResult('Hello again.'));
        $later = aiChatRequest('/api/admin/ai/chat', ['message' => 'hello']);
        expect($later['status'])->toBe(200);
        expect(AiChatScript::$offeredTools[0])->toContain('admin_content_guide');
    } finally {
        Mage::app()->removeCache($cacheId);
        aiChatDeleteConversations((int) $admin->getId());
        aiChatDeleteAdmin($admin);
    }
});

it('offers only the admin pages the role can open', function (): void {
    $admin = aiChatAdmin('ai_chat_nav_limited', ['admin/system/ai/chat', 'admin/cms']);
    try {
        aiChatLogin($admin);
        AiChatScript::reset(
            new ToolCallResult([new ToolCall('call_1', 'admin_open_page', ['page' => 'catalog/products'])]),
            new TextResult('I cannot open that page.'),
        );

        $result = aiChatRequest('/api/admin/ai/chat', ['message' => 'Open the products grid']);

        expect($result['status'])->toBe(200);
        expect(aiChatEvents($result['events'], 'tool_result')[0]['ok'])->toBeFalse();
        expect(aiChatEvents($result['events'], 'navigate'))->toBe([]);
    } finally {
        aiChatDeleteConversations((int) $admin->getId());
        aiChatDeleteAdmin($admin);
    }
});

it('runs the same call asked twice under two ids only once', function (): void {
    $admin = aiChatAdmin('ai_chat_dedupe2', ['all']);
    try {
        aiChatLogin($admin);
        AiChatScript::reset(
            new ToolCallResult([
                new ToolCall('call_a', 'catalog_products_list', ['itemsPerPage' => 1]),
                new ToolCall('call_b', 'catalog_products_list', ['itemsPerPage' => 1]),
            ]),
            new TextResult('Done.'),
        );

        $result = aiChatRequest('/api/admin/ai/chat', ['message' => 'List one product']);

        expect(aiChatEvents($result['events'], 'tool_call'))->toHaveCount(1);
        expect(aiChatEvents($result['events'], 'tool_result'))->toHaveCount(1);
        $done = aiChatEvents($result['events'], 'done');
        expect($done[0]['state'])->toBe('complete');
        $conversation = Mage::getModel('ai/conversation')->load((int) $done[0]['conversation_id']);
        $messages = array_values($conversation->messagesCollection()->getItems());
        expect(array_map(static fn($m) => $m->getRole(), $messages))->toBe(['user', 'assistant', 'tool', 'assistant']);
    } finally {
        aiChatDeleteConversations((int) $admin->getId());
        aiChatDeleteAdmin($admin);
    }
});

it('casts string arguments to the scalar types the tool schema declares', function (): void {
    $admin = aiChatAdmin('ai_chat_coerce', ['all']);
    $created = null;
    try {
        aiChatLogin($admin);
        AiChatScript::reset(
            new ToolCallResult([new ToolCall('call_c1', 'system_design_changes_create', ['storeId' => '1', 'design' => 'base/default', 'dateFrom' => '2090-01-01', 'dateTo' => '2090-01-02'])]),
        );
        $first = aiChatRequest('/api/admin/ai/chat', ['message' => 'Schedule the default theme']);
        $conversationId = (int) aiChatEvents($first['events'], 'confirm')[0]['conversation_id'];

        AiChatScript::reset(new TextResult('Scheduled.'));
        $approved = aiChatRequest('/api/admin/ai/chat/confirm', [
            'conversation_id' => $conversationId,
            'decisions' => ['call_c1' => true],
        ]);

        $results = aiChatEvents($approved['events'], 'tool_result');
        expect($results)->toHaveCount(1);
        expect($results[0]['ok'])->toBeTrue($approved['raw']);
        $created = Mage::getModel('core/design')->getCollection()->addFieldToFilter('date_from', '2090-01-01')->getFirstItem();
        expect((int) $created->getStoreId())->toBe(1);
    } finally {
        if ($created !== null && $created->getId()) {
            $created->delete();
        }
        aiChatDeleteConversations((int) $admin->getId());
        aiChatDeleteAdmin($admin);
    }
});

it('decodes JSON hex escapes a model copied into a string argument', function (): void {
    $admin = aiChatAdmin('ai_chat_escapes', ['all']);
    $block = Mage::getModel('cms/block')->setData(['title' => 'AI escapes', 'identifier' => 'ai_chat_escapes_block', 'content' => 'old', 'is_active' => 1, 'stores' => [0]]);
    $block->save();
    try {
        aiChatLogin($admin);
        AiChatScript::reset(
            new ToolCallResult([new ToolCall('call_e1', 'content_cms_blocks_update', ['id' => (string) $block->getId(), 'content' => '<p class="x">Hi & bye<\/p>'])]),
        );
        $first = aiChatRequest('/api/admin/ai/chat', ['message' => 'Update the block']);
        $conversationId = (int) aiChatEvents($first['events'], 'confirm')[0]['conversation_id'];

        AiChatScript::reset(new TextResult('Updated.'));
        $approved = aiChatRequest('/api/admin/ai/chat/confirm', ['conversation_id' => $conversationId, 'decisions' => ['call_e1' => true]]);

        expect(aiChatEvents($approved['events'], 'tool_result')[0]['ok'])->toBeTrue($approved['raw']);
        expect(Mage::getModel('cms/block')->load($block->getId())->getContent())->toBe('<p class="x">Hi &amp; bye</p>');
    } finally {
        $block->delete();
        aiChatDeleteConversations((int) $admin->getId());
        aiChatDeleteAdmin($admin);
    }
});

it('runs a tool in the store view the store argument names', function (): void {
    $admin = aiChatAdmin('ai_chat_store', ['all']);
    try {
        aiChatLogin($admin);
        $code = (string) Mage::app()->getDefaultStoreView()->getCode();
        AiChatScript::reset(
            new ToolCallResult([new ToolCall('call_s0', 'enable_tools', ['sections' => ['core']])]),
            new ToolCallResult([
                new ToolCall('call_s1', 'core_store_config_get', ['store' => $code]),
                new ToolCall('call_s2', 'core_store_config_get', ['store' => 'no_such_store_view']),
            ]),
            new TextResult('Done.'),
        );

        $result = aiChatRequest('/api/admin/ai/chat', ['message' => 'Show the store config']);

        expect(AiChatScript::$offeredTools[1])->toContain('core_store_config_get');
        $results = array_slice(aiChatEvents($result['events'], 'tool_result'), 1);
        expect($results)->toHaveCount(2);
        expect($results[0]['ok'])->toBeTrue($result['raw']);
        expect($results[0]['preview'])->toContain('"' . $code . '"');
        expect($results[1]['ok'])->toBeFalse();
        expect($results[1]['preview'])->toContain('Unknown store view code', $code);
    } finally {
        aiChatDeleteConversations((int) $admin->getId());
        aiChatDeleteAdmin($admin);
    }
});

it('hands the form values to the browser through the fill tool', function (): void {
    $admin = aiChatAdmin('ai_chat_filler', ['all']);
    try {
        aiChatLogin($admin);
        AiChatScript::reset(
            new ToolCallResult([
                new ToolCall('call_f1', 'admin_fill_form', ['page' => 'cms/page', 'record_id' => '2', 'fields' => ['title' => 'Home', 'content' => ['prepend' => '<p>Hi</p>', 'junk' => 1]]]),
                new ToolCall('call_f2', 'admin_fill_form', ['page' => 'cms/block', 'fields' => ['title' => 'New block']]),
                new ToolCall('call_f3', 'admin_fill_form', ['page' => 'cms/block', 'fields' => []]),
            ]),
            new TextResult('Review the form.'),
        );

        $result = aiChatRequest('/api/admin/ai/chat', ['message' => 'Edit the home page content']);

        expect(AiChatScript::$offeredTools[0])->toContain('admin_fill_form');
        $toolCalls = aiChatEvents($result['events'], 'tool_call');
        expect($toolCalls[0]['read_only'])->toBeTrue();
        expect($toolCalls[0]['title'])->toBe('Fill admin form');
        $results = aiChatEvents($result['events'], 'tool_result');
        expect($results[0]['ok'])->toBeTrue($result['raw']);
        expect($results[1]['ok'])->toBeTrue($result['raw']);
        expect($results[2]['ok'])->toBeFalse();
        $navigate = aiChatEvents($result['events'], 'navigate');
        expect($navigate)->toHaveCount(2);
        expect($navigate[0]['url'])->toContain('/cms_page/edit/page_id/2/');
        expect($navigate[0]['fields'])->toBe(['title' => 'Home', 'content' => ['prepend' => '<p>Hi</p>']]);
        expect($navigate[1]['url'])->toContain('/cms_block/new/');
        expect(aiChatEvents($result['events'], 'done')[0]['state'])->toBe('complete');
    } finally {
        aiChatDeleteConversations((int) $admin->getId());
        aiChatDeleteAdmin($admin);
    }
});

it('loads tool sections on demand and keeps them for the conversation', function (): void {
    $admin = aiChatAdmin('ai_chat_sections', ['all']);
    try {
        aiChatLogin($admin);
        AiChatScript::reset(
            new ToolCallResult([new ToolCall('call_e1', 'enable_tools', ['sections' => ['sales', 'nope']])]),
            new ToolCallResult([new ToolCall('call_e2', 'enable_tools', ['sections' => ['sales']])]),
            new ToolCallResult([new ToolCall('call_e3', 'sales_orders_list', ['itemsPerPage' => 1])]),
            new TextResult('Here are the orders.'),
        );

        $result = aiChatRequest('/api/admin/ai/chat', ['message' => 'What came in today?', 'context' => ['route' => 'dashboard/index']]);

        expect($result['status'])->toBe(200);
        // The dashboard loads no section and the message names none: only the local tools are offered at first.
        expect(AiChatScript::$offeredTools[0])->toBe(['enable_tools', 'remember', 'forget', 'attachment_read', 'admin_open_page', 'admin_fill_form', 'admin_page_action', 'run_in_background']);
        $results = aiChatEvents($result['events'], 'tool_result');
        expect($results[0]['ok'])->toBeFalse();
        expect($results[0]['preview'])->toContain('nope');
        expect($results[1]['ok'])->toBeTrue();
        // The failed load did not change the set, so the second request still had the local tools only.
        expect(AiChatScript::$offeredTools[1])->not->toContain('sales_orders_list');
        expect(AiChatScript::$offeredTools[2])->toContain('sales_orders_list', 'sales_orders_get');
        expect($results[2]['ok'])->toBeTrue($result['raw']);
        $done = aiChatEvents($result['events'], 'done');
        expect($done[0]['state'])->toBe('complete');
        expect(implode('', array_column(aiChatEvents($result['events'], 'delta'), 'text')))->toBe('Here are the orders.');

        // The next turn of the same conversation starts with the sales section loaded.
        AiChatScript::reset(new TextResult('Still here.'));
        aiChatRequest('/api/admin/ai/chat', ['conversation_id' => (int) $done[0]['conversation_id'], 'message' => 'Thanks', 'context' => ['route' => 'dashboard/index']]);
        expect(AiChatScript::$offeredTools[0])->toContain('sales_orders_list');
    } finally {
        aiChatDeleteConversations((int) $admin->getId());
        aiChatDeleteAdmin($admin);
    }
});

it('drops the placeholders a model sends for unused list parameters', function (): void {
    $admin = aiChatAdmin('ai_chat_placeholders', ['all']);
    try {
        aiChatLogin($admin);
        AiChatScript::reset(
            new ToolCallResult([new ToolCall('call_p1', 'catalog_products_list', ['search' => '', 'categoryId' => 0, 'priceMin' => 0, 'priceMax' => 0, 'sku' => '', 'status' => '', 'pageSize' => 2, 'page' => 1, 'store' => ''])]),
            new TextResult('Found products.'),
        );

        $result = aiChatRequest('/api/admin/ai/chat', ['message' => 'Find products', 'context' => ['route' => 'catalog_product/index']]);

        $preview = aiChatEvents($result['events'], 'tool_result')[0]['preview'];
        expect($preview)->not->toContain('"totalItems":0');
        expect($preview)->toContain('"sku"');
    } finally {
        aiChatDeleteConversations((int) $admin->getId());
        aiChatDeleteAdmin($admin);
    }
});

it('loads the sections the request names before the first model request', function (): void {
    $admin = aiChatAdmin('ai_chat_keywords', ['all']);
    try {
        aiChatLogin($admin);
        foreach ([
            'Open the home page of the default store in the editor' => ['content_cms_pages_list', 'admin_fill_form'],
            'Which orders came in today?' => ['sales_orders_list'],
            'Find products that are low in stock' => ['catalog_products_list'],
            'How many customers registered this week?' => ['customers_customers_list'],
        ] as $message => $expected) {
            AiChatScript::reset(new TextResult('Ok.'));
            aiChatRequest('/api/admin/ai/chat', ['message' => $message, 'context' => ['route' => 'dashboard/index']]);
            expect(AiChatScript::$offeredTools[0])->toContain(...$expected);
            expect(count(AiChatScript::$offeredTools[0]))->toBeLessThanOrEqual(McpToolbox::MAX_TOOLS);
        }
    } finally {
        aiChatDeleteConversations((int) $admin->getId());
        aiChatDeleteAdmin($admin);
    }
});

it('resolves a reused page identifier to the store view\'s own page', function (): void {
    $admin = aiChatAdmin('ai_chat_storepage', ['all']);
    $store = Mage::app()->getDefaultStoreView();
    $shared = Mage::getModel('cms/page')->setData(['identifier' => 'ai-chat-store-home', 'title' => 'Shared home', 'content' => 'shared', 'is_active' => 1, 'stores' => [0]]);
    $shared->save();
    $own = Mage::getModel('cms/page')->setData(['identifier' => 'ai-chat-store-home', 'title' => 'Own home', 'content' => 'own', 'is_active' => 1, 'stores' => [(int) $store->getId()]]);
    $own->save();
    try {
        aiChatLogin($admin);
        AiChatScript::reset(
            new ToolCallResult([
                new ToolCall('call_h1', 'content_cms_pages_list', ['identifier' => 'ai-chat-store-home', 'store' => (string) $store->getCode()]),
                new ToolCall('call_h2', 'content_cms_pages_list', ['identifier' => 'ai-chat-store-home']),
            ]),
            new TextResult('Found.'),
        );

        $result = aiChatRequest('/api/admin/ai/chat', ['message' => 'Find the home page', 'context' => ['route' => 'cms_page/index']]);

        $results = aiChatEvents($result['events'], 'tool_result');
        expect($results[0]['preview'])->toContain('"Own home"');
        expect($results[0]['preview'])->not->toContain('"Shared home"');
        // Without a store code the default store view applies, which owns the same page.
        expect($results[1]['preview'])->toContain('"Own home"');
    } finally {
        $own->delete();
        $shared->delete();
        aiChatDeleteConversations((int) $admin->getId());
        aiChatDeleteAdmin($admin);
    }
});

it('hands a page action to the browser', function (): void {
    $admin = aiChatAdmin('ai_chat_action', ['all']);
    try {
        aiChatLogin($admin);
        AiChatScript::reset(
            new ToolCallResult([
                new ToolCall('call_a1', 'admin_page_action', ['steps' => [['action' => 'set_field', 'target' => 'Comment', 'value' => 'pippo'], ['action' => 'click', 'target' => 'Submit Comment']]]),
                new ToolCall('call_a2', 'admin_page_action', ['steps' => [['action' => 'click', 'target' => 'Save'], ['action' => 'set_field', 'target' => 'Page Title', 'value' => 'x']]]),
            ]),
            new TextResult('Saving.'),
        );

        $result = aiChatRequest('/api/admin/ai/chat', ['message' => 'save it please', 'context' => ['route' => 'cms_page/edit']]);

        expect(AiChatScript::$offeredTools[0])->toContain('admin_page_action');
        $results = aiChatEvents($result['events'], 'tool_result');
        expect($results[0]['ok'])->toBeTrue($result['raw']);
        expect($results[1]['ok'])->toBeFalse();
        $actions = aiChatEvents($result['events'], 'page_action');
        expect($actions)->toHaveCount(1);
        expect($actions[0]['steps'])->toBe([
            ['action' => 'set_field', 'target' => 'Comment', 'value' => 'pippo'],
            ['action' => 'click', 'target' => 'Submit Comment', 'value' => null],
        ]);
        expect($results[1]['preview'])->toContain('a click must be the last step');
        expect(aiChatEvents($result['events'], 'done')[0]['state'])->toBe('complete');
    } finally {
        aiChatDeleteConversations((int) $admin->getId());
        aiChatDeleteAdmin($admin);
    }
});
