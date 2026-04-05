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
 * JSON-RPC 2.0 transport over stdio with LSP Content-Length framing.
 *
 * Reads:  Content-Length: N\r\n\r\n{JSON payload of N bytes}
 * Writes: Content-Length: N\r\n\r\n{JSON payload of N bytes}
 */
class Maho_Intelligence_Model_Lsp_Transport
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
        $length = strlen($json);
        $this->stdout->write("Content-Length: {$length}\r\n\r\n{$json}");
    }

    public function close(): void
    {
        $this->stdin->close();
        $this->stdout->close();
    }

    private function processBuffer(): void
    {
        while (true) {
            $headerEnd = strpos($this->buffer, "\r\n\r\n");
            if ($headerEnd === false) {
                return;
            }

            $header = substr($this->buffer, 0, $headerEnd);
            if (!preg_match('/Content-Length:\s*(\d+)/i', $header, $matches)) {
                $this->buffer = substr($this->buffer, $headerEnd + 4);
                continue;
            }

            $contentLength = (int) $matches[1];
            $bodyStart = $headerEnd + 4;
            $totalNeeded = $bodyStart + $contentLength;

            if (strlen($this->buffer) < $totalNeeded) {
                return;
            }

            $body = substr($this->buffer, $bodyStart, $contentLength);
            $this->buffer = substr($this->buffer, $totalNeeded);

            $decoded = json_decode($body, true);
            if (is_array($decoded)) {
                ($this->onMessage)($decoded);
            } else {
                fwrite(STDERR, "maho-intelligence: invalid JSON-RPC payload, skipping\n");
            }
        }
    }
}
