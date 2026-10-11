<?php

/**
 * The resized files of one source image of an entity, with the active settings of a store view.
 *
 * It builds URLs only: it reads no file and resizes nothing. The files exist because the save
 * of the entity, the import or image:resize created them, and the settings that the URLs use
 * become active only after their files exist.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

class Mage_Core_Model_Image_Set
{
    public const CACHE_FOLDER = 'cache';

    /** Cache tag of the stored sizes of the source images on a remote mount. */
    public const SIZE_CACHE_TAG = 'image_size';

    protected readonly string $source;

    protected readonly string $sourcePath;

    protected ?Mage_Core_Model_Image_Settings $settings = null;

    /** @var array{0: int, 1: int}|false|null */
    protected array|false|null $originalSize = null;

    /**
     * @param string $file path below the source folder of the entity, such as /a/b/shirt.jpg
     * @throws InvalidArgumentException when $file leaves the source folder or is a resized file
     */
    public function __construct(
        protected readonly string $entity,
        string $file,
        protected readonly ?int $storeId = null,
    ) {
        $this->source = Mage::getSingleton('core/image_config')->getSource($entity);
        $this->sourcePath = self::resolveSourcePath($this->source, $file)
            ?? throw new InvalidArgumentException("The image \"{$file}\" is not a source file of the entity \"{$entity}\".");
    }

    /**
     * The mount path of a source file of the folder $source, or null when $file leaves that
     * folder or is in its cache folder.
     * Examples with catalog/product: /a/b/x.jpg gives catalog/product/a/b/x.jpg.
     * /../../customer/x.jpg and /cache/3f9a/300/x.jpg give null.
     */
    public static function resolveSourcePath(string $source, string $file): ?string
    {
        $file = trim($file);
        if ($file === '' || $file === 'no_selection') {
            return null;
        }
        $path = \Maho\Io::getPathWithinMount(Mage::getStorage('media'), $source, '/' . ltrim($file, '/'));
        if ($path === null || $path === $source || str_starts_with($path, $source . '/' . self::CACHE_FOLDER . '/')) {
            return null;
        }
        return $path;
    }

    /**
     * The mount path of the file of $sourcePath for $settings and $width. $width must be one of
     * the widths of $settings.
     * Example: catalog/product/cache/3f9a1c2b0d4e/300/a/b/shirt.jpg.webp
     */
    public static function buildPath(string $source, string $sourcePath, Mage_Core_Model_Image_Settings $settings, int $width): string
    {
        return $source . '/' . self::CACHE_FOLDER . '/' . $settings->getHash() . '/' . $width
            . substr($sourcePath, strlen($source)) . $settings->extension;
    }

    public function getEntity(): string
    {
        return $this->entity;
    }

    /** The mount path of the source file. */
    public function getSourcePath(): string
    {
        return $this->sourcePath;
    }

    public function isSvg(): bool
    {
        return str_ends_with(strtolower($this->sourcePath), '.svg');
    }

    public function getSettings(): Mage_Core_Model_Image_Settings
    {
        return $this->settings ??= Mage::getSingleton('core/image_config')->getActiveSettings(
            $this->entity,
            $this->storeId ?? (int) Mage::app()->getStore()->getId(),
        );
    }

    /**
     * @return list<int>
     */
    public function getWidths(): array
    {
        return $this->getSettings()->widths;
    }

    /**
     * The mount path of the file for the smallest width that is $width or more. A width of 0
     * gives the largest file.
     */
    public function getPath(int $width = 0): string
    {
        if ($this->isSvg()) {
            return $this->sourcePath;
        }
        $settings = $this->getSettings();
        return self::buildPath($this->source, $this->sourcePath, $settings, $settings->getWidthFor($width));
    }

    /**
     * The URL of the file for the smallest width that is $width or more. A width of 0 gives the
     * largest file. An SVG source has no resized files, so its URL is the source.
     */
    public function getUrl(int $width = 0): string
    {
        return Mage::getStorage('media')->publicUrl($this->getPath($width));
    }

    /**
     * The value of a srcset attribute with every width, such as "https://…/150/x.jpg.webp 150w, …".
     * Empty for an SVG source.
     */
    public function getSrcset(): string
    {
        if ($this->isSvg()) {
            return '';
        }
        $mount = Mage::getStorage('media');
        $settings = $this->getSettings();
        return implode(', ', array_map(
            fn(int $width): string => $mount->publicUrl(self::buildPath($this->source, $this->sourcePath, $settings, $width)) . " {$width}w",
            $settings->widths,
        ));
    }

    /**
     * The width and the height of the source file. A remote mount reads the file once and keeps
     * the size in the cache. Null when the file cannot be read.
     *
     * @return array{0: int, 1: int}|null
     */
    public function getOriginalSize(): ?array
    {
        if ($this->originalSize === null) {
            $info = \Maho\Io::getImageSizeOnMount(Mage::getStorage('media'), $this->sourcePath, [self::SIZE_CACHE_TAG]);
            $this->originalSize = $info === false ? false : [(int) $info[0], (int) $info[1]];
        }
        return $this->originalSize === false ? null : $this->originalSize;
    }

    /**
     * The height of the file of $width: from the ratio of the settings, or from the ratio of the
     * source. Null when the settings keep the ratio of the source and the source cannot be read.
     */
    public function getHeightFor(int $width): ?int
    {
        $height = $this->getSettings()->getHeightFor($width);
        if ($height !== null) {
            return $height;
        }
        $size = $this->getOriginalSize();
        return $size === null || $size[0] === 0 ? null : (int) round($width * $size[1] / $size[0]);
    }

    /**
     * The attributes of an img element: src, srcset, sizes, width and height. The browser picks
     * the file from srcset with $sizes, and src is the largest file for a client without srcset.
     * The width and the height give the ratio, so the page reserves the space of the image
     * before it loads.
     *
     * @param string $sizes how wide the image shows in the layout, such as "(min-width: 768px) 33vw, 100vw"
     */
    public function getImgAttributes(string $sizes = '100vw'): string
    {
        $attributes = ['src' => $this->getUrl()];
        if (!$this->isSvg()) {
            $width = $this->getSettings()->getWidthFor(0);
            $attributes['srcset'] = $this->getSrcset();
            $attributes['sizes'] = $sizes;
            $height = $this->getHeightFor($width);
            if ($height !== null) {
                $attributes['width'] = (string) $width;
                $attributes['height'] = (string) $height;
            }
        }

        $html = [];
        foreach ($attributes as $name => $value) {
            $html[] = $name . '="' . htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE) . '"';
        }
        return implode(' ', $html);
    }
}
