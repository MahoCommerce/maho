<?php

/**
 * The run_in_background tool: a long job runs in a queue worker, in a conversation of its own.
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
 * A request over many records cannot run inside one chat turn. The tool is a write, so the
 * administrator confirms the instruction once; the worker then runs the turn with every
 * update of one record approved in advance, and the steps land in a new conversation the
 * administrator can open while it runs. Every other write waits in that conversation.
 */
final class BackgroundTaskTool
{
    public const NAME = 'run_in_background';
    public const TITLE = 'Run in the background';

    public function tool(): Tool
    {
        return ToolDefinition::create(
            new ExecutionReference(self::class, 'start'),
            self::NAME,
            'Run a long job in the background: a change over many records, a text for every product of a category, a check over a whole catalog. Give a complete instruction, with the records, the scope and the exact change, as if to a colleague who cannot ask back. The administrator confirms it once; every read and every update of one record in the job is then approved in advance. Any other write, such as a create, a delete, a cancel or a refund, waits in the job conversation for the administrator. The job runs in a new conversation that the administrator can open to follow it.',
            [
                'type' => 'object',
                'properties' => [
                    'title' => ['type' => 'string', 'description' => 'A short name for the job, up to 60 characters.', 'maxLength' => 60],
                    'instruction' => ['type' => 'string', 'description' => 'The complete instruction of the job.', 'maxLength' => 4000],
                ],
                'required' => ['title', 'instruction'],
                'additionalProperties' => false,
            ],
            ['title' => self::TITLE, 'read_only' => false, 'destructive' => false, 'local' => true],
        );
    }

    /**
     * @param array<string, mixed> $arguments
     * @return array{ok: bool, text: string}
     */
    public function start(array $arguments): array
    {
        $admin = \Mage::getSingleton('admin/session')->getUser();
        $adminId = (int) ($admin?->getId() ?? 0);
        if ($adminId <= 0) {
            return ['ok' => false, 'text' => 'No administrator is logged in.'];
        }
        $title = trim(mb_substr((string) ($arguments['title'] ?? ''), 0, 60));
        $instruction = trim((string) ($arguments['instruction'] ?? ''));
        if ($instruction === '') {
            return ['ok' => false, 'text' => 'The instruction is empty.'];
        }
        if (!\Mage::helper('core')->isModuleEnabled('Maho_Queue')) {
            return ['ok' => false, 'text' => 'The queue is not available, so nothing can run in the background. Do the job in this chat.'];
        }

        /** @var \Maho_Ai_Model_Conversation $conversation */
        $conversation = \Mage::getModel('ai/conversation');
        $conversation->setAdminUserId($adminId);
        $conversation->setStoreId(0);
        $conversation->setTitle($title !== '' ? $title : mb_substr($instruction, 0, 60));
        \Maho_Ai_Model_Chat_AgentRunner::queue($conversation, $adminId, $instruction, \Maho_Ai_Model_Chat_RunMode::Job);

        return ['ok' => true, 'text' => sprintf('The job runs in the background as conversation %d, "%s". Tell the administrator to open it from the conversation list to follow it; do not run the job here as well.', (int) $conversation->getId(), (string) $conversation->getTitle())];
    }
}
