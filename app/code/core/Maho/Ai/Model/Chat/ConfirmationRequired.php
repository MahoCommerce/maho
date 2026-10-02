<?php

/**
 * Raised when the model asked for a write tool that the administrator must confirm first.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Ai
 */

declare(strict_types=1);

use Symfony\AI\Agent\Toolbox\ToolResult;
use Symfony\AI\Platform\Result\ToolCall;

class Maho_Ai_Model_Chat_ConfirmationRequired extends RuntimeException
{
    /**
     * @param list<ToolCall> $toolCalls every call of the round, in model order
     * @param array<string, ToolResult> $results finished read calls, keyed by tool call id
     * @param list<ToolCall> $pending write calls that wait for the administrator
     */
    public function __construct(
        public readonly array $toolCalls,
        public readonly array $results,
        public readonly array $pending,
    ) {
        parent::__construct('The administrator must confirm a write before the assistant continues.');
    }
}
