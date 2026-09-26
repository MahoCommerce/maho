<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Dataflow
 */

declare(strict_types=1);

class Mage_Dataflow_Helper_Data extends Mage_Core_Helper_Abstract
{
    #[\Override]
    protected $_moduleName = 'Mage_Dataflow';

    /** Folder of the files that an admin uploads for a profile run, on the imports mount. */
    public const UPLOAD_DIRECTORY = 'uploads';

    /**
     * The mount and the folder on it for a profile path relative to the Maho root: var/export is
     * the exports mount and var/import the imports mount. Null for any other path.
     *
     * @return array{0: \Maho\Storage\Mount, 1: string}|null
     */
    public function getStorageLocation(string $path): ?array
    {
        $absolute = \Symfony\Component\Filesystem\Path::makeAbsolute($path, Mage::getBaseDir());
        $mounts = [
            'exports' => Mage::getBaseDir('export'),
            'imports' => Mage::getBaseDir('var') . DS . 'import',
        ];
        foreach ($mounts as $name => $directory) {
            $inside = \Maho\Io::getPathWithinDir($directory, $absolute);
            if ($inside !== null) {
                $base = \Symfony\Component\Filesystem\Path::canonicalize($directory);
                return [Mage::getStorage($name), trim(substr($inside, strlen($base)), '/')];
            }
        }
        return null;
    }

    /**
     * Copy the file $path of $mount into the temporary file of the batch, which the parser reads
     */
    public function copyToBatchFile(\Maho\Storage\Mount $mount, string $path, string $displayName): void
    {
        $target = Mage::getSingleton('dataflow/batch')->getIoAdapter()->getFile(true);
        $directory = dirname($target);
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }
        try {
            $source = $mount->readStream($path);
        } catch (\League\Flysystem\FilesystemException) {
            Mage::throwException($this->__('Could not load file: "%s".', $displayName));
        }
        try {
            $result = file_put_contents($target, $source);
        } finally {
            fclose($source);
        }
        if ($result === false) {
            Mage::throwException($this->__('Could not load file: "%s".', $displayName));
        }
    }

    public function getUploadMount(): \Maho\Storage\Mount
    {
        return Mage::getStorage('imports');
    }

    /**
     * Mount path of an uploaded profile file. Null when the name leaves the upload folder.
     */
    public function getUploadPath(string $filename): ?string
    {
        return \Maho\Io::getPathWithinMount($this->getUploadMount(), self::UPLOAD_DIRECTORY, $filename);
    }

    /**
     * Put a checked local upload in the upload folder of the imports mount and delete the local file.
     */
    public function storeUpload(string $localPath, string $filename): void
    {
        $path = $this->getUploadPath($filename);
        if ($path === null) {
            Mage::throwException($this->__('Invalid file path.'));
        }
        \Maho\Storage\Mount::copyLocalFile($localPath, $this->getUploadMount(), $path);
        unlink($localPath);
    }

    /**
     * Names of the uploaded profile files with the given extension, sorted
     *
     * @return list<string>
     */
    public function getUploadedFiles(string $extension): array
    {
        $files = [];
        try {
            foreach ($this->getUploadMount()->listContents(self::UPLOAD_DIRECTORY, false) as $item) {
                $name = basename($item->path());
                if ($item->isFile() && strtolower(pathinfo($name, PATHINFO_EXTENSION)) === strtolower($extension)) {
                    $files[] = $name;
                }
            }
        } catch (\League\Flysystem\FilesystemException $e) {
            Mage::logException($e);
        }
        sort($files);
        return $files;
    }
}
