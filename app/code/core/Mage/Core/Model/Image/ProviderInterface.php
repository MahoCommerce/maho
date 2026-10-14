<?php

/**
 * The source of the image files of an image entity.
 *
 * A module that declares an entity under global/images/entities/<entity> names a class that
 * implements this interface in <provider>. storage:image:resize and a change of the image
 * settings read every file of the entity from it.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

interface Mage_Core_Model_Image_ProviderInterface
{
    /**
     * Every source file of the entity, as a path below its source folder, such as /a/b/shirt.jpg.
     *
     * @return iterable<string>
     */
    public function getImageFiles(): iterable;
}
