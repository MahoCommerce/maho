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

    /** A test sets this to act as a browser that closed the stream. */
    public static bool $simulateClientGone = false;
    /** Test hook: Stop lands while this event is written. */
    public static ?string $simulateStopOnEvent = null;

    private bool $open = false;
    /** True once the browser left: the turn goes on and persists its result, and nothing is written. */
    private bool $detached = false;

    public function __construct(private readonly ?int $conversationId = null) {}

    /** The administrator pressed Stop: the turn ends at its next event. */
    public static function requestStop(int $conversationId): void
    {
        Mage::app()->saveCache('1', self::stopCacheId($conversationId), [Mage_Core_Model_Config::CACHE_TAG], 600);
    }

    private static function stopRequested(int $conversationId): bool
    {
        if (Mage::app()->loadCache(self::stopCacheId($conversationId)) === false) {
            return false;
        }
        Mage::app()->removeCache(self::stopCacheId($conversationId));

        return true;
    }

    private static function stopCacheId(int $conversationId): string
    {
        return 'ai_chat_stop_' . $conversationId;
    }

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
        // PHP would end the script at the next output after the browser left, and skip the
        // finally blocks that release the conversation lock. The writer checks and throws instead.
        ignore_user_abort(true);
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
        if ($this->detached) {
            $this->checkStop();

            return;
        }
        $json = Mage::helper('core')->jsonEncode($data);
        echo 'event: ' . $name . "\n";
        foreach (explode("\n", $json) as $line) {
            echo 'data: ' . $line . "\n";
        }
        echo "\n";
        $this->flush();
        if (self::$simulateStopOnEvent === $name) {
            throw new Maho_Ai_Model_Chat_ClientGone('The administrator stopped the turn.');
        }
    }

    public function isClientGone(): bool
    {
        return self::$simulateClientGone || connection_aborted() !== 0;
    }

    /**
     * A browser that left (a refresh, a closed tab) detaches the writer: the turn goes on,
     * so the answer is in the history when the panel comes back.
     *
     * @throws Maho_Ai_Model_Chat_ClientGone when the administrator pressed Stop
     */
    private function flush(): void
    {
        if (ob_get_level() > self::$keepBufferLevel) {
            ob_flush();
        }
        flush();
        if ($this->isClientGone()) {
            $this->detached = true;
        }
        $this->checkStop();
    }

    /**
     * @throws Maho_Ai_Model_Chat_ClientGone
     */
    private function checkStop(): void
    {
        if ($this->conversationId !== null && self::stopRequested($this->conversationId)) {
            throw new Maho_Ai_Model_Chat_ClientGone('The administrator stopped the turn.');
        }
    }
}
