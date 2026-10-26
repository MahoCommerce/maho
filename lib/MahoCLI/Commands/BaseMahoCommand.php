<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use Mage;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Process\Process;

abstract class BaseMahoCommand extends Command
{
    /** A job of runJobs() prints this line after each item, so the progress bar advances. */
    protected const JOB_ITEM_LINE = 'file';

    /** A job of runJobs() prints its result as JSON on one line that starts with this. */
    protected const JOB_RESULT_PREFIX = 'result ';

    /**
     * Whether to point out pending schema updates on boot. Off for the
     * command that applies them.
     */
    protected bool $warnOnPendingSchemaUpdates = true;

    /**
     * Whether the command as a whole is one trace. Long-running commands set
     * this off and trace each unit of work instead, so spans are exported as
     * they happen rather than piling up in memory until the process exits.
     */
    protected bool $traceWholeCommand = true;

    private bool $mahoInitialized = false;

    /**
     * Boot Maho in the admin store. An #[Interact] method can call it before __invoke(), so it runs once.
     */
    protected function initMaho(): void
    {
        if ($this->mahoInitialized) {
            return;
        }
        $this->mahoInitialized = true;

        Mage::register('isSecureArea', true, true);
        Mage::app('admin');

        // OpenTelemetry: each CLI command is its own trace. Only the command name is
        // recorded — arguments can contain secrets (e.g. admin-user:changepassword).
        // flush() at shutdown ends the root span and any children left open.
        $tracer = Mage::getTracer();
        if ($this->traceWholeCommand && $tracer?->isEnabled()) {
            $tracer->startRootSpan('maho ' . (string) $this->getName(), [
                'maho.area' => 'cli',
                'process.title' => 'maho',
            ], null);
            register_shutdown_function(static fn() => Mage::getTracer()?->flush());
        }

        if ($this->warnOnPendingSchemaUpdates && Mage::app()->isSchemaUpdatePending()) {
            fwrite(STDERR, "Warning: the database is behind the installed code, run \"./maho migrate\".\n");
        }
    }

    /**
     * Whether a module is declared and active, read from the module
     * declaration XML without booting Maho, so module-gated commands can
     * decide their availability even before an install or DB connection
     */
    protected function isModuleActive(string $moduleName): bool
    {
        foreach (\Maho::globPackages('app/etc/modules/*.xml') as $file) {
            $xml = @simplexml_load_file($file);
            if ($xml === false) {
                continue;
            }
            $node = $xml->modules->{$moduleName} ?? null;
            if ($node !== null) {
                return in_array(strtolower((string) $node->active), ['true', '1'], true);
            }
        }
        return false;
    }

    /**
     * Share $items among $count parts, and keep their keys.
     *
     * @param array<array-key, mixed> $items
     * @return list<array<array-key, mixed>> the parts that are not empty
     */
    protected function splitIntoParts(array $items, int $count): array
    {
        $parts = array_fill(0, max(1, $count), []);
        $i = 0;
        foreach ($items as $key => $item) {
            $parts[$i++ % count($parts)][$key] = $item;
        }
        return array_values(array_filter($parts));
    }

    /**
     * Run the maho command $arguments once for each part of $parts, all at the same time. Each
     * process gets its part as JSON on stdin. It prints JOB_ITEM_LINE after each item, and its
     * result as JSON after JOB_RESULT_PREFIX.
     *
     * @param list<array<array-key, mixed>> $parts
     * @param list<string> $arguments
     * @param callable(array<string, mixed>): void $onResult
     * @param callable(int, string): void $onFailure gets the number and the error of a job that printed no result
     */
    protected function runJobs(ProgressBar $progress, array $parts, array $arguments, callable $onResult, callable $onFailure): void
    {
        $processes = [];
        foreach ($parts as $i => $part) {
            $process = new Process([PHP_BINARY, MAHO_ROOT_DIR . '/maho', ...$arguments], MAHO_ROOT_DIR, null, Mage::helper('core')->jsonEncode($part), null);
            $process->start();
            $processes[$i] = $process;
        }

        $buffers = array_fill_keys(array_keys($processes), '');
        $reported = [];
        while ($processes !== []) {
            foreach ($processes as $i => $process) {
                $running = $process->isRunning();
                $buffers[$i] .= $process->getIncrementalOutput();
                while (($end = strpos($buffers[$i], "\n")) !== false) {
                    $line = substr($buffers[$i], 0, $end);
                    $buffers[$i] = substr($buffers[$i], $end + 1);
                    if ($line === self::JOB_ITEM_LINE) {
                        $progress->advance();
                    } elseif (str_starts_with($line, self::JOB_RESULT_PREFIX)) {
                        $onResult(Mage::helper('core')->jsonDecode(substr($line, strlen(self::JOB_RESULT_PREFIX))));
                        $reported[$i] = true;
                    }
                }
                if ($running) {
                    continue;
                }
                if (!isset($reported[$i])) {
                    $onFailure($i, trim($process->getErrorOutput()) ?: "The job stopped with exit code {$process->getExitCode()}.");
                }
                unset($processes[$i]);
            }
            usleep(50_000);
        }
    }

    public function humanReadableSize(int $bytes): string
    {
        return Mage::helper('core')->formatFileSize($bytes);
    }

    /**
     * Queues carrying mail: newsletters ride their own so a blast can be given
     * a dedicated worker, but the email commands still cover them.
     *
     * @return list<string>
     */
    protected function mailQueues(): array
    {
        return [\Mage_Core_Model_Email_Queue::QUEUE_NAME, \Mage_Newsletter_Model_Queue::QUEUE_NAME];
    }
}
