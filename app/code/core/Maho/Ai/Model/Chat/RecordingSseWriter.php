<?php

/**
 * Keeps the end state and the error of a turn that runs without a browser.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Ai
 */

declare(strict_types=1);

class Maho_Ai_Model_Chat_RecordingSseWriter extends Maho_Ai_Model_Chat_NullSseWriter
{
    private string $state = Maho_Ai_Model_Chat_AgentRunner::STATE_ERROR;
    private string $error = '';

    #[\Override]
    public function event(string $name, array $data = []): void
    {
        if ($name === 'done') {
            $this->state = (string) ($data['state'] ?? Maho_Ai_Model_Chat_AgentRunner::STATE_ERROR);
        } elseif ($name === 'error') {
            $this->error = (string) ($data['message'] ?? '');
        }
    }

    public function state(): string
    {
        return $this->state;
    }

    public function error(): string
    {
        return $this->error;
    }
}
