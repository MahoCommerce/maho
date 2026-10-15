<?php

/**
 * The settings that decide how the resized files of one image entity look in a store view.
 *
 * The widths are a folder of the cache path, and the hash covers every other setting. So two
 * store views with the same settings share their files, and a change of any setting gives new
 * URLs.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

final readonly class Mage_Core_Model_Image_Settings
{
    public const FIT_CONTAIN = 'contain';
    public const FIT_COVER = 'cover';

    /** Output formats that can keep the transparent areas of the source. */
    private const ALPHA_EXTENSIONS = ['.png', '.webp', '.avif', '.gif'];

    /**
     * @param list<int> $widths in ascending order
     * @param array{0: int, 1: int}|null $ratio width and height of the box, null for the ratio of the source
     * @param array{path: string, position: string, width: int|null, height: int|null, opacity: int}|null $watermark
     *        the path is the mount path of the watermark file
     */
    public function __construct(
        public array $widths,
        public ?array $ratio = null,
        public string $fit = self::FIT_CONTAIN,
        public int $quality = 85,
        public string $background = 'ffffff',
        public string $extension = '.webp',
        public ?array $watermark = null,
    ) {
        if ($widths === []) {
            throw new InvalidArgumentException('An image entity needs at least one width.');
        }
    }

    /**
     * Read the settings that toArray() wrote, or return null when $data has no widths.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): ?self
    {
        $widths = self::parseWidths($data['widths'] ?? []);
        if ($widths === []) {
            return null;
        }

        return new self(
            widths: $widths,
            ratio: is_array($data['ratio'] ?? null) ? [(int) $data['ratio'][0], (int) $data['ratio'][1]] : null,
            fit: ($data['fit'] ?? '') === self::FIT_COVER ? self::FIT_COVER : self::FIT_CONTAIN,
            quality: (int) ($data['quality'] ?? 85),
            background: (string) ($data['background'] ?? 'ffffff'),
            extension: (string) ($data['extension'] ?? '.webp'),
            watermark: self::parseWatermark($data['watermark'] ?? null),
        );
    }

    /**
     * @return array{path: string, position: string, width: int|null, height: int|null, opacity: int}|null
     */
    private static function parseWatermark(mixed $value): ?array
    {
        if (!is_array($value) || !is_string($value['path'] ?? null) || $value['path'] === '') {
            return null;
        }
        return [
            'path' => $value['path'],
            'position' => (string) ($value['position'] ?? 'stretch'),
            'width' => isset($value['width']) ? (int) $value['width'] : null,
            'height' => isset($value['height']) ? (int) $value['height'] : null,
            'opacity' => (int) ($value['opacity'] ?? 70),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'widths' => $this->widths,
            'ratio' => $this->ratio,
            'fit' => $this->fit,
            'quality' => $this->quality,
            'background' => $this->background,
            'extension' => $this->extension,
            'watermark' => $this->watermark,
        ];
    }

    /**
     * Turn "300, 150,600" or a list into ascending unique positive widths.
     *
     * @param string|array<int|string> $widths
     * @return list<int>
     */
    public static function parseWidths(string|array $widths): array
    {
        if (is_string($widths)) {
            $widths = explode(',', $widths);
        }
        $result = array_values(array_unique(array_filter(array_map(intval(...), $widths), fn(int $width): bool => $width > 0)));
        sort($result);
        return $result;
    }

    /**
     * Turn "1:1" or "3:4" into a ratio. "original", an empty value and a value that is not a
     * ratio give null.
     *
     * @return array{0: int, 1: int}|null
     */
    public static function parseRatio(string $ratio): ?array
    {
        if (!preg_match('/^\s*(\d+)\s*:\s*(\d+)\s*$/', $ratio, $match) || (int) $match[1] === 0 || (int) $match[2] === 0) {
            return null;
        }
        return [(int) $match[1], (int) $match[2]];
    }

    /**
     * The folder of the files that these settings make, without the width. It changes when any
     * setting other than the widths changes.
     */
    public function getHash(): string
    {
        $data = $this->toArray();
        unset($data['widths']);
        return substr(md5((string) json_encode($data)), 0, 12);
    }

    /**
     * The smallest width that is $width or more, or the largest width when none is. A width of
     * 0 or less gives the largest width.
     */
    public function getWidthFor(int $width): int
    {
        if ($width > 0) {
            foreach ($this->widths as $candidate) {
                if ($candidate >= $width) {
                    return $candidate;
                }
            }
        }
        return array_last($this->widths);
    }

    /**
     * The height of the box for $width, or null when the files keep the ratio of the source.
     */
    public function getHeightFor(int $width): ?int
    {
        return $this->ratio === null ? null : (int) round($width * $this->ratio[1] / $this->ratio[0]);
    }

    public function keepsTransparency(): bool
    {
        return in_array($this->extension, self::ALPHA_EXTENSIONS, true);
    }
}
