<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Install
 */

declare(strict_types=1);

class Mage_Install_Helper_Data extends Mage_Core_Helper_Abstract
{
    public const AVAILABLE_LANGUAGE_PACKS = [
        'de_DE', 'el_GR', 'es_ES', 'fr_FR', 'it_IT', 'nl_NL', 'pt_BR', 'pt_PT', 'ro_RO',
    ];

    protected $_moduleName = 'Mage_Install';

    private static ?string $composerBinary = null;
    private static ?string $phpBinary = null;
    private static bool $composerChecked = false;
    private static bool $phpChecked = false;

    /**
     * Find the PHP CLI binary, or return null if not available.
     */
    public function getPhpBinary(): ?string
    {
        if (self::$phpChecked) {
            return self::$phpBinary;
        }
        self::$phpChecked = true;

        // Prefer PHP's own bindir over $PATH: in a web (fpm/apache) context PHP_BINARY points
        // at php-fpm, so we look up the "php" CLI sibling rather than reusing the SAPI binary.
        self::$phpBinary = (new \Symfony\Component\Process\ExecutableFinder())
            ->find('php', null, [PHP_BINDIR, dirname(PHP_BINDIR) . '/bin', '/opt/homebrew/bin']);

        return self::$phpBinary;
    }

    /**
     * Find the composer binary, or return null if not available.
     */
    public function getComposerBinary(): ?string
    {
        if (self::$composerChecked) {
            return self::$composerBinary;
        }
        self::$composerChecked = true;

        // Check for composer.phar in project root
        $projectPhar = Mage::getBaseDir() . '/composer.phar';
        if (is_file($projectPhar)) {
            self::$composerBinary = $projectPhar;
            return self::$composerBinary;
        }

        // Search $PATH and common locations for the composer binary
        self::$composerBinary = (new \Symfony\Component\Process\ExecutableFinder())
            ->find('composer', null, [PHP_BINDIR, dirname(PHP_BINDIR) . '/bin', '/opt/homebrew/bin']);

        return self::$composerBinary;
    }

    public function isComposerAvailable(): bool
    {
        $php = $this->getPhpBinary();
        $composer = $this->getComposerBinary();

        if (!$php || !$composer) {
            return false;
        }

        try {
            $process = new \Symfony\Component\Process\Process([$php, $composer, '--version']);
            $process->setTimeout(10);
            $process->run();
            return $process->isSuccessful();
        } catch (\Exception $e) {
            return false;
        }
    }
}
