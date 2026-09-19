<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Storage\Adapter;

use League\Flysystem\FilesystemAdapter;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\UnixVisibility\PortableVisibilityConverter;
use League\Flysystem\Visibility;
use Maho\Storage\AdapterFactoryInterface;
use Maho\Storage\MountDefinition;
use Maho\Storage\StorageException;

final class Local implements AdapterFactoryInterface
{
    #[\Override]
    public function create(MountDefinition $definition): FilesystemAdapter
    {
        if ($definition->path === null) {
            throw new StorageException(sprintf('Storage mount "%s" uses the local adapter and needs a <path>.', $definition->name));
        }

        // Same modes the rest of Maho writes with (Maho\File\Uploader chmods 0666
        // and mkdirs 0777), so a mount and legacy code agree on one disk.
        $visibility = PortableVisibilityConverter::fromArray([
            'file' => ['public' => 0666, 'private' => 0600],
            'dir' => ['public' => 0777, 'private' => 0700],
        ], Visibility::PUBLIC);

        return new LocalFilesystemAdapter($definition->path, $visibility);
    }
}
