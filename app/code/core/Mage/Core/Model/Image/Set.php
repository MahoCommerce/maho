<?php

/**
 * The resized files of one source image of an entity, with the active settings of a store view.
 *
 * It builds URLs and resizes nothing. It reads only the size of the source image, when the
 * settings keep the ratio of the source. The files exist because the save of the entity, the
 * import or storage:image:resize created them, and the settings that the URLs use become active
 * only after their files exist.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

class Mage_Core_Model_Image_Set
{
    public const CACHE_FOLDER = 'cache';

    protected readonly string $source;

    protected readonly string $sourcePath;

    protected ?Mage_Core_Model_Image_Settings $settings = null;

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
     * the widths of $settings. An original in the output format keeps its name.
     * Example: catalog/product/cache/3f9a1c2b0d4e/300/a/b/shirt.jpg.webp, or .../shirt.webp
     */
    public static function buildPath(string $source, string $sourcePath, Mage_Core_Model_Image_Settings $settings, int $width): string
    {
        $file = substr($sourcePath, strlen($source));
        return $source . '/' . self::CACHE_FOLDER . '/' . $settings->getHash() . '/' . $width . $file
            . (str_ends_with(strtolower($file), $settings->extension) ? '' : $settings->extension);
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
     * The width and the height of the source file, from core_image_size. Null when the file
     * cannot be read, or when it is on a bucket and the queue did not read its size yet.
     *
     * @return array{0: int, 1: int}|null
     */
    public function getOriginalSize(): ?array
    {
        return Mage::getSingleton('core/image_size')->get(Mage::getStorage('media'), $this->sourcePath);
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
     * clients: it is the 2x file of the slot, or the largest file for an unknown slot.
     *
     * @param string $slot a slot of the theme, such as "thumbnail" or "listing"
     */
    public function getSrcAttributes(string $slot = 'banner'): string
    {
        $attributes = ['src' => $this->getUrl(2 * ($this->getSlotWidth($slot) ?? 0))];
        if (!$this->isSvg()) {
            $attributes['srcset'] = $this->getSrcset();
            $attributes['sizes'] = $this->getSizes($slot);
        }
        return self::formatAttributes($attributes);
    }

    /**
     * The sizes value of $slot: the full window when the window is narrower than the slot, else
     * the slot width. An unknown slot gives 100vw. No "auto": Chrome reads it as 100vw for an
     * image that is not lazy.
     * Example for a slot of 300: "(max-width: 300px) 100vw, 300px".
     */
    public function getSizes(string $slot): string
    {
        $width = $this->getSlotWidth($slot);
        return $width === null ? '100vw' : "(max-width: {$width}px) 100vw, {$width}px";
    }

    /**
     * The attributes of getSrcAttributes(), with a width and a height. The width is the width of
     * the slot, so a client without CSS, such as an email client, shows the image at that size.
     * The CSS of the theme can change the size: the attributes still give the ratio, so the page
     * keeps the space of the image before it loads. The height follows the ratio of the settings,
     * or of the source.
     *
     * @param string $slot a slot of the theme, such as "thumbnail" or "listing"
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
     * The width of $slot, or the largest width for an unknown slot.
     */
    public function getDisplayWidth(string $slot): int
    {
        return $this->getSlotWidth($slot) ?? $this->getSettings()->getWidthFor(0);
    }

    /**
     * The width in px of $slot for the entity in the theme of the store view, or null for an
     * unknown slot.
     */
    public function getSlotWidth(string $slot): ?int
    {
        return Mage::getSingleton('core/image_config')->getSlotWidth(
            $slot,
            $this->storeId ?? (int) Mage::app()->getStore()->getId(),
            $this->entity,
        );
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
