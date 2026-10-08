<?php

/**
 * Runs one turn of the admin assistant and streams it as server-sent events.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Ai
 */

declare(strict_types=1);

namespace Maho\Ai\Api\Chat;

use Mage_Admin_Model_User;
use Maho\Ai\Api\Agent\AgentFactory;
use Maho\Ai\Api\Agent\McpToolCatalog;
use Maho\Ai\Api\Agent\McpToolbox;
use Maho_Ai_Model_Chat_ClientGone;
use Maho_Ai_Model_Chat_RecordingSseWriter;
use Maho_Ai_Model_Chat_RepeatedDraft;
use Maho_Ai_Model_Chat_ConfirmationRequired;
use Maho_Ai_Model_Chat_MessageBagBuilder;
use Maho_Ai_Model_Chat_SseWriter;
use Maho_Ai_Model_Chat_SystemPrompt;
use Maho_Ai_Model_Chat_ToolExecutor;
use Maho_Ai_Model_Chat_ToolsetChanged;
use Maho_Ai_Model_Conversation;
use Maho_Ai_Model_Conversation_Message as Message;
use Symfony\AI\Agent\Agent;
use Symfony\AI\Agent\Exception\MaxIterationsExceededException;
use Symfony\AI\Agent\Execution\Execution;
use Symfony\AI\Platform\Message\Message as PlatformMessage;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Agent\Execution\Update\Progress;
use Symfony\AI\Agent\Toolbox\ToolResult;
use Symfony\AI\Platform\Exception\ExceptionInterface as PlatformException;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\TokenUsage\TokenUsage;
use Symfony\AI\Platform\TokenUsage\TokenUsageAggregation;

/**
 * Events, in the order a client sees them: `delta` (answer text), `tool_call`,
 * `tool_result`, `confirm` (write calls that wait for the administrator), `replace`
 * (the sanitized final text when it differs from the streamed one), `error`, `done`.
 */
final class Assistant
{
    public const USAGE_CONSUMER = Maho_Ai_Model_Conversation::USAGE_CONSUMER;

    /** Sent to the model, never stored: the run reached the tool round limit. */
    public const WRAP_UP_INSTRUCTION = 'You reached the limit of tool rounds for this answer. Do not call a tool. '
        . 'Answer now with the results of the tools that you already called. '
        . 'Say what you could not check, and suggest how the administrator can make the request narrower.';

    public function __construct(
        private readonly AgentFactory $agentFactory,
        private readonly McpToolbox $toolbox,
    ) {}

    /**
     * @param array<string, mixed> $pageContext
     * @param list<array{id: string, name: string, mime: string, size: int}> $attachments
     */
    public function startTurn(
        Maho_Ai_Model_Conversation $conversation,
        Mage_Admin_Model_User $admin,
        string $userMessage,
        array $pageContext,
        Maho_Ai_Model_Chat_SseWriter $sse,
        array $attachments = [],
    ): void {
        $validation = \Mage::getSingleton('ai/safety_inputValidator')->validate($userMessage);
        if (!$validation['safe']) {
            $sse->event('error', ['message' => \Mage::helper('ai')->__('The message was rejected: %s', (string) $validation['reason'])]);
            $sse->event('done', ['state' => 'error', 'conversation_id' => (int) $conversation->getId()]);
            return;
        }

        $conversation->cancelPendingWrites();
        if ($conversation->getStatus() === Maho_Ai_Model_Conversation::STATUS_ARCHIVED) {
            // The administrator answers an archived run: it is a conversation of the panel again.
            $conversation->setStatus(Maho_Ai_Model_Conversation::STATUS_ACTIVE)->save();
        }
        if ($attachments !== []) {
            $lines = [];
            foreach ($attachments as $file) {
                $lines[] = sprintf('- %s (%s, %s) id %s', $file['name'], $file['mime'], $this->humanSize($file['size']), $file['id']);
            }
            $userMessage = rtrim($userMessage) . "\n\nAttachments:\n" . implode("\n", $lines);
        }
        $message = $conversation->addMessage(['role' => Message::ROLE_USER, 'content' => $userMessage]);
        if ($attachments !== []) {
            $message->setAttachments($attachments)->save();
        }
        if ((string) $conversation->getTitle() === '') {
            $conversation->setTitle(mb_substr(trim(preg_replace('/\s+/', ' ', $userMessage) ?? $userMessage), 0, 80));
            $conversation->save();
        }

        $this->run($conversation, $admin, $pageContext, $sse);
    }

