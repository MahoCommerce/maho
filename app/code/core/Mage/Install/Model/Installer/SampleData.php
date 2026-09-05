<?php

/**
 * Sample data step of the web installer: downloads the package and imports it with progress written to a file the wizard polls.
 *
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Install
 */

declare(strict_types=1);

use Maho\Import\Reporter;
use Maho\Import\SampleData\Installer;
use Maho\Import\SampleData\Package;

class Mage_Install_Model_Installer_SampleData
{
    private const PROGRESS_FILE = 'sampledata_progress.json';

    public function getProgressFilePath(): string
    {
        return Mage::getBaseDir('var') . DS . self::PROGRESS_FILE;
    }

    /**
     * @return array<string, mixed>
     */
    public function getProgress(): array
    {
        $idle = ['phase' => 'idle', 'percent' => 0, 'message' => '', 'error' => null];
        $progressFile = $this->getProgressFilePath();
        if (!file_exists($progressFile)) {
            return $idle;
        }
        $data = json_decode((string) file_get_contents($progressFile), true);
        return is_array($data) ? $data : $idle;
    }

    public function isInstalling(): bool
    {
        return !in_array($this->getProgress()['phase'], ['idle', 'complete', 'error'], true);
    }

    public function clearProgress(): void
    {
        $progressFile = $this->getProgressFilePath();
        if (file_exists($progressFile)) {
            unlink($progressFile);
        }
    }

    public function updateProgress(string $phase, int $percent, string $message, ?string $error = null): void
    {
        file_put_contents($this->getProgressFilePath(), json_encode([
            'phase' => $phase,
            'percent' => $percent,
            'message' => $message,
            'error' => $error,
            'updated_at' => time(),
        ], JSON_PRETTY_PRINT));
    }

    public function install(): bool
    {
        $package = null;
        try {
            $this->updateProgress('downloading', 0, Mage::helper('install')->__('Downloading sample data...'));
            $package = Package::forBranch(Package::branchForVersion(Mage::getVersion()));

            $this->updateProgress('importing_data', 20, Mage::helper('install')->__('Importing sample data...'));
            (new Installer($this->reporter()))->install($package);

            $this->updateProgress('complete', 100, Mage::helper('install')->__('Sample data installed successfully!'));
            return true;
        } catch (Exception $e) {
            $this->updateProgress('error', $this->getProgress()['percent'] ?? 0, Mage::helper('install')->__('Installation failed'), $e->getMessage());
            throw $e;
        } finally {
            $package?->cleanup();
        }
    }

    /**
     * Maps the installer steps to the wizard phases: imports from 20 to 80 percent, reindex at 80, cache flush at 95.
     */
    private function reporter(): Reporter
    {
        return new readonly class ($this) implements Reporter {
            public function __construct(private Mage_Install_Model_Installer_SampleData $installer) {}

            #[\Override]
            public function info(string $message): void {}

            #[\Override]
            public function warning(string $message): void
            {
                Mage::log('[sample-data] ' . $message, Mage::LOG_WARNING);
            }

            #[\Override]
            public function progress(int $done, int $total, string $label = ''): void {}

            #[\Override]
            public function finish(): void {}

            #[\Override]
            public function step(int $done, int $total, string $label): void
            {
                if ($label === 'Reindex') {
                    $this->installer->updateProgress('reindexing', 80, Mage::helper('install')->__('Reindexing data...'));
                } elseif ($label === 'Cache') {
                    $this->installer->updateProgress('cache_flush', 95, Mage::helper('install')->__('Flushing caches...'));
                } else {
                    $percent = 20 + (int) (60 * ($done - 1) / max($total, 1));
                    $this->installer->updateProgress('importing_data', $percent, Mage::helper('install')->__('Importing %s...', $label));
                }
            }
        };
    }
}
