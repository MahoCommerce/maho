<?php

/**
 * The source files of the swatch image entity: the swatch images in wysiwyg/swatches.
 *
 * A swatch image of a product is an image of its gallery, so it is a file of the product entity.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_ConfigurableSwatches
 */

declare(strict_types=1);

class Mage_ConfigurableSwatches_Model_Swatch_Image implements Mage_Core_Model_Image_ProviderInterface
{
    public const ENTITY = 'swatch';

    /** The slot of the swatches in the theme. */
    public const SLOT = 'listing';

    /**
     * Every swatch image in wysiwyg/swatches, for storage:image:resize.
     *
     * @return iterable<string>
     */
    #[\Override]
    public function getImageFiles(): iterable
    {
        $source = Mage_ConfigurableSwatches_Helper_Productimg::SWATCH_FALLBACK_MEDIA_DIR;
        $cache = $source . '/' . Mage_Core_Model_Image_Set::CACHE_FOLDER . '/';
        $mount = Mage::getStorage('media');
        try {
            if (!$mount->directoryExists($source)) {
                return;
            }
            foreach ($mount->listFiles($source) as $item) {
                $path = $item->path();
                if (!str_starts_with($path, $cache)) {
                    yield substr($path, strlen($source));
                }
            }
        } catch (\League\Flysystem\FilesystemException $e) {
            Mage::logException($e);
        }
    }

    #[\Override]
    public function getPlaceholderUrl(int $width): ?string
    {
        return null;
    }
}
