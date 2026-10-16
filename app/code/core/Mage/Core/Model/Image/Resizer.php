<?php

/**
 * Creates and deletes the resized files of image entities on the media mount.
 *
 * Each source file gets one file for each width of each settings set that a store view uses,
 * active or live. A width larger than the source gives a file at the size of the source, so
 * every width in a srcset exists.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

use Intervention\Image\Interfaces\ImageInterface;

class Mage_Core_Model_Image_Resizer
{
    public const QUEUE_NAME = 'image';

    /** Source files in one queue message. */
    public const BATCH_SIZE = 100;

    /** A source below this folder of its entity is a placeholder, which gets no watermark. */
    public const PLACEHOLDER_FOLDER = 'placeholder';

    /** @var array<string, ImageInterface|null> */
    protected array $watermarks = [];

    /** @var array<string, array<string, true>> the files that createFilesAfterCommit() took in this request, by entity */
    protected array $scheduled = [];

    /**
     * Create the files of $files for every settings set of $entity. A file that exists is
     * written again, so a changed source with the same name gets new files.
     *
     * @param iterable<string> $files paths below the source folder of $entity
     * @param array<string, Mage_Core_Model_Image_Settings>|null $settingsList default: every settings set of the entity
     * @param array<string, true>|null $existing mount paths of files to skip, from listExistingFiles()
     * @param (callable(string $file): void)|null $onFile called after each source file
     * @param (callable(string $file): void)|null $onFailure called for each source file with a file
     *        that could not be written. A source that cannot be read is no failure: a retry cannot fix it.
     * @return int the number of files written
     */
    public function createFiles(
        string $entity,
        iterable $files,
        ?array $settingsList = null,
        ?array $existing = null,
        ?callable $onFile = null,
        ?callable $onFailure = null,
    ): int {
        $config = Mage::getSingleton('core/image_config');
        $source = $config->getSource($entity);
        $settingsList ??= $config->getAllSettings($entity);
        $count = 0;

        foreach ($files as $file) {
            $sourcePath = Mage_Core_Model_Image_Set::resolveSourcePath($source, (string) $file);
            if ($sourcePath !== null && !str_ends_with(strtolower($sourcePath), '.svg')) {
                $failed = false;
                $count += $this->createFilesOfSource($source, $sourcePath, $settingsList, $existing, $failed);
                $failed && $onFailure !== null && $onFailure((string) $file);
            }
            $onFile !== null && $onFile((string) $file);
        }

        return $count;
    }

    /**
     * Create the resized file at $path and return its bytes. This repairs a file that a page links
     * to but that does not exist, when the web server sends the request for a missing file to Maho.
     * Null when $path is not a file of the settings that a store view uses, or when no source of it
     * exists and can be read.
     *
     * @param string $path mount path, such as catalog/product/cache/3f9a1c2b0d4e/300/a/b/shirt.jpg.webp
     */
    public function createFileAtPath(string $path): ?string
    {
        $parsed = $this->parsePath($path);
        if ($parsed === null) {
            return null;
        }
        [, $source, $settings, $width, $sourcePaths] = $parsed;
        $mount = Mage::getStorage('media');
        if ($mount->fileExists($path)) {
            return $mount->read($path);
        }
        foreach ($sourcePaths as $sourcePath) {
            if (!$mount->fileExists($sourcePath)) {
                continue;
            }
            $image = $this->decode($sourcePath);
            if ($image === null) {
                continue;
            }
            $binary = $this->render($image, $settings, $width, !$this->isPlaceholder($source, $sourcePath));
            $mount->moveAtomic($path, $binary);
            return $binary;
        }
        return null;
    }

    /**
     * The URL of the placeholder for the resized file at $path, when $path is a file of the
     * settings that a store view uses but createFileAtPath() cannot create it. Null for any other
     * path, for the file of a placeholder, and for an entity with no placeholder.
     *
     * @param string $path mount path, such as catalog/product/cache/3f9a1c2b0d4e/300/a/b/shirt.jpg.webp
     */
    public function getPlaceholderUrl(string $path): ?string
    {
        $parsed = $this->parsePath($path);
        if ($parsed === null) {
            return null;
        }
        [$entity, $source, , $width, $sourcePaths] = $parsed;
        if (array_any($sourcePaths, fn(string $sourcePath): bool => $this->isPlaceholder($source, $sourcePath))) {
            return null;
        }
        return $this->getProvider($entity)?->getPlaceholderUrl($width);
    }

    /**
     * The entity, the source folder, the settings, the width and the source files of the resized
     * file at $path, or null when $path is not a file of the settings that a store view uses.
     *
     * @return array{0: string, 1: string, 2: Mage_Core_Model_Image_Settings, 3: int, 4: non-empty-list<string>}|null
     */
    protected function parsePath(string $path): ?array
    {
        $config = Mage::getSingleton('core/image_config');
        foreach ($config->getEntities() as $entity => $source) {
            $parsed = Mage_Core_Model_Image_Set::parsePath($source, $path, $config->getAllSettings($entity));
            if ($parsed !== null) {
                return [$entity, $source, ...$parsed];
            }
        }
        return null;
    }

    /**
     * Create the files of $files after the open database transaction commits, or now when no
     * transaction is open. A resize on a bucket takes seconds, so it must not hold the
     * transaction of a save. An error is logged and does not stop the caller, and the queue
     * tries the files that failed again.
     *
     * @param list<string> $files paths below the source folder of $entity
     */
    public function createFilesAfterCommit(string $entity, array $files): void
    {
        $files = array_values(array_filter(
            array_unique(array_filter($files, fn($file): bool => is_string($file) && $file !== '' && $file !== 'no_selection')),
            fn(string $file): bool => !isset($this->scheduled[$entity][$file]),
        ));
        if ($files === []) {
            return;
        }
        foreach ($files as $file) {
            $this->scheduled[$entity][$file] = true;
        }

        $run = function () use ($entity, $files): void {
            $failed = [];
            try {
                $this->createFiles($entity, $files, onFailure: function (string $file) use (&$failed): void {
                    $failed[] = $file;
                });
            } catch (\Throwable $e) {
                Mage::logException($e);
                $failed = $files;
            }
            $this->queue($entity, $failed);
        };

        if (Mage::getSingleton('core/resource')->getConnection('core_write')->getTransactionLevel() > 0) {
            Mage::getResourceSingleton('core/config')->addCommitCallback($run);
        } else {
            $run();
        }
    }

    /**
     * Queue the creation of the files of $files. The creation only saves time for the first
     * visitor, so an error is logged and does not stop the caller.
     *
     * @param list<string> $files paths below the source folder of $entity
     */
    public function queue(string $entity, array $files): void
    {
        $files = array_values(array_unique(array_filter($files, fn($file): bool => is_string($file) && $file !== '' && $file !== 'no_selection')));
        if ($files === []) {
            return;
        }

        try {
            foreach (array_chunk($files, self::BATCH_SIZE) as $batch) {
                \Maho\Queue\QueueManager::dispatch(new Mage_Core_Model_Image_ResizeMessage($entity, $batch), queue: self::QUEUE_NAME);
            }
        } catch (\Throwable $e) {
            Mage::logException($e);
        }
    }

    /**
     * Delete the settings folders that no store view uses, queue the creation of the missing
     * files for every image entity, and queue a new read of every stored image size. The files
     * that the URLs use and the sizes stay, so no image goes missing in the meantime. This is the
     * "Flush Catalog Images Cache" action of the admin.
     */
    public function refreshAll(): void
    {
        foreach (array_keys(Mage::getSingleton('core/image_config')->getEntities()) as $entity) {
            $this->clean($entity);
            $this->queueAll($entity);
        }
        Mage::getSingleton('core/image_size')->refreshAll();
    }

    /**
     * Queue createAll() for $entity.
     */
    public function queueAll(string $entity, ?string $dedupeKey = null): void
    {
        \Maho\Queue\QueueManager::dispatch(new Mage_Core_Model_Image_ResizeAllMessage($entity), queue: self::QUEUE_NAME, dedupeKey: $dedupeKey);
    }

    /**
     * Create the missing files of every source file of $entity, then make the live settings of
     * every store view active. This is the step after a change of a theme or of the image
     * settings, and the repair after a loss of files.
     *
     * @param (callable(string $file): void)|null $onFile called after each source file
     * @return int the number of files written
     * @throws RuntimeException when a file could not be written. The store views then keep their settings.
     */
    public function createAll(string $entity, ?callable $onFile = null): int
    {
        $config = Mage::getSingleton('core/image_config');
        $source = $config->getSource($entity);
        $settingsList = $config->getAllSettings($entity);

        $existing = [];
        foreach ($settingsList as $settings) {
            $existing += $this->listExistingFiles($source, $settings);
        }

        $failed = 0;
        $count = $this->createFiles($entity, $this->getSourceFiles($entity), $settingsList, $existing, $onFile, function () use (&$failed): void {
            $failed++;
        });
        if ($failed > 0) {
            throw new RuntimeException(sprintf('%d images of the entity "%s" could not be written, so its store views keep their settings.', $failed, $entity));
        }
        $this->activateLiveSettings($entity);

        return $count;
    }

    /**
     * Switch each store view to the live settings of $entity. Call it when their files exist.
     */
    public function activateLiveSettings(string $entity): void
    {
        $config = Mage::getSingleton('core/image_config');
        $pending = [];
        foreach ($config->getPendingStoreIds($entity) as $storeId) {
            $pending[$storeId] = $config->getLiveSettings($entity, $storeId);
        }
        $config->activate($entity, $pending);
    }

    /**
     * The files of $files that miss at least one resized file of $settingsList in $existing.
     *
     * @param iterable<string> $files paths below the source folder of $entity
     * @param array<string, Mage_Core_Model_Image_Settings> $settingsList
     * @param array<string, true> $existing the mount paths of the resized files that exist
     * @return list<string>
     */
    public function filterMissing(string $entity, iterable $files, array $settingsList, array $existing): array
    {
        $source = Mage::getSingleton('core/image_config')->getSource($entity);
        $missing = [];
        foreach ($files as $file) {
            $sourcePath = Mage_Core_Model_Image_Set::resolveSourcePath($source, (string) $file);
            if ($sourcePath !== null
                && !str_ends_with(strtolower($sourcePath), '.svg')
                && $this->getMissingPaths($source, $sourcePath, $settingsList, $existing) !== []
            ) {
                $missing[] = (string) $file;
            }
        }
        return $missing;
    }

    /**
     * The source files of every record of $entity, from the provider that the entity declares
     * under global/images/entities/<entity>/provider.
     *
     * @return iterable<string>
     * @throws UnexpectedValueException when the provider does not implement Mage_Core_Model_Image_ProviderInterface
     */
    public function getSourceFiles(string $entity): iterable
    {
        return $this->getProvider($entity)?->getImageFiles() ?? [];
    }

    /**
     * The provider that $entity declares under global/images/entities/<entity>/provider, or null
     * when it declares none.
     *
     * @throws UnexpectedValueException when the provider does not implement Mage_Core_Model_Image_ProviderInterface
     */
    protected function getProvider(string $entity): ?Mage_Core_Model_Image_ProviderInterface
    {
        $alias = (string) Mage::getConfig()->getNode(Mage_Core_Model_Image_Config::XML_PATH_ENTITIES . "/{$entity}/provider");
        if ($alias === '') {
            return null;
        }
        $provider = Mage::getSingleton($alias);
        if (!$provider instanceof Mage_Core_Model_Image_ProviderInterface) {
            throw new UnexpectedValueException("The provider of the image entity \"{$entity}\" must implement Mage_Core_Model_Image_ProviderInterface.");
        }
        return $provider;
    }

    /**
     * The values of the image attribute of $entityType in every store, of the entities $entityIds
     * or of all entities.
     *
     * @param list<int>|null $entityIds
     * @return list<string>
     */
    public function getAttributeImageFiles(string $entityType, ?array $entityIds = null): array
    {
        $attribute = Mage::getSingleton('eav/config')->getAttribute($entityType, 'image');
        if (!$attribute || !$attribute->getId()) {
            return [];
        }

        $adapter = Mage::getSingleton('core/resource')->getConnection('core_read');
        $select = $adapter->select()
            ->distinct()
            ->from($attribute->getBackend()->getTable(), ['value'])
            ->where('attribute_id = ?', (int) $attribute->getId());
        if ($entityIds !== null) {
            $select->where('entity_id IN (?)', $entityIds);
        }
        $files = $adapter->fetchCol($select);

        return array_values(array_filter(array_map(strval(...), $files), fn(string $file): bool => $file !== ''));
    }

    /**
     * The entity and the file of $path when it is a source file of an image entity, or null.
     * Example: wysiwyg/swatches/red.png gives ['swatch', '/red.png'].
     *
     * @param string $path mount path
     * @return array{0: string, 1: string}|null
     */
    public function findSourceFile(string $path): ?array
    {
        foreach (Mage::getSingleton('core/image_config')->getEntities() as $entity => $source) {
            if (str_starts_with($path, $source . '/')
                && Mage_Core_Model_Image_Set::resolveSourcePath($source, substr($path, strlen($source))) !== null
            ) {
                return [$entity, substr($path, strlen($source))];
            }
        }
        return null;
    }

    /**
     * The cache folders of every image entity, as mount paths.
     *
     * @return list<string>
     */
    public function getCacheFolders(): array
    {
        return array_values(array_map(
            fn(string $source): string => $source . '/' . Mage_Core_Model_Image_Set::CACHE_FOLDER,
            Mage::getSingleton('core/image_config')->getEntities(),
        ));
    }

    /**
     * Delete the resized files of $file in every settings folder of $entity. The delete of a
     * missing file is no error.
     */
    public function deleteFiles(string $entity, string $file): void
    {
        $source = Mage::getSingleton('core/image_config')->getSource($entity);
        $sourcePath = Mage_Core_Model_Image_Set::resolveSourcePath($source, $file);
        if ($sourcePath === null) {
            return;
        }

        $mount = Mage::getStorage('media');
        $relative = substr($sourcePath, strlen($source));
        $extensions = $this->getExtensions();
        foreach ($this->listFolders($source . '/' . Mage_Core_Model_Image_Set::CACHE_FOLDER) as $hashFolder) {
            foreach ($this->listFolders($hashFolder) as $widthFolder) {
                foreach (['', ...$extensions] as $extension) {
                    $mount->delete($widthFolder . $relative . $extension);
                }
            }
        }
    }

    /**
     * Delete the settings folders and the width folders of $entity that no store view uses,
     * active or live, with the files of earlier releases in the same cache folder.
     *
     * @return int the number of deleted folders
     */
    public function clean(string $entity): int
    {
        $config = Mage::getSingleton('core/image_config');
        $source = $config->getSource($entity);
        $settingsList = $config->getAllSettings($entity);

        $mount = Mage::getStorage('media');
        $count = 0;
        foreach ($this->listFolders($source . '/' . Mage_Core_Model_Image_Set::CACHE_FOLDER) as $folder) {
            $settings = $settingsList[basename($folder)] ?? null;
            $unused = $settings === null
                ? [$folder]
                : array_filter($this->listFolders($folder), fn(string $widthFolder): bool => !in_array(basename($widthFolder), array_map(strval(...), $settings->widths), true));
            foreach ($unused as $unusedFolder) {
                $mount->deleteDirectory($unusedFolder);
                $count++;
            }
        }
        return $count;
    }

    /**
     * Delete every resized file of $entity. The URLs then point to missing files until
     * createAll() runs, so call it right after.
     */
    public function deleteAll(string $entity): void
    {
        $source = Mage::getSingleton('core/image_config')->getSource($entity);
        Mage::getStorage('media')->deleteDirectory($source . '/' . Mage_Core_Model_Image_Set::CACHE_FOLDER);
    }

    /**
     * The mount paths of the files that exist for $settings, with one listing of its folder.
     *
     * @return array<string, true>
     */
    public function listExistingFiles(string $source, Mage_Core_Model_Image_Settings $settings): array
    {
        $folder = $source . '/' . Mage_Core_Model_Image_Set::CACHE_FOLDER . '/' . $settings->getHash();
        $mount = Mage::getStorage('media');
        $existing = [];
        try {
            if (!$mount->directoryExists($folder)) {
                return [];
            }
            foreach ($mount->listFiles($folder) as $item) {
                $existing[$item->path()] = true;
            }
        } catch (\League\Flysystem\FilesystemException $e) {
            Mage::logException($e);
        }
        return $existing;
    }

    /**
     * The resized files of $sourcePath that $existing does not hold, or all of them when $existing
     * is null.
     *
     * @param array<string, Mage_Core_Model_Image_Settings> $settingsList
     * @param array<string, true>|null $existing
     * @return array<string, array{0: Mage_Core_Model_Image_Settings, 1: int}> the settings and the width, by mount path
     */
    protected function getMissingPaths(string $source, string $sourcePath, array $settingsList, ?array $existing): array
    {
        $missing = [];
        foreach ($settingsList as $settings) {
            foreach ($settings->widths as $width) {
                $path = Mage_Core_Model_Image_Set::buildPath($source, $sourcePath, $settings, $width);
                if ($existing === null || !isset($existing[$path])) {
                    $missing[$path] = [$settings, $width];
                }
            }
        }
        return $missing;
    }

    /**
     * @param array<string, Mage_Core_Model_Image_Settings> $settingsList
     * @param array<string, true>|null $existing
     * @param bool $failed set to true when a file could not be written
     */
    protected function createFilesOfSource(string $source, string $sourcePath, array $settingsList, ?array $existing, bool &$failed = false): int
    {
        $missing = $this->getMissingPaths($source, $sourcePath, $settingsList, $existing);
        if ($missing === []) {
            return 0;
        }

        $image = $this->decode($sourcePath);
        if ($image === null) {
            return 0;
        }

        $mount = Mage::getStorage('media');
        $isPlaceholder = $this->isPlaceholder($source, $sourcePath);
        $count = 0;
        $rendered = [];
        foreach ($missing as $path => [$settings, $width]) {
            // Without a ratio, every width at or above the source width gives the same image
            $key = $settings->getHash() . '/' . ($settings->ratio === null ? min($width, $image->width()) : $width);
            try {
                $mount->moveAtomic($path, $rendered[$key] ??= $this->render($image, $settings, $width, !$isPlaceholder));
                $count++;
            } catch (\Throwable $e) {
                Mage::logException($e);
                $failed = true;
            }
        }
        return $count;
    }

    /**
     * The image of the source file, or null when it cannot be read. Its size goes to
     * core_image_size, so a page does not read the file to find it.
     */
    protected function decode(string $sourcePath): ?ImageInterface
    {
        $mount = Mage::getStorage('media');
        try {
            $root = $mount->localRoot();
            $image = $root !== null
                ? Maho::getImageManager()->decodePath($root . '/' . $sourcePath)
                : Maho::getImageManager()->decodeBinary($mount->read($sourcePath));
        } catch (\Throwable $e) {
            Mage::log("The image {$sourcePath} cannot be read: {$e->getMessage()}", Mage::LOG_WARNING);
            return null;
        }
        Mage::getSingleton('core/image_size')->save($mount->name(), $sourcePath, $image->width(), $image->height());
        return $image;
    }

    protected function isPlaceholder(string $source, string $sourcePath): bool
    {
        return str_starts_with($sourcePath, $source . '/' . self::PLACEHOLDER_FOLDER . '/');
    }

    /**
     * The bytes of $image at $width with $settings.
     */
    public function render(ImageInterface $image, Mage_Core_Model_Image_Settings $settings, int $width, bool $withWatermark = true): string
    {
        $image = clone $image;
        if (!$settings->keepsTransparency()) {
            $image->fillTransparentAreas($settings->background);
        }

        $height = $settings->getHeightFor($width);
        if ($height === null) {
            $image->scaleDown($width);
        } elseif ($settings->fit === Mage_Core_Model_Image_Settings::FIT_COVER) {
            $image->coverDown($width, $height);
        } else {
            $image->containDown($width, $height, $settings->keepsTransparency() ? 'ffffff00' : $settings->background);
        }

        if ($withWatermark && $settings->watermark !== null) {
            $this->addWatermark($image, $settings->watermark);
        }

        return $image->encodeUsingFileExtension(ltrim($settings->extension, '.'), quality: $settings->quality)->toString();
    }

    /**
     * @param array{path: string, position: string, width: int|null, height: int|null, opacity: int} $watermark
     */
    protected function addWatermark(ImageInterface $image, array $watermark): void
    {
        if (!array_key_exists($watermark['path'], $this->watermarks)) {
            try {
                $this->watermarks[$watermark['path']] = Maho::getImageManager()->decodeBinary(Mage::getStorage('media')->read($watermark['path']));
            } catch (\Throwable $e) {
                Mage::logException($e);
                $this->watermarks[$watermark['path']] = null;
            }
        }
        $element = $this->watermarks[$watermark['path']];
        if ($element === null) {
            return;
        }

        $element = clone $element;
        if ($watermark['width'] !== null && $watermark['height'] !== null) {
            $element->resize($watermark['width'], $watermark['height']);
        }

        $position = $watermark['position'];
        if ($position === 'stretch') {
            $element->resize($image->width(), $image->height());
            $position = 'top-left';
        } elseif ($position === 'tile') {
            $tile = $element;
            $element = Maho::getImageManager()->createImage($image->width(), $image->height());
            for ($x = 0; $x < $image->width(); $x += $tile->width()) {
                for ($y = 0; $y < $image->height(); $y += $tile->height()) {
                    $element->insert($tile, $x, $y);
                }
            }
            $position = 'top-left';
        }

        $image->insert($element, alignment: $position, transparency: $watermark['opacity'] / 100);
    }

    /**
     * @return list<string> the mount paths of the folders directly below $folder
     */
    protected function listFolders(string $folder): array
    {
        $mount = Mage::getStorage('media');
        $folders = [];
        try {
            foreach ($mount->listContents($folder, false) as $item) {
                if ($item->isDir()) {
                    $folders[] = $item->path();
                }
            }
        } catch (\League\Flysystem\FilesystemException) {
            return [];
        }
        return $folders;
    }

    /**
     * @return list<string> the output extensions of all store views
     */
    protected function getExtensions(): array
    {
        $config = Mage::getSingleton('core/image_config');
        $extensions = [];
        foreach ($config->getStoreIds() as $storeId) {
            foreach (array_keys($config->getEntities()) as $entity) {
                $extensions[] = $config->getActiveSettings($entity, $storeId)->extension;
                $extensions[] = $config->getLiveSettings($entity, $storeId)->extension;
            }
        }
        return array_values(array_unique($extensions));
    }
}
