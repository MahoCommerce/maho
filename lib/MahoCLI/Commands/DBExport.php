<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package MahoCLI
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\Ask;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'db:export',
    description: 'Dump the database to a SQL file using the credentials from your local.xml file',
)]
class DBExport extends BaseMahoCommand
{
    use DatabaseDumpTrait;

    public function __invoke(
        OutputInterface $output,
        #[Argument(description: 'Path of the dump file to write, relative to the current directory')]
        #[Ask('Dump file:')]
        string $file,
        #[Option(description: 'Compression of the dump: gzip, zstd or none')]
        string $compression = 'gzip',
        #[Option(description: 'Overwrite the output file when it already exists', shortcut: 'f')]
        bool $force = false,
        #[Option(description: 'Print the resolved connection and the command that would run, without executing it')]
        bool $dryRun = false,
    ): int {
        if (!in_array($compression, self::COMPRESSIONS, true)) {
            $this->writeError($output, sprintf(
                'Invalid --compression value: %s (expected: %s)',
                $compression,
                implode(', ', self::COMPRESSIONS),
            ));
            return Command::INVALID;
        }

        if (is_file($file) && !$force && !$dryRun) {
            $this->writeError($output, "Output file already exists: {$file} (use --force to overwrite)");
            return Command::FAILURE;
        }

        try {
            $conn = $this->readConnectionConfig();
        } catch (\RuntimeException $e) {
            $this->writeError($output, $e->getMessage());
            return Command::FAILURE;
        }

        if (!in_array($conn['engine'], self::DUMP_ENGINES, true)) {
            $this->writeError($output, sprintf(
                'Unsupported database engine: %s (supported: %s)',
                $conn['engine'],
                implode(', ', self::DUMP_ENGINES),
            ));
            return Command::FAILURE;
        }

        // SQLite would create an empty database file and dump nothing, hiding a wrong path.
        if ($conn['engine'] === 'sqlite' && !is_file($this->sqliteDatabaseFile($conn['dbname']))) {
            $this->writeError($output, 'SQLite database file not found: ' . $this->sqliteDatabaseFile($conn['dbname']));
            return Command::FAILURE;
        }

        $missing = $this->missingBinaries($conn, $compression, true);
        if ($missing !== []) {
            $this->writeError($output, sprintf(
                'Missing required binary: %s. Install it, or run the dump from the database host.',
                implode(', ', $missing),
            ));
            return Command::FAILURE;
        }

        $command = $this->buildExportCommand($conn, $file, $compression, $this->progressViewer());

        if ($dryRun) {
            $this->renderDryRun($output, $conn, [$command]);
            return Command::SUCCESS;
        }

        $directory = dirname($file);
        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            $this->writeError($output, "Cannot create directory: {$directory}");
            return Command::FAILURE;
        }

        $output->writeln(sprintf(
            'Exporting database <info>%s</info> to <info>%s</info> (%s)...',
            $conn['dbname'],
            $file,
            $compression === 'none' ? 'uncompressed' : $compression,
        ));

        $partial = is_writable($directory) ? tempnam($directory, basename($file) . '.') : false;
        if ($partial === false) {
            $this->writeError($output, "Cannot write in directory: {$directory}");
            return Command::FAILURE;
        }
        $exitCode = $this->runShellCommand(
            $this->buildExportCommand($conn, $partial, $compression, $this->progressViewer()),
            $conn,
        );
        if ($exitCode !== 0) {
            if (is_file($partial)) {
                unlink($partial);
            }
            $this->writeError($output, 'Database export failed, the partial file has been removed.');
            return Command::FAILURE;
        }
        if (!@rename($partial, $file)) {
            @unlink($partial);
            $this->writeError($output, "Cannot move the dump to {$file}.");
            return Command::FAILURE;
        }

        $output->writeln(sprintf(
            '<info>Database exported successfully (%s).</info>',
            new \Mage_Core_Helper_Data()->formatFileSize((int) @filesize($file)),
        ));

        return Command::SUCCESS;
    }
}
