<?php

/**
 * Queue message for the read of the width and the height of image files on a mount. Handled by
 * Mage_Core_Model_Image_ResizeMessageHandler.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

final readonly class Mage_Core_Model_Image_SizeMessage
{
    /**
     * @param list<string> $paths paths on the mount
     */
    public function __construct(
        public string $mount,
        public array $paths,
    ) {}
}
