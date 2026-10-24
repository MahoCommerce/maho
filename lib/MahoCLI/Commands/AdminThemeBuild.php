<?php

/**
 * Compiles admin theme CSS sources (src/) into their deployable css/ bundles.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package MahoCLI
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(
    name: 'dev:admin:theme:build',
    description: 'Compile the CSS sources (src/*.css) of every admin theme into their css/ bundles',
)]
class AdminThemeBuild extends FrontendThemeBuild
{
    protected const SKIN_PATH = 'public/skin/adminhtml';
}
