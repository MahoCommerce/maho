<?php

/**
 * Writes server-sent events for one chat turn.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Ai
 */

declare(strict_types=1);

class Maho_Ai_Model_Chat_SseWriter
{
    /** Output buffers up to this level stay open. A test raises it to capture the stream. */
    public static int $keepBufferLevel = 0;

    private bool $open = false;

    /**
     * @return array<string, string>
     */
    public static function headers(): array
    {
        return [
            'Content-Type' => 'text/event-stream; charset=utf-8',
            'Cache-Control' => 'no-cache, no-store',
            'X-Accel-Buffering' => 'no',
            'Content-Encoding' => 'identity',
        ];
    }

    /**
     * Release the PHP session before the first byte: a turn can run for minutes, and
     * the admin's other tabs block on the session lock until it is released.
     */
    public function open(): void
    {
        if ($this->open) {
            return;
        }
        $this->open = true;

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        set_time_limit(0);
        ignore_user_abort(false);
        while (ob_get_level() > self::$keepBufferLevel) {
            ob_end_flush();
        }
        // A 2 KiB comment line defeats the output buffering of most proxies.
        echo ':' . str_repeat(' ', 2048) . "\n\n";
        $this->flush();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function event(string $name, array $data = []): void
    {
        $this->open();
        $json = Mage::helper('core')->jsonEncode($data);
        echo 'event: ' . $name . "\n";
        foreach (explode("\n", $json) as $line) {
            echo 'data: ' . $line . "\n";
        }
        echo "\n";
        $this->flush();
    }

    public function isClientGone(): bool
    {
        return connection_aborted() !== 0;
    }

    private function flush(): void
    {
        if (ob_get_level() > self::$keepBufferLevel) {
            ob_flush();
        }
        flush();
    }
}
