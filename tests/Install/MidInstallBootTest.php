<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

/**
 * The wizard writes app/etc/local.xml at the Configuration step, so from there until the last
 * step the config is loaded while the store counts as not installed. The database is still empty
 * at that point, so a boot that runs the setup scripts or reads the stores throws, and Mage::run()
 * answers the exception with a redirect to the installer, which loops.
 * See https://github.com/MahoCommerce/maho/issues/1310
 */

it('serves the wizard without touching the database while the store is not installed', function () {
    $varDir = sys_get_temp_dir() . '/maho-mid-install-' . getmypid();
    mkdir($varDir, 0777, true);

    try {
        $output = (string) shell_exec(sprintf(
            '%s %s %s 2>&1',
            escapeshellarg(PHP_BINARY),
            escapeshellarg(__DIR__ . '/fixtures/mid-install-boot.php'),
            escapeshellarg($varDir),
        ));
    } finally {
        exec('rm -rf ' . escapeshellarg($varDir));
    }

    expect($output)->toContain('Maho Installation Wizard')
        ->toContain('SETUP_SCRIPTS_APPLIED=0 STORES_LOADED=0');
});