    /**
     * @param array<string, bool> $decisions tool call id => approved
     * @param array<string, mixed> $pageContext
     */
    public function resumeTurn(
        Maho_Ai_Model_Conversation $conversation,
        Mage_Admin_Model_User $admin,
        array $decisions,
        array $pageContext,
        Maho_Ai_Model_Chat_SseWriter $sse,
    ): void {
        $pending = $conversation->getPendingWrites();
        if ($pending === []) {
            $sse->event('error', ['message' => \Mage::helper('ai')->__('There is no action waiting for confirmation.')]);
            $sse->event('done', ['state' => 'error', 'conversation_id' => (int) $conversation->getId()]);
            return;
        }

        foreach ($pending as $message) {
            $callId = (string) $message->getToolCallId();
            if (($decisions[$callId] ?? false) !== true) {
                $message->setToolStatus(Message::TOOL_DENIED);
                $message->setContent(Message::deniedResult());
                $message->save();
                $sse->event('tool_result', ['id' => $callId, 'ok' => false, 'denied' => true, 'preview' => '']);
                continue;
            }

            $sse->event('tool_call', $this->describeCall($callId, (string) $message->getToolName(), $message->getToolArguments()));
            $text = (string) $this->toolbox->execute(new ToolCall($callId, (string) $message->getToolName(), $message->getToolArguments()))->getResult();
            $ok = !McpToolbox::isErrorText($text);
            $message->setToolStatus($ok ? Message::TOOL_DONE : Message::TOOL_ERROR);
            $message->setContent($text);
            if (!$ok) {
                $message->setUndoArguments(null);
            }
            $message->save();
            $result = ['id' => $callId, 'ok' => $ok, 'preview' => $this->preview($text)];
            if ($ok && $message->getUndoArguments() !== null) {
                $result['undo'] = (int) $message->getId();
            }
            $sse->event('tool_result', $result);
        }

        $this->run($conversation, $admin, $pageContext, $sse);
    }

    /**
     * One turn without a browser, in a queue worker. The instruction is already the last
     * message of the conversation. A job runs with its updates of one record approved in
     * advance; a scheduled run leaves every write waiting in the conversation.
     *
     * @return array{state: string, error: string}
     */
    public function runTask(
        Maho_Ai_Model_Conversation $conversation,
        Mage_Admin_Model_User $admin,
        \Maho_Ai_Model_Chat_RunMode $mode,
        ?\Maho_Ai_Model_Task_Schedule $schedule = null,
    ): array {
        $this->toolbox->setMode($mode);
        $this->toolbox->setRun($conversation, $schedule);
        $sse = new Maho_Ai_Model_Chat_RecordingSseWriter();
        try {
            $this->run($conversation, $admin, ['run_mode' => $mode->value], $sse);
        } finally {
            $this->toolbox->setMode(\Maho_Ai_Model_Chat_RunMode::Chat);
            $this->toolbox->setRun(null);
            $conversation->setStatus(Maho_Ai_Model_Conversation::STATUS_ACTIVE)->save();
        }

        return ['state' => $sse->state(), 'error' => $sse->error()];
    }

