<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Tests\Backend\Unit\MahoCLI\Commands;

use MahoCLI\Commands\TranslationsMissing;
use MahoCLI\Commands\TranslationsUnused;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Process\ExecutableFinder;

final class TranslationSourceDiscoveryTest extends TestCase
{
    private string $directory;
    private string $originalDirectory;
    private string|false $originalPath;
    private string $grep;

    #[\Override]
    protected function setUp(): void
    {
        $directory = getcwd();
        self::assertIsString($directory);
        $this->originalDirectory = $directory;
        $this->originalPath = getenv('PATH');
        $grep = new ExecutableFinder()->find('grep');
        self::assertIsString($grep, 'The translation commands require grep.');
        $this->grep = $grep;
        $this->directory = sys_get_temp_dir() . '/maho-source-discovery-' . bin2hex(random_bytes(6));
        mkdir($this->directory, 0700);
        chdir($this->directory);
    }

    #[\Override]
    protected function tearDown(): void
    {
        chdir($this->originalDirectory);
        putenv($this->originalPath === false ? 'PATH' : 'PATH=' . $this->originalPath);
        foreach (scandir($this->directory) as $file) {
            if ($file !== '.' && $file !== '..') {
                unlink($this->directory . '/' . $file);
            }
        }
        rmdir($this->directory);
    }

    /** Execute real grep with an additional missing input, retaining its matches and exit status. */
    private function makeDiscoveryFail(string $extension): void
    {
        $script = "#!/bin/sh\nexec " . escapeshellarg($this->grep) . ' "$@" '
            . escapeshellarg($this->directory . '/missing.' . $extension) . "\n";
        file_put_contents($this->directory . '/grep', $script);
        chmod($this->directory . '/grep', 0700);
        putenv('PATH=' . $this->directory . PATH_SEPARATOR . ($this->originalPath ?: ''));
    }

    private function writeMatchingSources(): void
    {
        $translationFunction = '__';
        file_put_contents('source-é.php', "<?php {$translationFunction}('Found sibling');\n");
        file_put_contents('strings.xml', '<config><field translate="label"><label>XML sibling</label></field></config>');
    }

    /** @param list<string> $keys */
    private function unusedCommand(array $keys): TranslationsUnused
    {
        return new class ($keys) extends TranslationsUnused {
            /** @param list<string> $keys */
            public function __construct(private array $keys)
            {
                parent::__construct();
            }

            #[\Override]
            protected function initMaho(): void {}

            #[\Override]
            protected function getDefinedStrings(string $lang): array
            {
                return ['translations.csv' => $this->keys];
            }
        };
    }

    private function missingCommand(): TranslationsMissing
    {
        return new class extends TranslationsMissing {
            #[\Override]
            protected function initMaho(): void {}

            #[\Override]
            protected function getDefinedStrings(string $lang): array
            {
                return [];
            }
        };
    }

    #[DataProvider('discoveryErrorExtensions')]
    public function testPartialDiscoveryRefusesRemovalAfterARealGrepError(string $extension): void
    {
        $this->writeMatchingSources();
        $this->makeDiscoveryFail($extension);
        $before = "\"Found sibling\",\"Kept text\"\r\n\"Undiscovered key\",\"Keep until the scan succeeds\"\r\n";
        file_put_contents('translations.csv', $before);
        $command = $this->unusedCommand(['Found sibling', 'Undiscovered key']);

        ob_start();
        try {
            $status = $command->__invoke(new BufferedOutput(), 'en_US', true);
        } finally {
            ob_end_clean();
        }

        self::assertSame($before, file_get_contents('translations.csv'));
        self::assertSame(Command::FAILURE, $status);
    }

    /** @return array<string, array{string}> */
    public static function discoveryErrorExtensions(): array
    {
        return ['PHP discovery' => ['php'], 'XML discovery' => ['xml']];
    }

    public function testNoGrepMatchesAreACompleteScanThatCanRemoveUnusedRecords(): void
    {
        file_put_contents('translations.csv', "\"Unused key\",\"Unused text\"\r\n");
        $command = $this->unusedCommand(['Unused key']);

        ob_start();
        try {
            $status = $command->__invoke(new BufferedOutput(), 'en_US', true);
        } finally {
            ob_end_clean();
        }

        self::assertSame(Command::SUCCESS, $status);
        self::assertSame('', file_get_contents('translations.csv'));
    }

    public function testCompleteDiscoveryKeepsBothPhpAndXmlUses(): void
    {
        $this->writeMatchingSources();
        $kept = "\"Found sibling\",\"PHP text\"\r\n\"XML sibling\",\"XML text\"\r\n";
        file_put_contents('translations.csv', $kept . "\"Unused key\",\"Unused text\"\r\n");
        $command = $this->unusedCommand(['Found sibling', 'XML sibling', 'Unused key']);

        ob_start();
        try {
            $status = $command->__invoke(new BufferedOutput(), 'en_US', true);
        } finally {
            ob_end_clean();
        }

        self::assertSame(Command::SUCCESS, $status);
        self::assertSame($kept, file_get_contents('translations.csv'));
    }

    #[DataProvider('discoveryErrorExtensions')]
    public function testMissingScannerKeepsMatchesButFailsAfterARealDiscoveryError(string $extension): void
    {
        $this->writeMatchingSources();
        $this->makeDiscoveryFail($extension);
        $command = $this->missingCommand();

        ob_start();
        try {
            $status = $command->__invoke('en_US');
            $display = ob_get_contents();
        } finally {
            ob_end_clean();
        }

        self::assertSame(Command::FAILURE, $status);
        self::assertStringContainsString('Found sibling', $display);
        self::assertStringContainsString('XML sibling', $display);
    }

    public function testMissingScannerAcceptsNoMatchingSources(): void
    {
        $command = $this->missingCommand();

        ob_start();
        try {
            $status = $command->__invoke('en_US');
            $display = ob_get_contents();
        } finally {
            ob_end_clean();
        }

        self::assertSame(Command::SUCCESS, $status);
        self::assertSame('', $display);
    }
}
