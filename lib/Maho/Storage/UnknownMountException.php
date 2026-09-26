<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Storage;

final class UnknownMountException extends StorageException
{
    /**
     * @param list<string> $known
     */
    public static function forName(string $name, array $known): self
    {
        return new self(sprintf(
            'Unknown storage mount "%s". Declared mounts: %s. A module declares one under <global><storage><mounts> in its config.xml.',
            $name,
            $known === [] ? '(none)' : implode(', ', $known),
        ));
    }
}
