<?php

/**
 * Runs one AI task in a queue worker.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Ai
 */

declare(strict_types=1);

class Maho_Ai_Model_Task_QueueHandler
{
    /** The task keeps its own status and retries, so the handler never throws: a queue retry would run an agent twice. */
    #[Maho\Config\MessageHandler]
    public function __invoke(Maho_Ai_Model_Task_QueueMessage $message): void
    {
        try {
            new Maho_Ai_Model_TaskRunner()->processTask($message->taskId);
        } catch (Throwable $e) {
            Mage::logException($e);
        }
    }
}