    /**
     * Put a record back as it was before a confirmed write. The undo is a write of its own:
     * it gets its own tool row, so the history and the model see it, and it cannot be undone.
     */
    public function undo(
        Maho_Ai_Model_Conversation $conversation,
        int $messageId,
        Maho_Ai_Model_Chat_SseWriter $sse,
    ): void {
        /** @var Message $original */
        $original = \Mage::getModel('ai/conversation_message')->load($messageId);
        $undo = $original->getUndoArguments();
        if (!$original->getId() || (int) $original->getConversationId() !== (int) $conversation->getId() || $undo === null || $original->getToolStatus() !== Message::TOOL_DONE) {
            $sse->event('error', ['message' => \Mage::helper('ai')->__('This change cannot be undone.')]);
            $sse->event('done', ['state' => 'error', 'conversation_id' => (int) $conversation->getId()]);
            return;
        }

        $name = (string) $original->getToolName();
        $callId = 'undo-' . $messageId;
        $call = $this->describeCall($callId, $name, $undo);
        $call['undo_of'] = $messageId;
        $sse->event('tool_call', $call);
        $text = (string) $this->toolbox->execute(new ToolCall($callId, $name, $undo))->getResult();
        $ok = !McpToolbox::isErrorText($text);

        $conversation->addMessage([
            'role' => Message::ROLE_ASSISTANT,
            'content' => '',
            'tool_calls' => \Mage::helper('core')->jsonEncode([['id' => $callId, 'name' => $name, 'arguments' => $undo, 'signature' => null]]),
        ]);
        $conversation->addMessage([
            'role' => Message::ROLE_TOOL,
            'tool_call_id' => $callId,
            'tool_name' => $name,
            'tool_arguments' => \Mage::helper('core')->jsonEncode($undo),
            'tool_status' => $ok ? Message::TOOL_DONE : Message::TOOL_ERROR,
            'is_write' => 1,
            'content' => $text,
        ]);
        if ($ok) {
            $original->setUndoArguments(null)->save();
            $conversation->addMessage(['role' => Message::ROLE_ASSISTANT, 'content' => \Mage::helper('ai')->__('I restored the previous values.')]);
        }
        $conversation->save();
        $sse->event('tool_result', ['id' => $callId, 'ok' => $ok, 'preview' => $this->preview($text)]);
        if ($ok) {
            $sse->event('delta', ['text' => \Mage::helper('ai')->__('I restored the previous values.')]);
        }
        $sse->event('done', ['state' => $ok ? 'complete' : 'error', 'conversation_id' => (int) $conversation->getId()]);
    }

    /**
     * @param array<string, mixed> $arguments
     * @return array{kind: string, scope: string, record: ?string, changes: list<array{field: string, from: mixed, to: mixed}>, undo: ?array<string, mixed>}
     */
    private function previewWrite(string $name, array $arguments): array
    {
        try {
            return $this->toolbox->previewWrite($name, $arguments);
        } catch (\Throwable $e) {
            \Mage::logException($e);

            return ['kind' => 'other', 'scope' => '', 'record' => null, 'changes' => [], 'undo' => null];
        }
    }

