<?php

/**
 * Queue message for the creation of the resized files of some source files, handled by
 * Mage_Core_Model_Image_ResizeMessageHandler.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

final readonly class Mage_Core_Model_Image_ResizeMessage
{
    /**
     * @param list<string> $files paths below the source folder of $entity
     */
    public function __construct(
        public string $entity,
        public array $files,
    ) {}
}
