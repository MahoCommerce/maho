<?php

/**
 * How a turn of the assistant runs: in the chat, as a background job, or on a schedule.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Ai
 */

declare(strict_types=1);

enum Maho_Ai_Model_Chat_RunMode: string
{
    /** The administrator watches: every write waits for a confirmation in the panel. */
    case Chat = 'chat';

    /** A job the administrator confirmed once: its updates of one record are approved in advance. */
    case Job = 'job';

    /** A scheduled task: nobody watches, so every write waits in the conversation for the administrator. */
    case Schedule = 'schedule';

    /** A worker has no browser to open a page in. */
    public function hasBrowser(): bool
    {
        return $this === self::Chat;
    }

    public function approvesWrites(): bool
    {
        return $this === self::Job;
    }
}
