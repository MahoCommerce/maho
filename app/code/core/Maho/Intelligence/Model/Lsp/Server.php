<?php

/**
 * Maho
 *
 * @package    Maho_Intelligence
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;

/**
 * LSP server for Maho Intelligence.
 *
 * Implements a subset of the Language Server Protocol:
 * - initialize / shutdown / exit
 * - textDocument/completion
 * - textDocument/definition
 * - textDocument/hover
 * - textDocument/didOpen, didChange, didClose
 */
class Maho_Intelligence_Model_Lsp_Server
{
    private const DIAGNOSTICS_DEBOUNCE_SECONDS = 0.3;

    private LoopInterface $loop;
    private Maho_Intelligence_Model_Lsp_Transport $transport;
    private Maho_Intelligence_Model_Lsp_DocumentStore $documents;
    private Maho_Intelligence_Model_Lsp_ContextDetector $detector;
    private Maho_Intelligence_Model_Registry $registry;

    private Maho_Intelligence_Model_Lsp_Handler_Completion $completionHandler;
    private Maho_Intelligence_Model_Lsp_Handler_Definition $definitionHandler;
    private Maho_Intelligence_Model_Lsp_Handler_Hover $hoverHandler;
    private Maho_Intelligence_Model_Lsp_Handler_Diagnostic $diagnosticHandler;

    /** @var array<string, TimerInterface> Pending diagnostic timers keyed by URI */
    private array $diagnosticTimers = [];


    public function run(): void
    {
        $this->loop = Loop::get();
        $loop = $this->loop;

        $this->registry = Mage::getModel('intelligence/registry');
        $this->documents = new Maho_Intelligence_Model_Lsp_DocumentStore();
        $this->detector = new Maho_Intelligence_Model_Lsp_ContextDetector();
        $this->transport = new Maho_Intelligence_Model_Lsp_Transport($loop);

        $this->completionHandler = new Maho_Intelligence_Model_Lsp_Handler_Completion(
            $this->registry,
            $this->detector,
            $this->documents,
        );
        $this->definitionHandler = new Maho_Intelligence_Model_Lsp_Handler_Definition(
            $this->registry,
            $this->detector,
            $this->documents,
        );
        $this->hoverHandler = new Maho_Intelligence_Model_Lsp_Handler_Hover(
            $this->registry,
            $this->detector,
            $this->documents,
        );
        $this->diagnosticHandler = new Maho_Intelligence_Model_Lsp_Handler_Diagnostic(
            $this->registry,
        );

        $this->transport->listen(
            onMessage: fn(array $msg) => $this->handleMessage($msg),
            onClose: fn() => $loop->stop(),
        );

        $loop->run();
    }

    private function handleMessage(array $message): void
    {
        $method = $message['method'] ?? null;
        $id = $message['id'] ?? null;
        $params = $message['params'] ?? [];

        // Notifications (no id) — don't send a response
        if ($id === null) {
            $this->handleNotification($method, $params);
            return;
        }

        // Requests (have id) — must send a response
        [$found, $result] = $this->handleRequest($method, $params);

        if (!$found) {
            $this->sendError($id, -32601, "Method not found: {$method}");
        } else {
            $this->sendResult($id, $result);
        }
    }

    private function handleNotification(?string $method, array $params): void
    {
        match ($method) {
            'initialized' => null,
            'textDocument/didOpen' => $this->handleDidOpen($params),
            'textDocument/didChange' => $this->handleDidChange($params),
            'textDocument/didClose' => $this->handleDidClose($params),
            'exit' => $this->loop->stop(),
            default => null,
        };
    }

    /**
     * @return array{bool, mixed} [methodFound, result]
     */
    private function handleRequest(?string $method, array $params): array
    {
        return match ($method) {
            'initialize' => [true, $this->handleInitialize($params)],
            'shutdown' => [true, $this->handleShutdown()],
            'textDocument/completion' => [true, $this->completionHandler->handle($params)],
            'textDocument/definition' => [true, $this->definitionHandler->handle($params)],
            'textDocument/hover' => [true, $this->hoverHandler->handle($params)],
            default => [false, null],
        };
    }

    private function handleInitialize(array $params): array
    {
        return [
            'capabilities' => [
                'textDocumentSync' => [
                    'openClose' => true,
                    'change' => 1, // Full sync
                ],
                'completionProvider' => [
                    'triggerCharacters' => ["'", '"'],
                    'resolveProvider' => false,
                ],
                'definitionProvider' => true,
                'hoverProvider' => true,
            ],
            'serverInfo' => [
                'name' => 'maho-intelligence-lsp',
                'version' => Mage::getVersion(),
            ],
        ];
    }

    private function handleShutdown(): null
    {
        return null;
    }

    private function handleDidOpen(array $params): void
    {
        $uri = $params['textDocument']['uri'];
        $text = $params['textDocument']['text'];
        $this->documents->open($uri, $text);
        $this->scheduleDiagnostics($uri, $text);
    }

    private function handleDidChange(array $params): void
    {
        $uri = $params['textDocument']['uri'] ?? '';
        $changes = $params['contentChanges'] ?? [];

        if (!empty($changes)) {
            $lastChange = end($changes);
            $text = $lastChange['text'];
            $this->documents->change($uri, $text);
            $this->scheduleDiagnostics($uri, $text);
        }
    }

    private function handleDidClose(array $params): void
    {
        $uri = $params['textDocument']['uri'];
        $this->documents->close($uri);
        // Clear diagnostics for closed document
        $this->sendNotification('textDocument/publishDiagnostics', [
            'uri' => $uri,
            'diagnostics' => [],
        ]);
    }

    private function scheduleDiagnostics(string $uri, string $text): void
    {
        if (isset($this->diagnosticTimers[$uri])) {
            $this->loop->cancelTimer($this->diagnosticTimers[$uri]);
        }

        $this->diagnosticTimers[$uri] = $this->loop->addTimer(
            self::DIAGNOSTICS_DEBOUNCE_SECONDS,
            function () use ($uri, $text): void {
                unset($this->diagnosticTimers[$uri]);
                $diagnostics = $this->diagnosticHandler->diagnose($uri, $text);
                $this->sendNotification('textDocument/publishDiagnostics', [
                    'uri' => $uri,
                    'diagnostics' => $diagnostics,
                ]);
            },
        );
    }

    private function sendResult(int|string $id, mixed $result): void
    {
        $this->transport->send([
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => $result,
        ]);
    }

    private function sendError(int|string $id, int $code, string $message): void
    {
        $this->transport->send([
            'jsonrpc' => '2.0',
            'id' => $id,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ]);
    }

    private function sendNotification(string $method, array $params): void
    {
        $this->transport->send([
            'jsonrpc' => '2.0',
            'method' => $method,
            'params' => $params,
        ]);
    }
}
