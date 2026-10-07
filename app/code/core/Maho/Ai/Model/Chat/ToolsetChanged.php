<?php

/**
 * Raised after a round that loaded tool sections, so the turn runner restarts the agent with them.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Ai
 */

declare(strict_types=1);

use Symfony\AI\Platform\Result\ToolCall;

/**
 * The agent runner resolves the tool list once per run, so a new section only reaches
 * the model in a new run. The round is stored before this is thrown.
 */
class Maho_Ai_Model_Chat_ToolsetChanged extends RuntimeException
{
    /**
     * @param list<ToolCall> $toolCalls every call of the round, in model order
     */
    public function __construct(public readonly array $toolCalls)
    {
        parent::__construct('The tool set changed; the agent must run again.');
    }
}
