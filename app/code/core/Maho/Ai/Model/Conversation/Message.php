<?php

/**
 * One message of an admin assistant conversation: user text, assistant text, or a tool call record.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Ai
 */

declare(strict_types=1);

class Maho_Ai_Model_Conversation_Message extends Mage_Core_Model_Abstract
{
    public const ROLE_USER = 'user';
    public const ROLE_ASSISTANT = 'assistant';
    public const ROLE_TOOL = 'tool';

    public const TOOL_DONE = 'done';
    public const TOOL_PENDING = 'pending';
    public const TOOL_DENIED = 'denied';
    public const TOOL_CANCELLED = 'cancelled';
    public const TOOL_ERROR = 'error';

    #[\Override]
    protected function _construct(): void
    {
        $this->_init('ai/conversation_message');
    }

    public static function deniedResult(): string
    {
        return '{"error":"The administrator declined this action."}';
    }

    public static function cancelledResult(): string
    {
        return '{"error":"Superseded by a new message from the administrator; the action was not performed."}';
    }

    public function getConversationId(): ?int
    {
        $value = $this->getData('conversation_id');
        return $value === null ? null : (int) $value;
    }

    public function getRole(): ?string
    {
        $value = $this->getData('role');
        return $value === null ? null : (string) $value;
    }

    public function setRole(?string $value): static
    {
        return $this->setData('role', $value);
    }

    public function getContent(): ?string
    {
        $value = $this->getData('content');
        return $value === null ? null : (string) $value;
    }

    public function setContent(?string $value): static
    {
        return $this->setData('content', $value);
    }

    /**
     * @return list<array{id: string, name: string, arguments: array<string, mixed>, signature?: ?string}>
     */
    public function getToolCalls(): array
    {
        $json = $this->getData('tool_calls');
        if ($json === null || $json === '') {
            return [];
        }
        try {
            $decoded = Mage::helper('core')->jsonDecode((string) $json);
        } catch (\JsonException) {
            return [];
        }

        return is_array($decoded) ? array_values($decoded) : [];
    }

    /**
     * @param list<array{id: string, name: string, arguments: array<string, mixed>, signature?: ?string}>|null $value
     */
    public function setToolCalls(?array $value): static
    {
        return $this->setData('tool_calls', $value === null ? null : Mage::helper('core')->jsonEncode($value));
    }

    public function getToolCallId(): ?string
    {
        $value = $this->getData('tool_call_id');
        return $value === null ? null : (string) $value;
    }

    public function setToolCallId(?string $value): static
    {
        return $this->setData('tool_call_id', $value);
    }

    public function getToolName(): ?string
    {
        $value = $this->getData('tool_name');
        return $value === null ? null : (string) $value;
    }

    public function setToolName(?string $value): static
    {
        return $this->setData('tool_name', $value);
    }

    /**
     * @return array<string, mixed>
     */
    public function getToolArguments(): array
    {
        $json = $this->getData('tool_arguments');
        if ($json === null || $json === '') {
            return [];
        }
        try {
            $decoded = Mage::helper('core')->jsonDecode((string) $json);
        } catch (\JsonException) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string, mixed>|null $value
     */
    public function setToolArguments(?array $value): static
    {
        return $this->setData('tool_arguments', $value === null ? null : Mage::helper('core')->jsonEncode($value));
    }

    public function getToolStatus(): ?string
    {
        $value = $this->getData('tool_status');
        return $value === null ? null : (string) $value;
    }

    public function setToolStatus(?string $value): static
    {
        return $this->setData('tool_status', $value);
    }

    public function getIsWrite(): ?bool
    {
        $value = $this->getData('is_write');
        return $value === null ? null : (bool) $value;
    }

    public function setIsWrite(?bool $value = true): static
    {
        return $this->setData('is_write', $value);
    }

    public function getInputTokens(): ?int
    {
        $value = $this->getData('input_tokens');
        return $value === null ? null : (int) $value;
    }

    public function setInputTokens(?int $value): static
    {
        return $this->setData('input_tokens', $value);
    }

    public function getOutputTokens(): ?int
    {
        $value = $this->getData('output_tokens');
        return $value === null ? null : (int) $value;
    }

    public function setOutputTokens(?int $value): static
    {
        return $this->setData('output_tokens', $value);
    }

    #[\Override]
    protected function _beforeSave(): static
    {
        if (!$this->getId()) {
            $this->setData('created_at', Mage::app()->getLocale()->formatDateForDb('now'));
        }

        return parent::_beforeSave();
    }
}
