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
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

#[AsCommand(
    name: 'db:import',
    description: 'Import a SQL dump into the database using the credentials from your local.xml file',
)]
class DBImport extends BaseMahoCommand
{
    use DatabaseDumpTrait;

    public function __invoke(
        InputInterface $input,
        OutputInterface $output,
        #[Argument(description: 'Path of the SQL dump file to load')]
        #[Ask('Dump file:')]
        string $file,
        #[Option(description: 'Compression of the dump: gzip, zstd or none')]
        string $compression = 'none',
        #[Option(description: 'Empty the database before loading the dump')]
        bool $dropTables = false,
        #[Option(description: 'Skip the question before --drop-tables empties the database', shortcut: 'f')]
        bool $force = false,
        #[Option(description: 'Print the resolved connection and the commands that would run, without executing them')]
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

        if (!is_file($file)) {
            $this->writeError($output, "File not found: {$file}");
            return Command::FAILURE;
        }

        if (!is_readable($file)) {
            $this->writeError($output, "Cannot read file: {$file}");
            return Command::FAILURE;
        }

        $detected = $this->detectCompression($file);
        if ($detected !== null && $detected !== $compression) {
            $this->writeError($output, sprintf(
                'The dump is %s-compressed: pass --compression=%s.',
                $detected,
                $detected,
            ));
            return Command::INVALID;
        }
        if ($detected === null && $compression !== 'none') {
            $this->writeError($output, sprintf(
                'The dump does not look %s-compressed: pass --compression=none for a plain SQL file.',
                $compression,
            ));
            return Command::INVALID;
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

        $missing = $this->missingBinaries($conn, $compression, false);
        if ($missing !== []) {
            $this->writeError($output, sprintf(
                'Missing required binary: %s. Install it, or load the dump from the database host.',
                implode(', ', $missing),
            ));
            return Command::FAILURE;
        }

        if ($dropTables && !$force && !$dryRun) {
            $question = new ConfirmationQuestion(sprintf('Empty database %s before loading the dump? [y/N] ', $conn['dbname']), false);
            /** @var QuestionHelper $helper */
            $helper = $this->getHelper('question');
            if (!$helper->ask($input, $output, $question)) {
                $this->writeError($output, 'Import cancelled. Use --force to skip this question.');
                return Command::FAILURE;
            }
        }

        $commands = [];
        if ($dropTables) {
            $commands = $this->buildResetCommands($conn);
        }
        $commands[] = $this->buildImportCommand($conn, $file, $compression, $this->progressViewer());

        if ($dryRun) {
            $this->renderDryRun($output, $conn, $commands);
            return Command::SUCCESS;
        }

        $resetCount = $dropTables ? count($commands) - 1 : 0;
        if ($resetCount > 0) {
            $output->writeln(sprintf('Emptying database <info>%s</info>...', $conn['dbname']));
        }
        foreach (array_slice($commands, 0, $resetCount) as $resetCommand) {
            if ($this->runShellCommand($resetCommand, $conn) !== 0) {
                $this->writeError($output, 'Could not empty the database, nothing has been imported.');
                return Command::FAILURE;
            }
        }

        $output->writeln(sprintf(
            'Importing <info>%s</info> into database <info>%s</info> (%s)...',
            $file,
            $conn['dbname'],
            $compression === 'none' ? 'uncompressed' : $compression,
        ));

        if ($this->runShellCommand($commands[$resetCount], $conn) !== 0) {
            $this->writeError($output, 'Database import failed.');
            return Command::FAILURE;
        }

        $output->writeln('<info>Database imported successfully.</info>');

        return Command::SUCCESS;
    }
}
