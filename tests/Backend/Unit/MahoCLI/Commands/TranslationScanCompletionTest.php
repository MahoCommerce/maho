<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package MahoCLI
 */

declare(strict_types=1);

use MahoCLI\Commands\TranslationsMissing;
use MahoCLI\Commands\TranslationsUnused;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\MahoBackendTestCase;

uses(MahoBackendTestCase::class);

function translationScanCompletionTempDir(): string
{
    $dir = sys_get_temp_dir() . '/maho-translation-scan-' . bin2hex(random_bytes(6));
    mkdir($dir, 0700);
    return $dir;
}

function translationScanCompletionRemoveDir(string $dir): void
{
    $files = glob($dir . '/*');
    if ($files === false) {
        return;
    }
    foreach ($files as $file) {
        unlink($file);
    }
    rmdir($dir);
}

it('refuses translation removal when a malformed source file makes the scan incomplete', function () {
    $dir = translationScanCompletionTempDir();
    try {
        $malformed = $dir . '/01-malformed.xml';
        $valid = $dir . '/02-valid.xml';
        $csv = $dir . '/translations.csv';
        file_put_contents($malformed, '<config><label translate="label"><label>Malformed only</label></config>');
        file_put_contents($valid, '<config><label translate="label"><label>Valid sibling</label></label></config>');
        $before = '"Valid sibling","Valid translation"' . "\r\n"
            . '"Malformed only","Used only in malformed XML"' . "\r\n"
            . '"Unused key","Unused translation"' . "\r\n";
        file_put_contents($csv, $before);

        $command = new class extends TranslationsUnused {
            /** @var list<string> */
            public array $files;
            public string $csv;

            #[\Override]
            protected function initMaho(): void {}

            /** @return list<string> */
            #[\Override]
            protected function getFiles(): array
            {
                return $this->files;
            }

            /** @return array<string, array<int, string>> */
            #[\Override]
            protected function getDefinedStrings(string $lang): array
            {
                return [$this->csv => ['Valid sibling', 'Malformed only', 'Unused key']];
            }
        };
        $command->csv = $csv;
        $command->files = [$malformed, $valid];

        $status = $command->__invoke(new BufferedOutput(), 'en_US', true);

        expect($status)->toBe(Command::FAILURE)
            ->and(file_get_contents($csv))->toBe($before);
    } finally {
        translationScanCompletionRemoveDir($dir);
    }
});

it('removes only unused CSV records after a complete scan and keeps retained bytes', function () {
    $dir = translationScanCompletionTempDir();
    try {
        $source = $dir . '/source.php';
        $csv = $dir . '/translations.csv';
        file_put_contents($source, "<?php __('keep, key'); __('multiline key');");
        $before = "\"remove, key\",\"Unused, text\"\r\n"
            . "\"keep, key\",\"Keep, text\"\r\n"
            . "# translator note\r\n"
            . "\"keep, key\",\"Duplicate retained row\"\r\n"
            . "\"remove, key\",\"Duplicate unused\"\r\n"
            . "\r\n"
            . "\"multiline key\",\"first line\nsecond line\"\r\n";
        $expected = "\"keep, key\",\"Keep, text\"\r\n"
            . "# translator note\r\n"
            . "\"keep, key\",\"Duplicate retained row\"\r\n"
            . "\r\n"
            . "\"multiline key\",\"first line\nsecond line\"\r\n";
        file_put_contents($csv, $before);

        $command = new class extends TranslationsUnused {
            public string $csv;
            public string $source;

            #[\Override]
            protected function initMaho(): void {}

            /** @return list<string> */
            #[\Override]
            protected function getFiles(): array
            {
                return [$this->source];
            }

            /** @return array<string, array<int, string>> */
            #[\Override]
            protected function getDefinedStrings(string $lang): array
            {
                return [$this->csv => ['remove, key', 'keep, key', 'multiline key']];
            }
        };
        $command->csv = $csv;
        $command->source = $source;

        $status = $command->__invoke(new BufferedOutput(), 'en_US', true);

        expect($status)->toBe(Command::SUCCESS)
            ->and(file_get_contents($csv))->toBe($expected);
    } finally {
        translationScanCompletionRemoveDir($dir);
    }
});

it('reports a failed or partial CSV write instead of a removed count', function () {
    $dir = translationScanCompletionTempDir();
    try {
        $source = $dir . '/source.php';
        $csv = $dir . '/translations.csv';
        $before = "\"remove, key\",\"Unused text\"\r\n\"keep, key\",\"Used text\"\r\n";
        file_put_contents($source, "<?php __('keep, key');");
        file_put_contents($csv, $before);

        $command = new class extends TranslationsUnused {
            public string $csv;
            public string $source;
            public int|false $writeResult = false;

            #[\Override]
            protected function initMaho(): void {}

            /** @return list<string> */
            #[\Override]
            protected function getFiles(): array
            {
                return [$this->source];
            }

            /** @return array<string, array<int, string>> */
            #[\Override]
            protected function getDefinedStrings(string $lang): array
            {
                return [$this->csv => ['remove, key', 'keep, key']];
            }

            #[\Override]
            protected function writeCsvFile(string $file, string $contents): int|false
            {
                return $this->writeResult;
            }
        };
        $command->csv = $csv;
        $command->source = $source;
        $output = new BufferedOutput();

        $failedStatus = $command->__invoke($output, 'en_US', true);
        $command->writeResult = strlen("\"keep, key\",\"Used text\"\r\n") - 1;
        $partialStatus = $command->__invoke($output, 'en_US', true);
        $display = $output->fetch();

        expect($failedStatus)->toBe(Command::FAILURE)
            ->and($partialStatus)->toBe(Command::FAILURE)
            ->and($display)->toContain('Failed to update translations')
            ->and($display)->not->toContain('Removed 1 unused translations')
            ->and(file_get_contents($csv))->toBe($before);
    } finally {
        translationScanCompletionRemoveDir($dir);
    }
});

it('continues the missing-translation report after malformed XML and exits incomplete', function () {
    $dir = translationScanCompletionTempDir();
    try {
        $malformed = $dir . '/01-malformed.xml';
        $valid = $dir . '/02-valid.xml';
        file_put_contents($malformed, '<config><broken></config>');
        file_put_contents($valid, '<config><label translate="label"><label>Valid sibling</label></label></config>');

        $command = new class extends TranslationsMissing {
            /** @var list<string> */
            public array $files;

            #[\Override]
            protected function initMaho(): void {}

            /** @return list<string> */
            #[\Override]
            protected function getFiles(): array
            {
                return $this->files;
            }

            /** @return array<string, array<int, string>> */
            #[\Override]
            protected function getDefinedStrings(string $lang): array
            {
                return [];
            }
        };
        $command->files = [$malformed, $valid];

        ob_start();
        try {
            $status = $command->__invoke('en_US');
            $display = ob_get_contents();
        } finally {
            ob_end_clean();
        }

        expect($status)->toBe(Command::FAILURE)
            ->and($display)->toContain('Valid sibling');
    } finally {
        translationScanCompletionRemoveDir($dir);
    }
});
