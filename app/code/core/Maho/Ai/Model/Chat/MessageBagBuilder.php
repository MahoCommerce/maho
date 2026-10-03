<?php

/**
 * Turns stored conversation messages into the Symfony AI message bag of the next request.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Ai
 */

declare(strict_types=1);

use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\Content\Image;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\ToolCall;

class Maho_Ai_Model_Chat_MessageBagBuilder
{
    /**
     * The last $limit messages, oldest first. The system prompt is not stored, the agent
     * adds it on every request.
     */
    public function build(Maho_Ai_Model_Conversation $conversation, int $limit): MessageBag
    {
        $messages = array_values($conversation->messagesCollection()->getItems());

        return $this->fromMessages($this->window($messages, $limit), (int) $conversation->getAdminUserId());
    }

    /**
     * @param list<Maho_Ai_Model_Conversation_Message> $messages oldest first
     */
    public function fromMessages(array $messages, int $adminId = 0): MessageBag
    {
        $bag = new MessageBag();
        foreach ($messages as $message) {
            $role = $message->getRole();
            $content = (string) $message->getContent();

            if ($role === Maho_Ai_Model_Conversation_Message::ROLE_USER) {
                $bag->add(Message::ofUser($content, ...$this->images($message, $adminId)));
                continue;
            }

            if ($role === Maho_Ai_Model_Conversation_Message::ROLE_ASSISTANT) {
                if ($message->getToolStatus() !== null) {
                    // A note about the turn (stopped, failed) is for the administrator only.
                    continue;
                }
                $toolCalls = $message->getToolCalls();
                if ($toolCalls === []) {
                    $bag->add(Message::ofAssistant($content));
                    continue;
                }
                $parts = $content === '' ? [] : [new Text($content)];
                foreach ($toolCalls as $call) {
                    $parts[] = $this->toolCall($call);
                }
                $bag->add(new AssistantMessage(...$parts));
                continue;
            }

            if ($role === Maho_Ai_Model_Conversation_Message::ROLE_TOOL) {
                $toolCall = new ToolCall(
                    (string) $message->getToolCallId(),
                    (string) $message->getToolName(),
                    $message->getToolArguments(),
                );
                $bag->add(Message::ofToolCall($toolCall, $this->toolContent($message)));
            }
        }

        return $bag;
    }

    /**
     * Cut the oldest messages, never inside a tool round: an assistant message that
     * carries tool calls and its tool results travel together or not at all.
     *
     * @param list<Maho_Ai_Model_Conversation_Message> $messages
     * @return list<Maho_Ai_Model_Conversation_Message>
     */
    public function window(array $messages, int $limit): array
    {
        $count = count($messages);
        if ($limit <= 0 || $count <= $limit) {
            return $messages;
        }

        $start = $count - $limit;
        while ($start < $count && $messages[$start]->getRole() === Maho_Ai_Model_Conversation_Message::ROLE_TOOL) {
            $start++;
        }

        return array_values(array_slice($messages, $start));
    }

    /**
     * @param array{id?: string, name?: string, arguments?: array<string, mixed>, signature?: ?string} $call
     */
    /**
     * The images attached to a user message, as content the model can see. A text file is
     * not sent: the model reads it with the attachment tool when it needs it.
     *
     * @return list<Image>
     */
    private function images(Maho_Ai_Model_Conversation_Message $message, int $adminId): array
    {
        $images = [];
        foreach ($message->getAttachments() as $file) {
            if (!Maho_Ai_Model_Chat_Attachment::isImage($file['mime'])) {
                continue;
            }
            $path = Maho_Ai_Model_Chat_Attachment::path($adminId, $file['id']);
            if ($path !== null) {
                $images[] = Image::fromFile($path);
            }
        }

        return $images;
    }

    private function toolCall(array $call): ToolCall
    {
        return new ToolCall(
            (string) ($call['id'] ?? ''),
            (string) ($call['name'] ?? ''),
            is_array($call['arguments'] ?? null) ? $call['arguments'] : [],
            isset($call['signature']) ? (string) $call['signature'] : null,
        );
    }

    private function toolContent(Maho_Ai_Model_Conversation_Message $message): string
    {
        $content = (string) $message->getContent();
        if ($content !== '') {
            return $content;
        }

        return match ($message->getToolStatus()) {
            Maho_Ai_Model_Conversation_Message::TOOL_DENIED => Maho_Ai_Model_Conversation_Message::deniedResult(),
            Maho_Ai_Model_Conversation_Message::TOOL_CANCELLED,
            Maho_Ai_Model_Conversation_Message::TOOL_PENDING => Maho_Ai_Model_Conversation_Message::cancelledResult(),
            default => '{"result":"empty"}',
        };
    }
}
