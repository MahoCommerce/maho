<?php

/**
 * Queue message for the creation of every missing resized file of an entity. Handled by
 * Mage_Core_Model_Image_ResizeMessageHandler.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

final readonly class Mage_Core_Model_Image_ResizeAllMessage
{
    public function __construct(
        public string $entity,
    ) {}
}
