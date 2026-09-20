<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use Mage;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;

#[AsCommand(
    name: 'serve',
    description: 'Run Maho with the built in web server',
)]
class Serve extends BaseMahoCommand
{
    public function __invoke(
        #[Argument(description: 'Default is 8000')]
        int $port = 8000,
        #[Option(description: 'Host/interface to bind')]
        string $host = '127.0.0.1',
    ): int {
        $docroot = MAHO_PUBLIC_DIR;

        // Single-threaded by default, which stalls anything that polls while a request is still
        // running (the admin reindex and cron dialogs, parallel asset loads)
        if (getenv('PHP_CLI_SERVER_WORKERS') === false) {
            putenv('PHP_CLI_SERVER_WORKERS=8');
        }

        // The CLI SAPI leaves OPcache off, so every request recompiles the whole bootstrap.
        // Timestamps stay validated, so an edit still applies immediately.
        $opcache = '-d opcache.enable_cli=1 -d opcache.validate_timestamps=1 -d opcache.revalidate_freq=0';

        passthru("php {$opcache} -S " . escapeshellarg("{$host}:{$port}") . ' -t ' . escapeshellarg($docroot));

        return Command::SUCCESS;
    }
}
