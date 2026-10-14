<?php

/**
 * The image entities and the settings of their resized files in each store view.
 *
 * A module declares an entity under global/images/entities/<entity>/source, the folder of its
 * originals on the media mount. The theme of a store view gives the slots and, for each
 * entity, the ratio and the fit, in the <images> node of its etc/theme.xml or of a parent
 * theme. A slot is the largest width in px of the images in one place of the layout, and
 * each image has a file at 1x and 2x of each slot. An entity can name the slots that it uses and
 * give its own width to a slot. The entity node can name the store config path of its
 * <background> color, and the prefix of its <watermark> settings, such as design/watermark/image.
 * The output format and the quality are the same for the whole installation.
 *
 * Two sets of settings exist for each store view and entity. The live settings come from the
 * theme and the store config now. The active settings are the ones that the URLs use. A
 * change of the live settings becomes active only after the files for it exist, so a page
 * never links to a file that is not there yet.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

class Mage_Core_Model_Image_Config
{
    public const XML_PATH_ENTITIES = 'global/images/entities';

    /** Store config path of the active settings of an entity, followed by the entity name. */
    public const XML_PATH_ACTIVE = 'images/active/';

    public const CACHE_TAG = 'image_settings';

    /** The theme that every theme chain ends with, as in the design fallback. */
    public const BASE_THEME = 'base/default';

    /** @var array<string, Mage_Core_Model_Image_Settings> */
    protected array $liveSettings = [];

    /** @var array<string, Mage_Core_Model_Image_Settings> */
    protected array $activeSettings = [];

    /** @var array<int, array{slots?: array<string, int>, entities?: array<string, array{ratio?: string, fit?: string, slots?: array<string, int>}>}> */
    protected array $themeNodes = [];

    /**
     * @return array<string, string> the source folder of each entity, by entity
     */
    public function getEntities(): array
    {
        $entities = [];
        $node = Mage::getConfig()->getNode(self::XML_PATH_ENTITIES);
        foreach ($node ? $node->children() : [] as $name => $entity) {
            $source = trim((string) $entity->source, '/');
            if ($source !== '') {
                $entities[(string) $name] = $source;
            }
        }
        return $entities;
    }

    /**
     * The source folder of $entity on the media mount, such as catalog/product.
     *
     * @throws InvalidArgumentException when no module declares $entity
     */
    public function getSource(string $entity): string
    {
        return $this->getEntities()[$entity]
            ?? throw new InvalidArgumentException("No module declares the image entity \"{$entity}\".");
    }

    /**
     * The settings that the URLs of $entity use in the store view.
     */
    public function getActiveSettings(string $entity, int $storeId): Mage_Core_Model_Image_Settings
    {
        $key = "{$entity}/{$storeId}";
        if (isset($this->activeSettings[$key])) {
            return $this->activeSettings[$key];
        }

        $stored = (string) Mage::getStoreConfig(self::XML_PATH_ACTIVE . $entity, $storeId);
        $settings = $stored !== '' ? Mage_Core_Model_Image_Settings::fromArray((array) json_decode($stored, true)) : null;

        return $this->activeSettings[$key] = $settings ?? $this->getLiveSettings($entity, $storeId);
    }

    /**
     * The settings that the theme and the store config of the store view give now. A remote
     * mount checks the watermark file, so the result stays in the app cache.
     */
    public function getLiveSettings(string $entity, int $storeId): Mage_Core_Model_Image_Settings
    {
        $key = "{$entity}/{$storeId}";
        if (isset($this->liveSettings[$key])) {
            return $this->liveSettings[$key];
        }

        $cacheId = 'image_settings_' . $entity . '_' . $storeId;
        $cached = Mage::app()->useCache('config') ? Mage::app()->loadCache($cacheId) : false;
        $settings = is_string($cached) && $cached !== ''
            ? Mage_Core_Model_Image_Settings::fromArray((array) json_decode($cached, true))
            : null;

        if ($settings === null) {
            $settings = $this->buildLiveSettings($entity, $storeId);
            if (Mage::app()->useCache('config')) {
                Mage::app()->saveCache(
                    (string) json_encode($settings->toArray()),
                    $cacheId,
                    [self::CACHE_TAG, Mage_Core_Model_Config::CACHE_TAG],
                );
            }
        }

        return $this->liveSettings[$key] = $settings;
    }

    /**
     * The width in px of a slot of the theme of the store view, such as 150 for thumbnail. A
     * slot names a place where images show, so that a template does not hold the width of its
     * layout. The width that $entity gives to the slot comes first. Null when no theme in the
     * chain declares the slot.
     */
    public function getSlotWidth(string $slot, int $storeId, ?string $entity = null): ?int
    {
        return ($entity === null ? null : $this->getEntitySlots($entity, $storeId)[$slot] ?? null)
            ?? $this->getThemeNode($storeId)['slots'][$slot]
            ?? null;
    }

    /**
     * The slots that the images of $entity use, with their width in px. An entity without its
     * own list uses every slot of the theme. A slot of the list without a width takes the width
     * of the theme, and a slot that the theme does not declare is ignored.
     *
     * @return array<string, int>
     */
    public function getEntitySlots(string $entity, int $storeId): array
    {
        $theme = $this->getThemeNode($storeId);
        $themeSlots = $theme['slots'] ?? [];
        $own = $theme['entities'][$entity]['slots'] ?? null;
        if ($own === null) {
            return $themeSlots;
        }

        $slots = [];
        foreach ($own as $name => $width) {
            $width = $width > 0 ? $width : $themeSlots[$name] ?? 0;
            if ($width > 0) {
                $slots[$name] = $width;
            }
        }
        return $slots ?: $themeSlots;
    }

    /**
     * Make the given settings the active settings of $entity, for each store view in the
     * list. Call it only after the files for the settings exist. The config reloads once.
     *
     * @param array<int, Mage_Core_Model_Image_Settings> $settingsByStore
     */
    public function activate(string $entity, array $settingsByStore): void
    {
        if ($settingsByStore === []) {
            return;
        }
        foreach ($settingsByStore as $storeId => $settings) {
            Mage::getConfig()->saveConfig(
                self::XML_PATH_ACTIVE . $entity,
                (string) json_encode($settings->toArray()),
                'stores',
                (int) $storeId,
            );
        }
        Mage::getConfig()->reinit();
        Mage::app()->reinitStores();
        Mage::app()->cleanCache([Mage_Core_Block_Abstract::CACHE_GROUP]);
        $this->activeSettings = [];
    }

    /**
     * The different settings of $entity over all store views, active and live, by hash. A
     * file of an entity must exist for each of them.
     *
     * @return array<string, Mage_Core_Model_Image_Settings>
     */
    public function getAllSettings(string $entity): array
    {
        $result = [];
        foreach ($this->getStoreIds() as $storeId) {
            foreach ([$this->getActiveSettings($entity, $storeId), $this->getLiveSettings($entity, $storeId)] as $settings) {
                $hash = $settings->getHash();
                $result[$hash] = isset($result[$hash])
                    ? $this->mergeWidths($result[$hash], $settings)
                    : $settings;
            }
        }
        return $result;
    }

    /**
     * The store views whose live settings of $entity are not active yet, or that have no stored
     * active settings.
     *
     * @return list<int>
     */
    public function getPendingStoreIds(string $entity): array
    {
        $pending = [];
        foreach ($this->getStoreIds() as $storeId) {
            if ((string) Mage::getStoreConfig(self::XML_PATH_ACTIVE . $entity, $storeId) === ''
                || $this->getActiveSettings($entity, $storeId) != $this->getLiveSettings($entity, $storeId)
            ) {
                $pending[] = $storeId;
            }
        }
        return $pending;
    }

    /**
     * Forget the settings that this object and the app cache hold, after a change of a theme
     * or of the store config.
     */
    public function reset(): void
    {
        $this->liveSettings = [];
        $this->activeSettings = [];
        $this->themeNodes = [];
        Mage::app()->cleanCache([self::CACHE_TAG]);
    }

    /**
     * @return list<int> the ids of the store views, without the admin store
     */
    public function getStoreIds(): array
    {
        return array_values(array_map(fn($store): int => (int) $store->getId(), Mage::app()->getStores()));
    }

    protected function buildLiveSettings(string $entity, int $storeId): Mage_Core_Model_Image_Settings
    {
        $entityNode = $this->getThemeNode($storeId)['entities'][$entity] ?? [];
        $slots = array_values($this->getEntitySlots($entity, $storeId));
        $config = Mage::getConfig()->getNode(self::XML_PATH_ENTITIES . "/{$entity}");
        $backgroundPath = trim((string) $config?->background);
        $background = $backgroundPath === '' ? '' : ltrim((string) Mage::getStoreConfig($backgroundPath, $storeId), '#');
        $watermarkPrefix = trim((string) $config?->watermark);

        return new Mage_Core_Model_Image_Settings(
            widths: Mage_Core_Model_Image_Settings::parseWidths([...$slots, ...array_map(fn(int $width): int => 2 * $width, $slots)]),
            ratio: Mage_Core_Model_Image_Settings::parseRatio($entityNode['ratio'] ?? ''),
            fit: ($entityNode['fit'] ?? '') === Mage_Core_Model_Image_Settings::FIT_COVER
                ? Mage_Core_Model_Image_Settings::FIT_COVER
                : Mage_Core_Model_Image_Settings::FIT_CONTAIN,
            quality: (int) Mage::getStoreConfig('system/media_storage_configuration/image_quality') ?: 85,
            background: preg_match('/^[0-9a-f]{6}$/i', $background) ? strtolower($background) : 'ffffff',
            extension: Maho::getConfiguredImageExtension(),
            watermark: $watermarkPrefix === '' ? null : $this->getWatermark($entity, $watermarkPrefix, $storeId),
        );
    }

    /**
     * @return array{slots?: array<string, int>, entities?: array<string, array{ratio?: string, fit?: string, slots?: array<string, int>}>}
     */
    protected function getThemeNode(int $storeId): array
    {
        return $this->themeNodes[$storeId] ??= $this->getThemeImagesNode($storeId);
    }

    /**
     * The <images> settings of the skin theme of the store view. A theme without a value takes
     * the value of its parent theme, and the last parent takes the value of base/default. As in the
     * design fallback, when the theme declares no parent, only the default theme of the store view
     * and then the default theme of the package come before base/default. The slot list of an entity
     * is one value: the list of a child theme replaces the list of its parent.
     *
     * @return array{slots?: array<string, int>, entities?: array<string, array{ratio?: string, fit?: string, slots?: array<string, int>}>}
     */
    protected function getThemeImagesNode(int $storeId): array
    {
        $designConfig = Mage::getSingleton('core/design_config');
        $area = Mage_Core_Model_Design_Package::DEFAULT_AREA;
        $package = (string) Mage::getStoreConfig('design/package/name', $storeId) ?: Mage_Core_Model_Design_Package::DEFAULT_PACKAGE;
        $defaultTheme = (string) Mage::getStoreConfig('design/theme/default', $storeId);
        $theme = (string) Mage::getStoreConfig('design/theme/skin', $storeId)
            ?: $defaultTheme
            ?: Mage_Core_Model_Design_Package::DEFAULT_THEME;

        $legacyThemes = $designConfig->getNode("{$area}/{$package}/{$theme}/parent")
            ? null
            : array_filter([$defaultTheme, Mage_Core_Model_Design_Package::DEFAULT_THEME]);

        $result = [];
        $visited = [];
        while ($theme !== '' && !isset($visited["{$package}/{$theme}"])) {
            $visited["{$package}/{$theme}"] = true;
            $node = $designConfig->getNode("{$area}/{$package}/{$theme}/images");
            if ($node) {
                foreach ($node->slots ? $node->slots->children() : [] as $name => $child) {
                    if (!isset($result['slots'][$name]) && (int) $child > 0) {
                        $result['slots'][$name] = (int) $child;
                    }
                }
                foreach ($node->children() as $name => $child) {
                    if ($name === 'slots') {
                        continue;
                    }
                    foreach (['ratio', 'fit'] as $field) {
                        if (!isset($result['entities'][$name][$field]) && trim((string) $child->$field) !== '') {
                            $result['entities'][$name][$field] = trim((string) $child->$field);
                        }
                    }
                    if (!isset($result['entities'][$name]['slots']) && $child->slots && $child->slots->children()->count() > 0) {
                        $result['entities'][$name]['slots'] = [];
                        foreach ($child->slots->children() as $slotName => $width) {
                            $result['entities'][$name]['slots'][$slotName] = max(0, (int) $width);
                        }
                    }
                }
            }

            $parent = $legacyThemes === null
                ? trim((string) $designConfig->getNode("{$area}/{$package}/{$theme}/parent"))
                : '';
            while ($parent === '' && $legacyThemes) {
                $legacyTheme = array_shift($legacyThemes);
                $parent = isset($visited["{$package}/{$legacyTheme}"]) ? '' : $legacyTheme;
            }
            $parent = $parent ?: self::BASE_THEME;
            $parts = explode('/', $parent);
            [$package, $theme] = count($parts) === 2 ? $parts : [$package, $parts[0]];
        }

        return $result;
    }

    /**
     * The watermark of $entity in the store view, from the store config paths that start with
     * $prefix. The file is in the watermark folder below the source folder of the entity. The store
     * folder comes first, then the website folder, the default folder and the watermark folder itself.
     *
     * @return array{path: string, position: string, width: int|null, height: int|null, opacity: int}|null
     */
    protected function getWatermark(string $entity, string $prefix, int $storeId): ?array
    {
        $prefix .= '_';
        $file = (string) Mage::getStoreConfig($prefix . 'image', $storeId);
        if ($file === '') {
            return null;
        }

        $mount = Mage::getStorage('media');
        $baseDir = $this->getSource($entity) . '/watermark';
        $file = '/' . ltrim($file, '/');
        $websiteId = (int) Mage::app()->getStore($storeId)->getWebsiteId();
        foreach (["/stores/{$storeId}{$file}", "/websites/{$websiteId}{$file}", "/default{$file}", $file] as $candidate) {
            $path = \Maho\Io::getPathWithinMount($mount, $baseDir, $candidate);
            if ($path === null || !$mount->fileExists($path)) {
                continue;
            }

            $size = explode('x', strtolower((string) Mage::getStoreConfig($prefix . 'size', $storeId)));
            return [
                'path' => $path,
                'position' => (string) Mage::getStoreConfig($prefix . 'position', $storeId) ?: 'stretch',
                'width' => count($size) === 2 && (int) $size[0] > 0 ? (int) $size[0] : null,
                'height' => count($size) === 2 && (int) $size[1] > 0 ? (int) $size[1] : null,
                'opacity' => (int) (Mage::getStoreConfig($prefix . 'imageOpacity', $storeId) ?: 70),
            ];
        }

        return null;
    }

    protected function mergeWidths(Mage_Core_Model_Image_Settings $first, Mage_Core_Model_Image_Settings $second): Mage_Core_Model_Image_Settings
    {
        $widths = Mage_Core_Model_Image_Settings::parseWidths([...$first->widths, ...$second->widths]);
        return $widths === $first->widths
            ? $first
            : Mage_Core_Model_Image_Settings::fromArray(['widths' => $widths] + $first->toArray()) ?? $first;
    }
}
