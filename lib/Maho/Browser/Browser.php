<?php

/**
 * Chromium builds the shared Playwright runtime can install.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Browser;

enum Browser: string
{
    /** Chrome headless shell: enough for scans that only read the DOM, about a third of the size */
    case HeadlessShell = 'chromium-headless-shell';

    /** Full Chromium: for scans where rendering fidelity matters */
    case Chromium = 'chromium';

    public static function fromOption(string $option): self
    {
        return match (strtolower(trim($option))) {
            'headless-shell', 'chromium-headless-shell', 'shell' => self::HeadlessShell,
            'chromium', 'chrome', 'full' => self::Chromium,
            default => throw new \InvalidArgumentException("Unknown browser \"$option\", expected headless-shell or chromium"),
        };
    }

    /** Directory name prefix Playwright uses under the browsers path */
    public function directoryPrefix(): string
    {
        return str_replace('-', '_', $this->value);
    }

    public function label(): string
    {
        return match ($this) {
            self::HeadlessShell => 'Chrome headless shell (about 350 MB)',
            self::Chromium => 'Chromium (about 650 MB)',
        };
    }
}
