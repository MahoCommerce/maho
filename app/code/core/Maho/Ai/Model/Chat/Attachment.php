<?php

/**
 * Files an administrator attaches to a chat message, kept under var/ai/attachments per administrator.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Ai
 */

declare(strict_types=1);

/**
 * A file lives at var/ai/attachments/<admin id>/<id>_<name>. The id is random, so a name
 * never collides and a path never comes from the client. Text files are read by the
 * attachment_read tool; an image goes to the model with the message when the model can see.
 */
final class Maho_Ai_Model_Chat_Attachment
{
    public const MAX_BYTES = 5 * 1024 * 1024;
    public const MAX_PER_MESSAGE = 5;
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
        if ($size <= 0 || $size > self::MAX_BYTES) {
            Mage::throwException(Mage::helper('ai')->__('A file can be at most %s.', '5 MB'));
        }
        if (isset(self::IMAGE_EXTENSIONS[$extension]) && @getimagesize($tmpPath) === false) {
            Mage::throwException(Mage::helper('ai')->__('The file is not an image.'));
        }
        $id = bin2hex(random_bytes(8));
        $directory = self::directory($adminId);
        if (!is_dir($directory) && !mkdir($directory, 0o750, true) && !is_dir($directory)) {
            Mage::throwException(Mage::helper('ai')->__('The attachment folder cannot be created.'));
        }
        $target = $directory . DS . $id . '_' . $name;
        if (!(is_uploaded_file($tmpPath) ? move_uploaded_file($tmpPath, $target) : rename($tmpPath, $target))) {
            Mage::throwException(Mage::helper('ai')->__('The file cannot be stored.'));
        }

        return ['id' => $id, 'name' => $name, 'mime' => $mime, 'size' => $size];
    }

    /** The path of an attachment of this administrator, or null when there is none with that id. */
    public static function path(int $adminId, string $id): ?string
    {
        if (preg_match('/^[a-f0-9]{16}$/', $id) !== 1) {
            return null;
        }
        $matches = glob(self::directory($adminId) . DS . $id . '_*');

        return is_array($matches) && isset($matches[0]) && is_file($matches[0]) ? $matches[0] : null;
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

        return ['id' => $id, 'name' => $name, 'mime' => self::TEXT_EXTENSIONS[$extension] ?? self::IMAGE_EXTENSIONS[$extension] ?? 'application/octet-stream', 'size' => (int) filesize($path)];
    }

    public static function isImage(string $mime): bool
    {
        return in_array($mime, self::IMAGE_EXTENSIONS, true);
    }

    public static function isText(string $mime): bool
    {
        return in_array($mime, self::TEXT_EXTENSIONS, true);
    }

    private static function directory(int $adminId): string
    {
        return Mage::getBaseDir('var') . DS . 'ai' . DS . 'attachments' . DS . $adminId;
    }

    private static function safeName(string $name): string
    {
        $name = (string) preg_replace('/[^A-Za-z0-9._-]+/', '-', basename(trim($name)));
        $name = trim($name, '.-');

        return $name === '' ? 'file' : mb_substr($name, 0, 80);
    }
}
