<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use Mage;
use Symfony\Component\Console\Command\Command;

abstract class BaseMahoCommand extends Command
{
    /**
     * Whether to point out pending schema updates on boot. Off for the
     * command that applies them.
     */
    protected bool $warnOnPendingSchemaUpdates = true;

    protected function initMaho(): void
    {
        Mage::register('isSecureArea', true, true);
        Mage::app('admin');

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

    public function humanReadableSize(int $bytes): string
    {
        return Mage::helper('core')->formatFileSize($bytes);
    }
}
