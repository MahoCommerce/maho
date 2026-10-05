<?php

/**
 * The notify tool: a background run tells the administrators about a result in the admin inbox.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Ai
 */

declare(strict_types=1);

namespace Maho\Ai\Api\Agent;

use Symfony\AI\Platform\Tool\ExecutionReference;
use Symfony\AI\Platform\Tool\Tool;

/**
 * Only a run without a browser offers it: in the chat, the answer itself reaches the
 * administrator. A message only adds to the inbox, so the tool runs without a confirmation.
 */
final class NotifyTool
{
    public const NAME = 'notify';

    /** Messages one run may send, so a confused run cannot flood the inbox. */
    public const MAX_PER_RUN = 3;

    private ?\Maho_Ai_Model_Conversation $conversation = null;
    private ?\Maho_Ai_Model_Task_Schedule $schedule = null;
    private int $sent = 0;

    /** The run the messages are about; null outside a background run. */
    public function setRun(?\Maho_Ai_Model_Conversation $conversation, ?\Maho_Ai_Model_Task_Schedule $schedule = null): void
    {
        $this->conversation = $conversation;
        $this->schedule = $schedule;
        $this->sent = 0;
    }

    public function isAvailable(): bool
    {
        return $this->conversation !== null;
    }

    public function tool(): Tool
    {
        return ToolDefinition::create(
            new ExecutionReference(self::class, 'notify'),
            self::NAME,
            'Put a message in the admin notification inbox, for a result that needs the attention of the administrators. The message links to this conversation. Send one only when there is something to act on or to know; when everything is as expected, send none. The text must stand on its own: the facts, the records and the numbers.',
            [
                'type' => 'object',
                'properties' => [
                    'title' => ['type' => 'string', 'description' => 'The result in one line, up to 120 characters.', 'maxLength' => 120],
                    'text' => ['type' => 'string', 'description' => 'The details, as plain text without Markdown, up to 2000 characters.', 'maxLength' => 2000],
                    'severity' => ['type' => 'string', 'enum' => array_keys(\Maho_Ai_Model_Chat_Notifier::SEVERITIES), 'description' => 'notice for information, minor or major for a problem to act on, critical for a problem that stops sales.'],
                ],
                'required' => ['title', 'text', 'severity'],
                'additionalProperties' => false,
            ],
            ['title' => 'Send a notification', 'read_only' => true, 'destructive' => false, 'local' => true],
        );
    }

    /**
     * @param array<string, mixed> $arguments
     * @return array{ok: bool, text: string}
     */
    public function notify(array $arguments): array
    {
        if ($this->conversation === null) {
            return ['ok' => false, 'text' => 'Notifications are for background runs only. Answer the administrator in the chat.'];
        }
        if ($this->sent >= self::MAX_PER_RUN) {
            return ['ok' => false, 'text' => sprintf('This run already sent %d notifications. Put the rest in your final answer.', self::MAX_PER_RUN)];
        }
        $title = trim(mb_substr((string) ($arguments['title'] ?? ''), 0, 120));
        $text = trim(mb_substr((string) ($arguments['text'] ?? ''), 0, 2000));
        $severity = \Maho_Ai_Model_Chat_Notifier::SEVERITIES[(string) ($arguments['severity'] ?? '')] ?? \Maho_Ai_Model_Chat_Notifier::SEVERITIES['notice'];
        if ($title === '') {
            return ['ok' => false, 'text' => 'The title is empty.'];
        }

        \Maho_Ai_Model_Chat_Notifier::send($severity, $title, $text, $this->conversation, $this->schedule);
        $this->sent++;

        return ['ok' => true, 'text' => 'The notification is in the admin inbox.'];
    }
}
