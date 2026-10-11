<?php

/**
 * Resized image URLs for templates.
 *
 * Example: $image = $this->helper('core/image')->init('blog', $post->getImage());
 * then $image->getUrl(600) and $image->getSrcset().
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

class Mage_Core_Helper_Image extends Mage_Core_Helper_Abstract
{
    #[\Override]
    protected $_moduleName = 'Mage_Core';

    /**
     * The resized files of $file, an image of $entity, in the current store view. Null when
     * $file is empty or is not a source file of the entity.
     *
     * @param string|null $file path below the source folder of the entity, such as /a/b/shirt.jpg
     */
    public function init(string $entity, ?string $file, ?int $storeId = null): ?Mage_Core_Model_Image_Set
    {
        if ($file === null || $file === '' || $file === 'no_selection') {
            return null;
        }
        try {
            return new Mage_Core_Model_Image_Set($entity, $file, $storeId);
        } catch (InvalidArgumentException $e) {
            Mage::log($e->getMessage(), Mage::LOG_WARNING);
            return null;
        }
    }
}
