<?php

/**
 * Maho
 *
 * @package    Maho_Intelligence
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

use React\EventLoop\LoopInterface;
use React\Stream\ReadableResourceStream;
use React\Stream\WritableResourceStream;

/**
 * JSON-RPC 2.0 transport over stdio with newline-delimited JSON (NDJSON).
 *
 * MCP uses bare JSON-RPC messages, one per line, without Content-Length framing.
 *
 * Reads:  {JSON}\n
 * Writes: {JSON}\n
 */
class Maho_Intelligence_Model_Mcp_Transport
{
    private ReadableResourceStream $stdin;
    private WritableResourceStream $stdout;
    private string $buffer = '';

    /** @var callable(array): void */
    private $onMessage;

    /** @var callable(): void */
    private $onClose;

    public function __construct(LoopInterface $loop)
    {
        $this->stdin = new ReadableResourceStream(STDIN, $loop);
        $this->stdout = new WritableResourceStream(STDOUT, $loop);
    }

    /**
     * @param callable(array): void $onMessage Called with decoded JSON-RPC message
     * @param callable(): void $onClose Called when stdin closes
     */
    public function listen(callable $onMessage, callable $onClose): void
    {
        $this->onMessage = $onMessage;
        $this->onClose = $onClose;

        $this->stdin->on('data', function (string $chunk): void {
            $this->buffer .= $chunk;
            $this->processBuffer();
        });

        $this->stdin->on('close', function (): void {
            ($this->onClose)();
        });
    }

    public function send(array $message): void
    {
        $json = json_encode($message, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            fwrite(STDERR, 'maho-intelligence: json_encode failed: ' . json_last_error_msg() . "\n");
            return;
        }
        $this->stdout->write($json . "\n");
    }

    public function close(): void
    {
        $this->stdin->close();
        $this->stdout->close();
    }

    private function processBuffer(): void
    {
        while (($newlinePos = strpos($this->buffer, "\n")) !== false) {
            $line = substr($this->buffer, 0, $newlinePos);
            $this->buffer = substr($this->buffer, $newlinePos + 1);

            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $decoded = json_decode($line, true);
            if (is_array($decoded)) {
                ($this->onMessage)($decoded);
            } else {
                fwrite(STDERR, "maho-intelligence: invalid JSON-RPC payload, skipping\n");
            }
        }
    }
}
