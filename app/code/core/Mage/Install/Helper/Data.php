<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Install
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
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

        $searchDirs = array_filter(array_unique([
            PHP_BINDIR,
            dirname(PHP_BINDIR) . '/bin',
            '/usr/local/bin',
            '/usr/bin',
            '/opt/homebrew/bin',
        ]));

        foreach ($searchDirs as $dir) {
            $path = $dir . '/php';
            if (is_file($path) && is_executable($path)) {
                self::$phpBinary = $path;
                return self::$phpBinary;
            }
        }

        return null;
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

        // Search common locations for the composer binary
        $searchDirs = array_filter(array_unique([
            PHP_BINDIR,
            dirname(PHP_BINDIR) . '/bin',
            '/usr/local/bin',
            '/usr/bin',
            '/opt/homebrew/bin',
        ]));

        foreach ($searchDirs as $dir) {
            $path = $dir . '/composer';
            if (is_file($path) && is_executable($path)) {
                self::$composerBinary = $path;
                return self::$composerBinary;
            }
        }

        return null;
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
