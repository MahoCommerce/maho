<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Io;

interface IoInterface
{
    /**
     * Open a connection
     *
     * @param array<string, string|int|bool|null> $args
     */
    public function open(array $args = []): bool;

    /**
     * Close a connection
     */
    public function close(): void;

    /**
     * Create a directory
     *
     * @return string|bool The FTP adapter returns the name of the new directory
     */
    public function mkdir(string $dir, int $mode = 0777, bool $recursive = true): string|bool;

    /**
     * Delete a directory
     */
    public function rmdir(string $dir, bool $recursive = false): bool;

    /**
     * Get current working directory
     *
     * @return string|false|null null before open()
     */
    public function pwd(): string|false|null;

    /**
     * Change current working directory
     */
    public function cd(string $dir): bool;

    /**
     * Read a file
     *
     * @param string|resource|null $dest A file name or a stream to write to, or null to return the content
     */
    public function read(string $filename, mixed $dest = null): string|bool;

    /**
     * Write a file
     *
     * @param string|resource $src A file name, the content, or a stream
     */
    public function write(string $filename, mixed $src, ?int $mode = null): int|bool;

    /**
     * Delete a file
     */
    public function rm(string $filename): bool;

    /**
     * Rename or move a directory or a file
     */
    public function mv(string $src, string $dest): bool;

    /**
     * Change mode of a directory or a file
     *
     * @return int|bool The FTP and SFTP adapters return the new mode
     */
    public function chmod(string $filename, int $mode): int|bool;

    /**
     * Get list of cwd subdirectories and files
     *
     * @return list<array<string, string|int|bool>>
     */
    public function ls(?string $grep = null): array;

    /**
     * Retrieve directory separator in context of io resource
     */
    public function dirsep(): string;
}
