<?php

/**
 * The attachment_read tool: the text of a file the administrator attached to a message.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Ai
 */

declare(strict_types=1);

namespace Maho\Ai\Api\Agent;

use Symfony\AI\Platform\Tool\ExecutionReference;
use Symfony\AI\Platform\Tool\Tool;

final class AttachmentTool
{
    public const NAME = 'attachment_read';
    public const TITLE = 'Read an attachment';

    public function tool(): Tool
    {
        return ToolDefinition::create(
            new ExecutionReference(self::class, 'read'),
            self::NAME,
            'Read a text, CSV, JSON, XML or Markdown file the administrator attached to a message, by the id the message lists. A long file comes in parts: pass offset to continue. Read a file before you use what it holds; never guess its content from its name.',
            [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'string', 'description' => 'The attachment id, as the message lists it.'],
                    'offset' => ['type' => 'integer', 'description' => 'Character to start from, 0 by default.', 'minimum' => 0],
                ],
                'required' => ['id'],
                'additionalProperties' => false,
            ],
            ['title' => self::TITLE, 'read_only' => true, 'destructive' => false, 'local' => true],
        );
    }

    /**
     * @param array<string, mixed> $arguments
     * @return array{ok: bool, text: string}
     */
    public function read(array $arguments): array
    {
        $adminId = (int) (\Mage::getSingleton('admin/session')->getUser()?->getId() ?? 0);
        $id = (string) ($arguments['id'] ?? '');
        $file = \Maho_Ai_Model_Chat_Attachment::describe($adminId, $id);
        $path = \Maho_Ai_Model_Chat_Attachment::path($adminId, $id);
        if ($file === null || $path === null) {
            return ['ok' => false, 'text' => sprintf('There is no attachment "%s".', $id)];
        }
        if (!\Maho_Ai_Model_Chat_Attachment::isText($file['mime'])) {
            return ['ok' => false, 'text' => sprintf('"%s" is a %s file, not a text file; it was shown to you with the message when the model can see images.', $file['name'], $file['mime'])];
        }
        $content = (string) file_get_contents($path);
        if (!mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'ISO-8859-1');
        }
        $offset = max(0, (int) ($arguments['offset'] ?? 0));
        $total = mb_strlen($content);
        $part = mb_substr($content, $offset, \Maho_Ai_Model_Chat_Attachment::READ_CHARS);
        $end = $offset + mb_strlen($part);
        $head = sprintf('%s (%s, %d characters, showing %d to %d%s)', $file['name'], $file['mime'], $total, $offset, $end, $end < $total ? sprintf('; call again with offset %d for the rest', $end) : '');

        return ['ok' => true, 'text' => $head . "\n\n" . $part];
    }
}