    /**
     * @param array<string, mixed> $pageContext
     */
    private function run(
        Maho_Ai_Model_Conversation $conversation,
        Mage_Admin_Model_User $admin,
        array $pageContext,
        Maho_Ai_Model_Chat_SseWriter $sse,
    ): void {
        $storeId = $conversation->getStoreId();
        $roundText = '';
        $persistRound = function (array $toolCalls, array $results) use ($conversation, &$roundText): void {
            $this->persistRound($conversation, $roundText, $toolCalls, $results, []);
            $roundText = '';
        };

        try {
            $this->toolbox->enableSections($this->initialSections($conversation, $pageContext));
            $this->toolbox->storeContentGuide((string) ($pageContext['editor_guide'] ?? ''));
            $pageContext['editor_guide'] = $this->toolbox->contentGuide();
            /** @var Maho_Ai_Model_Chat_SystemPrompt $promptBuilder */
            $promptBuilder = \Mage::getModel('ai/chat_systemPrompt');
            $prompt = $promptBuilder->build($admin, $pageContext, $storeId);
            $executor = new Maho_Ai_Model_Chat_ToolExecutor($this->toolbox, $persistRound);
            $limit = max(2, (int) \Mage::getStoreConfig('ai/chat/history_messages', $storeId));

            // A round that loaded tool sections ends the run: the runner resolves the tool
            // list once, so the agent starts again with the new set and the stored history.
            // A run that reaches the tool round limit gets one more round without tool calls,
            // so it answers with the data it already has. The toolbox stays, since some
            // providers refuse a history with tool calls when the request defines no tools.
            $restarts = 0;
            $wrapUp = false;
            $execution = null;
            do {
                $restart = false;
                $agent = $this->agentFactory->create($prompt, $executor, $storeId, $wrapUp ? 0 : null);
                $bag = new Maho_Ai_Model_Chat_MessageBagBuilder()->build($conversation, $limit);
                if ($wrapUp) {
                    $bag->add(PlatformMessage::ofUser(self::WRAP_UP_INSTRUCTION));
                }
                try {
                    $execution = $this->stream($agent, $bag, $sse, $roundText);
                } catch (Maho_Ai_Model_Chat_ToolsetChanged) {
                    $restart = ++$restarts < 4;
                    if (!$restart) {
                        throw new \Mage_Core_Exception(\Mage::helper('ai')->__('The assistant loaded tools too many times in one turn.'));
                    }
                } catch (MaxIterationsExceededException $e) {
                    if ($wrapUp) {
                        throw $e;
                    }
                    $wrapUp = $restart = true;
                    if ($roundText !== '') {
                        $roundText = '';
                        $sse->event('replace', ['text' => '']);
                    }
                }
            } while ($restart);
            assert($execution instanceof Execution);

            $text = $roundText;
            if ($text === '') {
                // A provider without streaming answers in one piece: nothing was sent as a delta yet.
                $content = $execution->getResult()->getContent();
                $text = is_string($content) ? $content : '';
                if ($text !== '') {
                    $sse->event('delta', ['text' => $text]);
                }
            }
            $metadata = [];
            $streamed = $text;
            $text = Maho_Ai_Model_Chat_RepeatedDraft::remove($text);
            $clean = $this->toolbox->linkRecords(\Mage::getSingleton('ai/safety_outputSanitizer')->sanitize($text, false, $metadata));
            if ($clean !== $streamed) {
                $sse->event('replace', ['text' => $clean]);
            }
            [$in, $out] = $this->tokenUsage($execution->getMetadata()->all());
            $conversation->addMessage([
                'role' => Message::ROLE_ASSISTANT,
                'content' => $clean,
                'input_tokens' => $in,
                'output_tokens' => $out,
            ]);
            $conversation->setPlatform($this->agentFactory->platformCode($storeId));
            $conversation->setModel($agent->getModel());
            $conversation->save();
            $this->recordUsage($conversation, $in, $out);
            $sse->event('done', ['state' => 'complete', 'conversation_id' => (int) $conversation->getId()]);
        } catch (Maho_Ai_Model_Chat_ClientGone) {
            // The administrator stopped the turn. Keep the text that was already streamed.
            if ($roundText !== '') {
                $conversation->addMessage(['role' => Message::ROLE_ASSISTANT, 'content' => $roundText]);
            }
            $this->stopped($conversation);
        } catch (Maho_Ai_Model_Chat_ConfirmationRequired $e) {
            $this->persistRound($conversation, $roundText, $e->toolCalls, $e->results, $e->pending);
            $rows = [];
            foreach ($conversation->getPendingWrites() as $row) {
                $rows[(string) $row->getToolCallId()] = $row;
            }
            $calls = [];
            foreach ($e->pending as $toolCall) {
                $call = $this->describeCall($toolCall->getId(), $toolCall->getName(), $toolCall->getArguments());
                $call['preview'] = $this->previewWrite($toolCall->getName(), $toolCall->getArguments());
                $row = $rows[$toolCall->getId()] ?? null;
                if ($row !== null && $call['preview']['undo'] !== null) {
                    $row->setUndoArguments($call['preview']['undo'])->save();
                }
                unset($call['preview']['undo']);
                $calls[] = $call;
            }
            $sse->event('confirm', ['conversation_id' => (int) $conversation->getId(), 'calls' => $calls]);
            $sse->event('done', ['state' => 'awaiting_confirmation', 'conversation_id' => (int) $conversation->getId()]);
        } catch (MaxIterationsExceededException) {
            $this->fail($conversation, $sse, \Mage::helper('ai')->__(
                'The assistant stopped after %d tool rounds without an answer. Make the request narrower, or raise "Max Tool Rounds per Answer" under System > Configuration > AI > Assistant.',
                (int) \Mage::getStoreConfig('ai/chat/max_tool_calls', $storeId),
            ));
        } catch (PlatformException $e) {
            $error = \Mage::helper('ai')->translateProviderException($e, $this->agentFactory->platformCode($storeId));
            $this->fail($conversation, $sse, $this->readableProviderError($error->getMessage()));
        } catch (\Mage_Core_Exception $e) {
            $this->fail($conversation, $sse, $e->getMessage());
        } catch (\Throwable $e) {
            \Mage::logException($e);
            $this->fail($conversation, $sse, \Mage::helper('ai')->__('The assistant stopped because of an internal error: %s', mb_substr($e->getMessage(), 0, 500)));
        }
    }

