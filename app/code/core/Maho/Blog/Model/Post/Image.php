<?php

/**
 * The source files of the blog post image entity.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Blog
 */

declare(strict_types=1);

class Maho_Blog_Model_Post_Image implements Mage_Core_Model_Image_ProviderInterface
{
    public const ENTITY = 'blog';

    /**
     * The image of every post in every store, for storage:image:resize.
     *
     * @return list<string>
     */
    #[\Override]
    public function getImageFiles(): array
    {
        return Mage::getSingleton('core/image_resizer')->getAttributeImageFiles(Maho_Blog_Model_Post::ENTITY);
    }

    #[\Override]
    public function getPlaceholderUrl(int $width): ?string
    {
        return null;
    }
}
