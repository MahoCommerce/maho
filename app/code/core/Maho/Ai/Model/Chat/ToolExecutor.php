<?php

/**
 * Runs read tools at once and holds write tools for the administrator's confirmation.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Ai
 */

declare(strict_types=1);

use Maho\Ai\Api\Agent\McpToolbox;
use Symfony\AI\Agent\Execution\Update\Progress;
use Symfony\AI\Agent\Toolbox\ToolExecutorInterface;
use Symfony\AI\Agent\Toolbox\ToolResult;
use Symfony\AI\Platform\Result\ToolCall;

class Maho_Ai_Model_Chat_ToolExecutor implements ToolExecutorInterface
{
    /**
     * @param \Closure(list<ToolCall>, array<string, ToolResult>): void $onRoundComplete
     *   called when every call of a round ran, so the caller can persist the round
     */
    public function __construct(
        private readonly McpToolbox $toolbox,
        private readonly \Closure $onRoundComplete,
    ) {}

    /**
     * Read-only calls run in model order and report `tool_call` and `tool_result`
     * progress. The round stops with {@see Maho_Ai_Model_Chat_ConfirmationRequired} when
     * at least one call writes, so the chat service can persist the round and ask.
     */
    /**
     * @param list<ToolCall> $toolCalls
     * @return list<ToolCall> the calls with each id and each name-plus-arguments once, first occurrence kept
     */
    public static function unique(array $toolCalls): array
    {
        $seen = [];
        $unique = [];
        foreach ($toolCalls as $toolCall) {
            $key = self::firstId($toolCall, $seen);
            if ($key !== null) {
                continue;
            }
            $seen[$toolCall->getId()] = $toolCall->getId();
            $seen[self::payloadKey($toolCall)] = $toolCall->getId();
            $unique[] = $toolCall;
        }

        return $unique;
    }

    /**
     * The id of the earlier call this one repeats, by id or by name and arguments, or null.
     *
     * @param array<string, string> $seen
     */
    private static function firstId(ToolCall $toolCall, array $seen): ?string
    {
        return $seen[$toolCall->getId()] ?? $seen[self::payloadKey($toolCall)] ?? null;
    }

    private static function payloadKey(ToolCall $toolCall): string
    {
        return 'payload:' . $toolCall->getName() . ':' . json_encode($toolCall->getArguments());
    }

    #[\Override]
    public function execute(array $toolCalls): \Generator
    {
        // A streamed round can report the same completed call twice, and a model can ask for
        // the same call twice under two ids. Each distinct call runs once; a repeat gets the
        // same result so the caller still has one result per call.
        $results = [];
        $ordered = [];
        $pending = [];
        $seen = [];
        foreach ($toolCalls as $toolCall) {
            $id = $toolCall->getId();
            $firstId = self::firstId($toolCall, $seen);
            if ($firstId !== null) {
                if (isset($results[$firstId])) {
                    $ordered[] = new ToolResult($toolCall, $results[$firstId]->getResult());
                }
                continue;
            }
            $seen[$id] = $id;
            $seen[self::payloadKey($toolCall)] = $id;
            $write = !$this->toolbox->isReadOnly($toolCall->getName());
            if ($write && !$this->toolbox->isBackground()) {
                $pending[] = $toolCall;
                continue;
            }

            yield new Progress('tool_call', sprintf('Executing tool "%s".', $toolCall->getName()), $toolCall);
            // The administrator approved the job, not a deletion: a destructive tool waits for the chat.
            $result = $write && $this->toolbox->isDestructive($toolCall->getName())
                ? new ToolResult($toolCall, McpToolbox::ERROR_PREFIX . 'A destructive action is not allowed in a background job. Ask the administrator to run it in the chat.')
                : $this->toolbox->execute($toolCall);
            $results[$id] = $result;
            $ordered[] = $result;
            yield new Progress('tool_result', sprintf('Tool "%s" finished.', $toolCall->getName()), $result);
        }

        if ($pending !== []) {
            throw new Maho_Ai_Model_Chat_ConfirmationRequired(self::unique($toolCalls), $results, $pending);
        }

        ($this->onRoundComplete)(self::unique($toolCalls), $results);

        if ($this->toolbox->takeToolsetChange()) {
            throw new Maho_Ai_Model_Chat_ToolsetChanged(self::unique($toolCalls));
        }

        return $ordered;
    }
}
