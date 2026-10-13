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
     * The image of every post in every store, for image:resize.
     *
     * @return list<string>
     */
    #[\Override]
    public function getImageFiles(): array
    {
        $attribute = Mage::getSingleton('eav/config')->getAttribute(Maho_Blog_Model_Post::ENTITY, 'image');
        if (!$attribute || !$attribute->getId()) {
            return [];
        }

        $adapter = Mage::getSingleton('core/resource')->getConnection('core_read');
        $files = $adapter->fetchCol($adapter->select()
            ->distinct()
            ->from($attribute->getBackend()->getTable(), ['value'])
            ->where('attribute_id = ?', (int) $attribute->getId()));

        return array_values(array_filter(array_map(strval(...), $files), fn(string $file): bool => $file !== ''));
    }
}
