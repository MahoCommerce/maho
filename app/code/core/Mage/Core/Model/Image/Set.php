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
     * The src, srcset and sizes attributes of an img element. The browser picks the file from
     * srcset with the sizes value of $slot. src is for a client without srcset, such as most email
     * clients: for a fixed sizes value such as "100px" it is the file for twice that width, else
     * the largest file.
     *
     * @param string $slot a slot of the theme, such as "thumbnail" or "listing", or a sizes value
     */
    public function getSrcAttributes(string $slot = 'banner'): string
    {
        $sizes = $this->getSizes($slot);
        $width = preg_match('/^\s*(\d+)px\s*$/', $sizes) ? 2 * $this->getDisplayWidth($slot) : 0;
        $attributes = ['src' => $this->getUrl($width)];
        if (!$this->isSvg()) {
            $attributes['srcset'] = $this->getSrcset();
            $attributes['sizes'] = $sizes;
        }
        return self::formatAttributes($attributes);
    }

    /**
     * The sizes value of $slot in the theme. A value that is not a slot name, such as "80px",
     * is a sizes value already. An unknown slot gives 100vw.
     */
    public function getSizes(string $slot): string
    {
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $slot)) {
            return $slot;
        }
        return Mage::getSingleton('core/image_config')->getSlotSizes(
            $slot,
            $this->storeId ?? (int) Mage::app()->getStore()->getId(),
        ) ?? '100vw';
    }

    /**
     * The attributes of getSrcAttributes(), with a width and a height. For a fixed slot such as
     * "100px", the width is that width, so a client without CSS, such as an email client, shows
     * the image at that size. For a fluid slot, the width is the largest width, and the CSS of the
     * theme sets the size: the attributes then only give the ratio, so the page reserves the
     * space of the image before it loads. The height follows the ratio of the settings, or of
     * the source.
     *
     * @param string $slot a slot of the theme, such as "thumbnail" or "listing", or a sizes value
     */
    public function getImgAttributes(string $slot = 'banner'): string
    {
        $html = $this->getSrcAttributes($slot);
        $width = $this->getDisplayWidth($slot);
        $height = $this->getHeightFor($width);
        return $height === null
            ? $html
            : $html . ' ' . self::formatAttributes(['width' => (string) $width, 'height' => (string) $height]);
    }

    /**
     * The width of a fixed slot such as "100px", or the largest width for a fluid slot.
     */
    public function getDisplayWidth(string $slot): int
    {
        return preg_match('/^\s*(\d+)px\s*$/', $this->getSizes($slot), $match)
            ? (int) $match[1]
            : $this->getSettings()->getWidthFor(0);
    }

    /**
     * @param array<string, string> $attributes
     */
    protected static function formatAttributes(array $attributes): string
    {
        $html = [];
        foreach ($attributes as $name => $value) {
            $html[] = $name . '="' . htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE) . '"';
        }
        return implode(' ', $html);
    }
}
