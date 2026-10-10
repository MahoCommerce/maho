<?php

/**
 * Files an administrator attaches to a chat message, kept on the ai_attachments mount per administrator.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Ai
 */

declare(strict_types=1);

use League\Flysystem\FilesystemException;
use Maho\Storage\Mount;

/**
 * A file lives at <admin id>/<id>_<name> on the ai_attachments mount. The id is random, so a name
 * never collides and a path never comes from the client. Text files are read by the
 * attachment_read tool; an image goes to the model with the message when the model can see.
 */
final class Maho_Ai_Model_Chat_Attachment
{
    public const MAX_BYTES = 5 * 1024 * 1024;
    public const MAX_PER_MESSAGE = 5;
    public const KEEP_DAYS = 30;
    /** What the tool reads as text, with its MIME type as the panel sends it. */
    public const TEXT_EXTENSIONS = ['csv' => 'text/csv', 'txt' => 'text/plain', 'md' => 'text/markdown', 'json' => 'application/json', 'xml' => 'application/xml', 'html' => 'text/html', 'tsv' => 'text/tab-separated-values'];
    public const IMAGE_EXTENSIONS = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif', 'webp' => 'image/webp'];
    public const READ_CHARS = 12000;

    /**
     * @return array{id: string, name: string, mime: string, size: int}
     * @throws Mage_Core_Exception when the file is too large or of a kind the assistant cannot use
     */
    public static function store(int $adminId, string $originalName, string $tmpPath): array
    {
        $name = self::safeName($originalName);
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $mime = self::TEXT_EXTENSIONS[$extension] ?? self::IMAGE_EXTENSIONS[$extension] ?? null;
        if ($mime === null) {
            Mage::throwException(Mage::helper('ai')->__('The assistant accepts text, CSV, JSON, XML, Markdown and image files.'));
        }
        $size = (int) filesize($tmpPath);
        if ($size <= 0 || $size > self::maxBytes()) {
            Mage::throwException(Mage::helper('ai')->__('A file can be at most %s.', self::humanSize(self::maxBytes())));
        }
        if (isset(self::IMAGE_EXTENSIONS[$extension]) && @\Maho\Io::getImageSize($tmpPath) === false) {
            Mage::throwException(Mage::helper('ai')->__('The file is not an image.'));
        }
        $id = bin2hex(random_bytes(8));
        try {
            self::mount()->copyFromLocalFile($tmpPath, $adminId . '/' . $id . '_' . $name);
        } catch (FilesystemException|\Maho\Storage\StorageException) {
            Mage::throwException(Mage::helper('ai')->__('The file cannot be stored.'));
        }
        @unlink($tmpPath);

        return ['id' => $id, 'name' => $name, 'mime' => $mime, 'size' => $size];
    }

    /** The path on the ai_attachments mount of an attachment of this administrator, or null when there is none with that id. */
    public static function path(int $adminId, string $id): ?string
    {
        if (preg_match('/^[a-f0-9]{16}$/', $id) !== 1) {
            return null;
        }
        $prefix = $adminId . '/' . $id . '_';
        try {
            foreach (self::mount()->listFiles((string) $adminId) as $file) {
                if (str_starts_with($file->path(), $prefix)) {
                    return $file->path();
                }
            }
        } catch (FilesystemException) {
        }

        return null;
    }

    /** The content of an attachment of this administrator, or null when there is none with that id. */
    public static function read(int $adminId, string $id): ?string
    {
        $path = self::path($adminId, $id);
        if ($path === null) {
            return null;
        }
        try {
            return self::mount()->read($path);
        } catch (FilesystemException) {
            return null;
        }
    }

    /**
     * @return array{id: string, name: string, mime: string, size: int}|null
     */
    public static function describe(int $adminId, string $id): ?array
    {
        $path = self::path($adminId, $id);
        if ($path === null) {
            return null;
        }
        $name = substr(basename($path), 17);
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        try {
            $size = self::mount()->fileSize($path);
        } catch (FilesystemException) {
            return null;
        }

        return ['id' => $id, 'name' => $name, 'mime' => self::TEXT_EXTENSIONS[$extension] ?? self::IMAGE_EXTENSIONS[$extension] ?? 'application/octet-stream', 'size' => $size];
    }