    /** The administrator pressed Stop: the history shows it after a reload. */
    public function stopped(Maho_Ai_Model_Conversation $conversation): void
    {
        $this->notice($conversation, \Mage::helper('ai')->__('Stopped.'), Message::TOOL_CANCELLED);
    }

    /** The turn ends with an error: the panel shows it now, and the history shows it after a reload. */
    private function fail(Maho_Ai_Model_Conversation $conversation, Maho_Ai_Model_Chat_SseWriter $sse, string $message): void
    {
        $this->notice($conversation, $message, Message::TOOL_ERROR);
        $sse->event('error', ['message' => $message]);
        $sse->event('done', ['state' => 'error', 'conversation_id' => (int) $conversation->getId()]);
    }

    /** A note about the turn itself, shown in the history and never sent to the model. */
    private function notice(Maho_Ai_Model_Conversation $conversation, string $text, string $status): void
    {
        $conversation->addMessage(['role' => Message::ROLE_ASSISTANT, 'content' => $text, 'tool_status' => $status]);
    }

    /** Run the agent once and forward its progress as SSE events. */
    private function stream(Agent $agent, MessageBag $bag, Maho_Ai_Model_Chat_SseWriter $sse, string &$roundText): Execution
    {
        // With ai/general/log_requests on, one line per model request tells how the provider
        // streamed it: a repeated answer or a repeated tool call then points at the provider.
        $trace = \Mage::getStoreConfigFlag('ai/general/log_requests');
        $round = ['deltas' => 0, 'chars' => 0, 'tools' => 0];
        $logRound = static function (array $round) use ($agent): void {
            if ($round['deltas'] + $round['tools'] > 0) {
                \Mage::log(sprintf('chat model=%s deltas=%d chars=%d tool_calls=%d', $agent->getModel(), $round['deltas'], $round['chars'], $round['tools']), \Mage::LOG_INFO, 'ai.log');
            }
        };
        $execution = $agent->call($bag, ['stream' => true]);
        foreach ($execution as $update) {
            if (!$update instanceof Progress) {
                continue;
            }
            $payload = $update->getPayload();
            switch ($update->getStage()) {
                case 'model_request':
                    $roundText = '';
                    if ($trace) {
                        $logRound($round);
                    }
                    $round = ['deltas' => 0, 'chars' => 0, 'tools' => 0];
                    break;
                case 'delta':
                    if ($payload instanceof TextDelta && $payload->getText() !== '') {
                        $roundText .= $payload->getText();
                        $round['deltas']++;
                        $round['chars'] += mb_strlen($payload->getText());
                        $sse->event('delta', ['text' => $payload->getText()]);
                    }
                    break;
                case 'tool_call':
                    if ($payload instanceof ToolCall) {
                        $round['tools']++;
                        $sse->event('tool_call', $this->describeCall($payload->getId(), $payload->getName(), $payload->getArguments()));
                    }
                    break;
                case 'tool_result':
                    if ($payload instanceof ToolResult) {
                        $text = (string) $payload->getResult();
                        $sse->event('tool_result', [
                            'id' => $payload->getToolCall()->getId(),
                            'ok' => !McpToolbox::isErrorText($text),
                            'preview' => $this->preview($text),
                        ]);
                        $navigation = $this->toolbox->takeNavigation();
                        if ($navigation !== null) {
                            $sse->event(isset($navigation['steps']) ? 'page_action' : 'navigate', $navigation);
                        }
                    }
                    break;
            }
        }
        if ($trace) {
            $logRound($round);
        }

        return $execution;
    }

