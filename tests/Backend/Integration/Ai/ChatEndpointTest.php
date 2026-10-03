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

    public static function answer(Model $model, array|string|object $input, array $options): ResultInterface
    {
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

        // The worker: the handler runs the job with the scripted model; the update needs no confirmation.
        AiChatScript::reset(
            new ToolCallResult([new ToolCall('call_w', 'content_cms_pages_update', ['id' => (string) $pageId, 'title' => 'New'])]),
            new TextResult('Renamed one page.'),
        );
        new Maho_Ai_Model_Chat_BackgroundTurnHandler()(new Maho_Ai_Model_Chat_BackgroundTurn($jobId, (int) $admin->getId(), 'Rename the page ' . $pageId . ' to New.'));

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
    } finally {
        $queue->delete($queueTable, ['body LIKE ?' => '%BackgroundTurn%']);
        Mage::getModel('cms/page')->load($pageId)->delete();
        aiChatLogin($admin);
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

it('ends the turn and releases the lock when the browser closes the stream', function (): void {
    $admin = aiChatAdmin('ai_chat_stopper', ['all']);
    try {
        aiChatLogin($admin);
        AiChatScript::reset(new TextResult('A long answer the administrator stopped.'));
        Maho_Ai_Model_Chat_SseWriter::$simulateClientGone = true;
        try {
            $stopped = aiChatRequest('/api/admin/ai/chat', ['message' => 'tell me everything']);
        } finally {
            Maho_Ai_Model_Chat_SseWriter::$simulateClientGone = false;
        }
        expect($stopped['status'])->toBe(200);
        expect(aiChatEvents($stopped['events'], 'done'))->toBe([]);

        $conversation = Mage::getModel('ai/conversation')->getCollection()->addFieldToFilter('admin_user_id', (int) $admin->getId())->getFirstItem();
        expect($conversation->getId())->not->toBeNull();
        AiChatScript::reset(new TextResult('Next answer.'));
        $next = aiChatRequest('/api/admin/ai/chat', ['message' => 'and now?', 'conversation_id' => (int) $conversation->getId()]);
        expect($next['status'])->toBe(200);
        expect(aiChatEvents($next['events'], 'done')[0]['state'])->toBe('complete');
    } finally {
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
        expect(AiChatScript::$offeredTools[0])->toBe(['enable_tools', 'remember', 'forget', 'admin_open_page', 'admin_fill_form', 'admin_page_action', 'run_in_background']);
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
