<?php

/**
 * Shared handling of store lists and content files for CMS pages, CMS blocks and blog posts.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho
 */

declare(strict_types=1);

namespace Maho\Import\Importer;

use Maho\Import\AbstractImporter;
use Maho\Import\CsvFile;

abstract class AbstractCmsImporter extends AbstractImporter
{
    public const OPTION_CONTENT_DIR = 'content_dir';

    /**
     * Reads `content_file` (relative to the content dir, default `content/` next to the CSV) or `content`.
     *
     * @param array<string, mixed> $row
     * @param array<string, mixed> $options
     */
    protected function content(CsvFile $file, int $line, array $row, array $options, bool $required): ?string
    {
        $inline = $row['content'] ?? '';
        $fileName = $row['content_file'] ?? '';
        if ($inline !== '' && $fileName !== '') {
            $this->fail($file, $line, 'content and content_file cannot both be set');
        }
        if ($fileName !== '') {
            $dir = $options[self::OPTION_CONTENT_DIR] ?? dirname($file->getPath()) . '/content';
            $path = rtrim($dir, '/') . '/' . ltrim($fileName, '/');
            if (!is_file($path)) {
                $this->fail($file, $line, "content_file '$fileName' not found in $dir");
            }
            return (string) file_get_contents($path);
        }
        if ($inline === '' && $required) {
            $this->fail($file, $line, 'content or content_file is required');
        }
        return $inline === '' ? null : $inline;
    }

    /**
     * @param array<string, string> $row
     * @return list<int>
     */
    protected function storeIds(CsvFile $file, int $line, array $row): array
    {
        $ids = $this->at($file, $line, fn() => $this->resolver->storeIds($row['stores'] ?? ''));
        sort($ids);
        return array_values(array_unique($ids));
    }
}