    /**
     * The tool sections to load before the first model request: the section of the admin
     * page the administrator is on, and every section this conversation used before.
     *
     * @param array<string, mixed> $pageContext
     * @return list<string>
     */
    private function initialSections(Maho_Ai_Model_Conversation $conversation, array $pageContext): array
    {
        $sections = [];
        $route = (string) ($pageContext['route'] ?? $conversation->getContextRoute() ?? '');
        $controller = explode('/', $route)[0];
        foreach (self::ROUTE_SECTIONS as $prefix => $section) {
            if ($controller === $prefix || str_starts_with($controller, $prefix . '_')) {
                $sections[] = $section;
                break;
            }
        }
        $userMessage = '';
        foreach ($conversation->messagesCollection()->getItems() as $message) {
            if ($message->getRole() === Message::ROLE_USER) {
                $userMessage = (string) $message->getContent();
            }
            if ($message->getRole() !== Message::ROLE_TOOL) {
                continue;
            }
            $name = (string) $message->getToolName();
            if ($name === McpToolbox::ENABLE_NAME) {
                array_push($sections, ...array_filter((array) ($message->getToolArguments()['sections'] ?? []), is_string(...)));
            } elseif (!McpToolbox::isLocal($name)) {
                $sections[] = McpToolCatalog::section($name);
            }
        }
        // Last in the list is first in the budget: the sections the request names, best match last.
        array_push($sections, ...$this->sectionsNamedIn($userMessage));

        return array_values(array_unique(array_reverse(array_values(array_unique(array_reverse($sections))))));
    }

    /**
     * The sections whose tool names share a word with the request: "the home page" names
     * content_cms_pages_list, "orders from yesterday" names sales_orders_list. A model that
     * finds the tool loaded does not have to ask for it first.
     *
     * @return list<string>
     */
    private function sectionsNamedIn(string $userMessage): array
    {
        $words = [];
        foreach (preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($userMessage)) ?: [] as $word) {
            if (mb_strlen($word) >= 3) {
                $words[self::singular($word)] = true;
            }
        }
        if ($words === []) {
            return [];
        }
        $scores = [];
        foreach ($this->toolbox->sections() as $section => $names) {
            foreach ($names as $name) {
                $segments = explode('_', $name);
                array_shift($segments);
                array_pop($segments);
                foreach (array_unique($segments) as $segment) {
                    if (isset($words[self::singular($segment)])) {
                        $scores[$section] = ($scores[$section] ?? 0) + 1;
                    }
                }
            }
        }
        asort($scores);

