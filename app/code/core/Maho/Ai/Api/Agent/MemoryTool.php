<?php

/**
 * The remember and forget tools: notes an administrator keeps across conversations.
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
 * A note is a lasting preference or fact the administrator stated: the language to answer
 * in, the store view they work on, how they like a list. The prompt lists the notes of the
 * logged-in administrator; nobody else sees them. Both tools run without confirmation: a
 * note changes nothing in the store.
 */
final class MemoryTool
{
    public const REMEMBER = 'remember';
    public const FORGET = 'forget';
    public const NAMES = [self::REMEMBER, self::FORGET];

    public static function title(string $name): string
    {
        return $name === self::FORGET ? 'Forget a note' : 'Remember a note';
    }

    public function rememberTool(): Tool
    {
        return ToolDefinition::create(
            new ExecutionReference(self::class, 'remember'),
            self::REMEMBER,
            'Keep a short note about the administrator for every later conversation: a lasting preference or fact they stated, such as the language to answer in, the store view they work on, or a rule for their content. Store it once, in one sentence, and never store a secret, a password or a record value you could read again.',
            [
                'type' => 'object',
                'properties' => ['note' => ['type' => 'string', 'description' => 'One sentence, at most ' . \Maho_Ai_Model_Memory::MAX_LENGTH . ' characters.', 'maxLength' => \Maho_Ai_Model_Memory::MAX_LENGTH]],
                'required' => ['note'],
                'additionalProperties' => false,
            ],
            ['title' => self::title(self::REMEMBER), 'read_only' => true, 'destructive' => false, 'local' => true],
        );
    }

    public function forgetTool(): Tool
    {
        return ToolDefinition::create(
            new ExecutionReference(self::class, 'forget'),
            self::FORGET,
            'Drop one of the notes listed in the prompt under what the administrator asked you to remember, by its number, when the administrator asks you to forget it or states the opposite.',
            [
                'type' => 'object',
                'properties' => ['note_id' => ['type' => 'integer', 'description' => 'The number of the note, as the prompt lists it.']],
                'required' => ['note_id'],
                'additionalProperties' => false,
            ],
            ['title' => self::title(self::FORGET), 'read_only' => true, 'destructive' => false, 'local' => true],
        );
    }

    /**
     * @param array<string, mixed> $arguments
     * @return array{ok: bool, text: string}
     */
    public function remember(array $arguments): array
    {
        $adminId = $this->adminId();
        if ($adminId === null) {
            return ['ok' => false, 'text' => 'No administrator is logged in.'];
        }
        $note = trim(preg_replace('/\s+/', ' ', (string) ($arguments['note'] ?? '')) ?? '');
        if ($note === '') {
            return ['ok' => false, 'text' => 'The note is empty.'];
        }
        $note = mb_substr($note, 0, \Maho_Ai_Model_Memory::MAX_LENGTH);
        $notes = \Maho_Ai_Model_Memory::notesOf($adminId);
        foreach ($notes as $existing) {
            if (mb_strtolower($existing['note']) === mb_strtolower($note)) {
                return ['ok' => true, 'text' => sprintf('Already noted as note %d.', $existing['id'])];
            }
        }
        if (count($notes) >= \Maho_Ai_Model_Memory::MAX_NOTES) {
            return ['ok' => false, 'text' => sprintf('The administrator already has %d notes, the most allowed. Forget one first.', \Maho_Ai_Model_Memory::MAX_NOTES)];
        }
        $memory = \Mage::getModel('ai/memory')->setAdminUserId($adminId)->setNote($note);
        $memory->save();

        return ['ok' => true, 'text' => sprintf('Noted as note %d. It will be in every later conversation of this administrator.', (int) $memory->getId())];
    }

    /**
     * @param array<string, mixed> $arguments
     * @return array{ok: bool, text: string}
     */
    public function forget(array $arguments): array
    {
        $adminId = $this->adminId();
        $id = (int) ($arguments['note_id'] ?? 0);
        $memory = \Mage::getModel('ai/memory')->load($id);
        if ($adminId === null || !$memory->getId() || $memory->getAdminUserId() !== $adminId) {
            return ['ok' => false, 'text' => sprintf('There is no note %d.', $id)];
        }
        $memory->delete();

        return ['ok' => true, 'text' => sprintf('Note %d is forgotten.', $id)];
    }

    private function adminId(): ?int
    {
        $id = (int) (\Mage::getSingleton('admin/session')->getUser()?->getId() ?? 0);

        return $id > 0 ? $id : null;
    }
}
