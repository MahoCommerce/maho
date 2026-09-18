<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(
    name: 'accessibility:install',
    description: 'Install the shared Playwright runtime the accessibility scanner needs (same as sys:playwright:install)',
)]
class AccessibilityInstall extends SysPlaywrightInstall
{
    #[\Override]
    public function isEnabled(): bool
    {
        return $this->isModuleActive('Maho_AccessibilityScan');
    }
}