        return array_keys($scores);
    }

    private static function singular(string $word): string
    {
        return match (true) {
            str_ends_with($word, 'ies') => substr($word, 0, -3) . 'y',
            str_ends_with($word, 'ses') || str_ends_with($word, 'xes') => substr($word, 0, -2),
            str_ends_with($word, 's') && !str_ends_with($word, 'ss') => substr($word, 0, -1),
            default => $word,
        };
    }

    /** Admin controller prefix => tool section. */
    private const ROUTE_SECTIONS = [
        'catalog' => 'catalog',
        'cms' => 'content',
        'blog' => 'content',
        'widget' => 'content',
        'sales' => 'sales',
        'customer' => 'customers',
        'customersegmentation' => 'customers',
        'promo' => 'promotions',
        'tax' => 'tax',
        'newsletter' => 'other',
        'report' => 'reports',
        'system' => 'system',
        'permissions' => 'system',
        'cache' => 'system',
        'process' => 'system',
        'urlrewrite' => 'catalog',
    ];

    /**
     * Store one tool round: the assistant message that asked for the calls, a tool row
     * per finished call, and a pending row per write that waits for the administrator.
     *
     * @param list<ToolCall> $toolCalls
     * @param array<string, ToolResult> $results keyed by tool call id
     * @param list<ToolCall> $pending
     */
    private function persistRound(
        Maho_Ai_Model_Conversation $conversation,
        string $text,
        array $toolCalls,
        array $results,
        array $pending,
    ): void {
        $toolCalls = Maho_Ai_Model_Chat_ToolExecutor::unique($toolCalls);
        $calls = [];
        foreach ($toolCalls as $toolCall) {
            $calls[] = [
                'id' => $toolCall->getId(),
                'name' => $toolCall->getName(),
                'arguments' => $toolCall->getArguments(),
                'signature' => $toolCall->getSignature(),
            ];
        }
        $conversation->addMessage([
            'role' => Message::ROLE_ASSISTANT,
            'content' => $text,
            'tool_calls' => \Mage::helper('core')->jsonEncode($calls),
        ]);

        $pendingIds = array_map(static fn(ToolCall $c): string => $c->getId(), $pending);
        foreach ($toolCalls as $toolCall) {
            $id = $toolCall->getId();
            $isPending = in_array($id, $pendingIds, true);
            $resultText = isset($results[$id]) ? (string) $results[$id]->getResult() : null;
            $conversation->addMessage([
                'role' => Message::ROLE_TOOL,
                'tool_call_id' => $id,
                // The pending row stores the real MCP name: the confirm step calls it directly.
                'tool_name' => $isPending ? $this->toolbox->resolve($toolCall->getName()) : $toolCall->getName(),
                'tool_arguments' => \Mage::helper('core')->jsonEncode($toolCall->getArguments()),
                'tool_status' => match (true) {
                    $isPending => Message::TOOL_PENDING,
                    $resultText !== null && McpToolbox::isErrorText($resultText) => Message::TOOL_ERROR,
                    default => Message::TOOL_DONE,
                },
                'is_write' => $isPending || !$this->toolbox->isReadOnly($toolCall->getName()) ? 1 : 0,
                'content' => $resultText,
            ]);
        }
    }

    /**
     * @param array<string, mixed> $arguments
     * @return array{id: string, name: string, title: string, arguments: array<string, mixed>, read_only: bool, destructive: bool}
     */
    private function describeCall(string $id, string $name, array $arguments): array
    {
        return [
            'id' => $id,
            'name' => $this->toolbox->resolve($name),
            'title' => $this->toolbox->title($name),
            'arguments' => $arguments,
            'read_only' => $this->toolbox->isReadOnly($name),
            'destructive' => $this->toolbox->isDestructive($name),
        ];
    }

    /**
     * A provider error often quotes the whole JSON body. Keep its message and drop the rest.
     */
    private function readableProviderError(string $message): string
    {
        if (preg_match('/^(.*?):\s*"?(\{.*\})"?\s*$/s', $message, $m) !== 1) {
            return $message;
        }
        try {
            $body = json_decode(stripslashes($m[2]), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            try {
                $body = json_decode($m[2], true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                return $message;
            }
        }
        $detail = $body['error']['message'] ?? $body['message'] ?? $body['error'] ?? null;
        if (!is_string($detail) || trim($detail) === '') {
            return $message;
        }
        $prefix = preg_replace('/:\s*Unexpected response code \d+$/', '', trim($m[1])) ?? trim($m[1]);

        return $prefix . ': ' . trim($detail);
    }

    private function humanSize(int $bytes): string
    {
        return $bytes >= 1024 * 1024 ? sprintf('%.1f MB', $bytes / 1024 / 1024) : sprintf('%d KB', max(1, intdiv($bytes, 1024)));
    }

    private function preview(string $text): string
    {
        $text = trim($text);

        return mb_strlen($text) > 300 ? mb_substr($text, 0, 300) . '…' : $text;
    }

    /**
     * @param array<string, mixed> $metadata
     * @return array{int, int}
     */
    private function tokenUsage(array $metadata): array
    {
        foreach ($metadata as $value) {
            if ($value instanceof TokenUsage || $value instanceof TokenUsageAggregation) {
                return [(int) ($value->getPromptTokens() ?? 0), (int) ($value->getCompletionTokens() ?? 0)];
            }
        }

        return [0, 0];
    }

    private function recordUsage(Maho_Ai_Model_Conversation $conversation, int $in, int $out): void
    {
        try {
            \Mage::getModel('ai/usage')->recordCall(
                consumer: self::USAGE_CONSUMER,
                platform: (string) $conversation->getPlatform(),
                model: (string) $conversation->getModel(),
                storeId: (int) $conversation->getStoreId(),
                inputTokens: $in,
                outputTokens: $out,
            );
        } catch (\Throwable $e) {
            \Mage::logException($e);
        }
    }
}