    /** Removes the file. A file that a sent message refers to stays, unless $force is set (a conversation delete). */
    public static function delete(int $adminId, string $id, bool $force = false): bool
    {
        $path = self::path($adminId, $id);
        if ($path === null || (!$force && self::isReferenced($adminId, $id))) {
            return false;
        }

        try {
            self::mount()->delete($path);
        } catch (FilesystemException) {
            return false;
        }

        return true;
    }

    /** True when a message of this administrator carries the attachment. */
    public static function isReferenced(int $adminId, string $id): bool
    {
        $resource = Mage::getSingleton('core/resource');
        $connection = $resource->getConnection('core_read');
        $select = $connection->select()
            ->from(['m' => $resource->getTableName('ai/conversation_message')], ['message_id'])
            ->join(['c' => $resource->getTableName('ai/conversation')], 'c.conversation_id = m.conversation_id', [])
            ->where('c.admin_user_id = ?', $adminId)
            ->where('m.attachments LIKE ?', '%"id":"' . $id . '"%')
            ->limit(1);

        return $connection->fetchOne($select) !== false;
    }

    /** Removes every attachment of a conversation that is deleted. */
    public static function deleteForConversation(Maho_Ai_Model_Conversation $conversation): void
    {
        $adminId = (int) $conversation->getAdminUserId();
        foreach ($conversation->messagesCollection() as $message) {
            foreach ($message->getAttachments() as $attachment) {
                self::delete($adminId, (string) ($attachment['id'] ?? ''), force: true);
            }
        }
    }

    /** Removes files older than $days, and the per-administrator folder once it is empty. Returns the number of removed files. */
    public static function purgeOlderThan(int $days): int
    {
        $mount = self::mount();
        $cutoff = time() - $days * 86400;
        $removed = 0;
        try {
            foreach ($mount->listFiles()->toArray() as $file) {
                if (($file->lastModified() ?? $mount->lastModified($file->path())) < $cutoff) {
                    $mount->delete($file->path());
                    $removed++;
                }
            }
            foreach ($mount->listContents('')->filter(static fn($item): bool => $item->isDir())->toArray() as $directory) {
                if ($mount->listFiles($directory->path())->toArray() === []) {
                    $mount->deleteDirectory($directory->path());
                }
            }
        } catch (FilesystemException $e) {
            Mage::logException($e);
        }

        return $removed;
    }

    /** The smaller of MAX_BYTES and what PHP accepts in one upload. */
    public static function maxBytes(): int
    {
        $limit = self::MAX_BYTES;
        foreach (['upload_max_filesize', 'post_max_size'] as $setting) {
            $bytes = self::iniBytes((string) ini_get($setting));
            if ($bytes > 0) {
                $limit = min($limit, $bytes);
            }
        }

        return $limit;
    }

    public static function humanSize(int $bytes): string
    {
        if ($bytes >= 1024 * 1024) {
            return rtrim(rtrim(number_format($bytes / 1024 / 1024, 1, '.', ''), '0'), '.') . ' MB';
        }

        return max(1, (int) round($bytes / 1024)) . ' KB';
    }

    private static function iniBytes(string $value): int
    {
        $value = trim($value);
        if ($value === '' || $value === '-1') {
            return 0;
        }
        $number = (int) $value;

        return match (strtolower(substr($value, -1))) {
            'g' => $number * 1024 ** 3,
            'm' => $number * 1024 ** 2,
            'k' => $number * 1024,
            default => $number,
        };
    }

    public static function isImage(string $mime): bool
    {
        return in_array($mime, self::IMAGE_EXTENSIONS, true);
    }

    public static function isText(string $mime): bool
    {
        return in_array($mime, self::TEXT_EXTENSIONS, true);
    }

    private static function mount(): Mount
    {
        return Mage::getStorage('ai_attachments');
    }

    private static function safeName(string $name): string
    {
        $name = (string) preg_replace('/[^A-Za-z0-9._-]+/', '-', basename(trim($name)));
        $name = trim($name, '.-');

        return $name === '' ? 'file' : mb_substr($name, 0, 80);
    }
}
