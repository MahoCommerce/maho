<?php

/**
 * A module that runs a Playwright script through the shared browser runtime.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Browser;

/**
 * Register an implementation under global/browser/scanners/<name> (a model alias)
 * so sys:playwright:install provisions every scanner in one run.
 */
interface ScannerInterface
{
    public function browser(): Browser;

    /**
     * npm packages the scanner script imports, name => version constraint
     *
     * @return array<string, string>
     */
    public function packages(): array;
}
