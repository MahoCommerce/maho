<?php

/**
 * Shared handling of store lists, content files and custom field values for CMS pages, CMS blocks and blog posts.
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
    /** Leave a macro that does not resolve yet in place, for a first pass before the categories exist. */
    public const OPTION_LENIENT_MACROS = 'lenient_macros';

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
            return $this->at($file, $line, fn() => $this->resolver->expand((string) file_get_contents($path), (bool) ($options[self::OPTION_LENIENT_MACROS] ?? false)));
        }
        if ($inline === '' && $required) {
            $this->fail($file, $line, 'content or content_file is required');
        }
        return $inline === '' ? null : $this->at($file, $line, fn() => $this->resolver->expand($inline, (bool) ($options[self::OPTION_LENIENT_MACROS] ?? false)));
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

    /**
     * Return the stored value of $value. A select, a multiselect or a boolean takes option labels,
     * and a multiselect separates them with commas.
     */
    protected function customValue(CsvFile $file, int $line, \Mage_Eav_Model_Entity_Attribute_Abstract $attribute, string $value): string
    {
        if (!in_array($attribute->getFrontendInput(), ['select', 'multiselect', 'boolean'], true)) {
            return $value;
        }
        $ids = [];
        foreach (array_map(trim(...), explode(',', $value)) as $label) {
            $id = $attribute->getSource()->getOptionId($label);
            if ($id === null || $id === '') {
                $this->fail($file, $line, "'$label' is not an option of {$attribute->getAttributeCode()}");
            }
            $ids[] = (string) $id;
        }
        return implode(',', $ids);
    }
}
