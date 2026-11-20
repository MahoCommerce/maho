<?php

/**
 * The private storage of the report exports that the queue writes, and the clean-up of old files.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Reports
 */

declare(strict_types=1);

class Mage_Reports_Model_Explorer_Export
{
    public const STORAGE_MOUNT = 'reports_exports';
    public const FILE_LIFETIME_DAYS = 7;

    public function getStorage(): \Maho\Storage\Mount
    {
        return Mage::getStorage(self::STORAGE_MOUNT);
    }

    /** A new file name for an export of $adminUserId. A random part makes it hard to guess. */
    public function newFileName(int $adminUserId): string
    {
        return 'report_' . $adminUserId . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '.csv';
    }

    /**
     * The path of an export file of $adminUserId, or null when $fileName is not the name of one of their exports.
     * $fileName comes from a request, so only a plain file name with the ID of the administrator passes.
     */
    public function getStoragePath(string $fileName, int $adminUserId): ?string
    {
        if (!preg_match('/^report_' . $adminUserId . '_[0-9]{8}_[0-9]{6}_[a-f0-9]{16}\.csv$/', $fileName)) {
            return null;
        }
        return \Maho\Io::getPathWithinMount($this->getStorage(), '', $fileName);
    }

    /** Delete the export files that are older than FILE_LIFETIME_DAYS. */
    #[Maho\Config\CronJob('reports_delete_expired_exports', schedule: '50 3 * * *')]
    public function deleteExpiredFiles(): void
    {
        $mount = $this->getStorage();
        $limit = time() - self::FILE_LIFETIME_DAYS * 86400;
        foreach ($mount->listFiles() as $file) {
            $lastModified = $file->lastModified();
            if ($lastModified !== null && $lastModified < $limit) {
                $mount->delete($file->path());
            }
        }
    }
}
