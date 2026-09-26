<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sitemap
 */

declare(strict_types=1);

class Mage_Sitemap_Helper_Data extends Mage_Core_Helper_Abstract
{
    #[\Override]
    protected $_moduleName = 'Mage_Sitemap';

    /**
     * Mount path of the sitemap file that $requestPath names in the store: the index file of a
     * sitemap, or a file that it lists, whose name is the index name plus a suffix. Null for any
     * other path, so a request can never read another file of the mount.
     */
    public function getStoredFilePath(string $requestPath, int $storeId): ?string
    {
        $requestPath = '/' . ltrim($requestPath, '/');
        $basename = basename($requestPath);
        if (!preg_match('#^[a-zA-Z0-9_.-]+\.xml$#', $basename)) {
            return null;
        }
        $directory = rtrim(dirname($requestPath), '/') . '/';

        /** @var Mage_Sitemap_Model_Resource_Sitemap_Collection $collection */
        $collection = Mage::getResourceModel('sitemap/sitemap_collection');
        $collection->addStoreFilter([$storeId]);
        foreach ($collection as $sitemap) {
            $filename = (string) $sitemap->getSitemapFilename();
            $sitemapDirectory = rtrim('/' . trim((string) $sitemap->getSitemapPath(), '/'), '/') . '/';
            if ($filename === '' || $sitemapDirectory !== $directory) {
                continue;
            }
            if ($basename === $filename || str_starts_with($basename, pathinfo($filename, PATHINFO_FILENAME) . '-')) {
                return $sitemap->getStoragePath($basename);
            }
        }
        return null;
    }
}
