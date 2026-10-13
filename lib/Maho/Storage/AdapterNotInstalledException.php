<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Storage;

final class AdapterNotInstalledException extends StorageException
{
    public static function forMount(string $mount, string $type, string $package): self
    {
        return new self(sprintf(
            'Storage mount "%s" uses the "%s" adapter, which requires the %s Composer package. Install it with: composer require %s',
            $mount,
            $type,
            $package,
            $package,
        ));
    }
}
