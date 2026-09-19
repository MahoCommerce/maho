<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Storage;

use League\Flysystem\FilesystemAdapter;

/**
 * Builds the Flysystem adapter behind a mount. A module ships its own
 * implementation and names it in `<adapter><type>Vendor\Module\Factory</type>`.
 */
interface AdapterFactoryInterface
{
    /**
     * @throws StorageException when the mount options are incomplete or invalid
     */
    public function create(MountDefinition $definition): FilesystemAdapter;
}
