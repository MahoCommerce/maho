<?php

/**
 * Blog posts keyed by url key and store set; skipped when the blog module is off.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho
 */

declare(strict_types=1);

namespace Maho\Import\Importer;

use Mage;
use Maho\Import\CsvFile;
use Maho\Import\Reporter;
use Maho\Import\Result;

class BlogPosts extends AbstractCmsImporter
{
    private const TEXT_COLUMNS = ['title', 'image', 'meta_title', 'meta_keywords', 'meta_description', 'short_content'];

    #[\Override]
    protected function requiredColumns(): array
    {
        return ['url_key', 'stores'];
    }

    #[\Override]
    protected function prepare(CsvFile $file, array $options): array
    {
        if (!$this->enabled()) {
            return [];
        }
        $rows = [];
        foreach ($file as $line => $row) {
            $urlKey = $this->requireValue($file, $line, $row, 'url_key');
            $this->requireValue($file, $line, $row, 'stores');
            if (!preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $urlKey)) {
                $this->fail($file, $line, "url_key '$urlKey' must be lowercase letters, digits and dashes");
            }
            $row['store_ids'] = $this->storeIds($file, $line, $row);
            $row['is_active'] = $this->at($file, $line, fn() => CsvFile::bool($row['is_active'] ?? '', true));
            if (($row['publish_date'] ?? '') !== '' && !Mage::helper('core')->isValidDate($row['publish_date'])) {
                $this->fail($file, $line, "publish_date '{$row['publish_date']}' is not a date");
            }
            $row['post_id'] = Mage::getModel('blog/post')->getPostIdByUrlKey($urlKey, $row['store_ids'][0]);
            $row['body'] = $this->content($file, $line, $row, $options, $row['post_id'] === null);
            if ($row['post_id'] === null && ($row['title'] ?? '') === '') {
                $this->fail($file, $line, 'title is required for a new post');
            }
            $rows[$line] = $row;
        }
        return $rows;
    }

    #[\Override]
    protected function write(CsvFile $file, array $rows, array $options, Reporter $reporter): Result
    {
        $result = new Result();
        if (!$this->enabled()) {
            $result->notices[] = 'the blog module is disabled, ' . basename($file->getPath()) . ' was skipped';
            return $result;
        }
        foreach ($rows as $row) {
            $post = Mage::getModel('blog/post');
            if ($row['post_id'] !== null) {
                $post->load($row['post_id']);
                $result->updated++;
            } else {
                $result->created++;
            }
            $post->setUrlKey($row['url_key'])->setIsActive($row['is_active'] ? 1 : 0)->setStores($row['store_ids']);
            foreach (self::TEXT_COLUMNS as $column) {
                if (($row[$column] ?? '') !== '') {
                    $post->setData($column, $row[$column]);
                }
            }
            if (($row['publish_date'] ?? '') !== '') {
                $post->setPublishDate($row['publish_date']);
            }
            if ($row['body'] !== null) {
                $post->setContent($row['body']);
            }
            $post->save();
        }
        $reporter->info(count($rows) . ' posts');
        return $result;
    }

    private function enabled(): bool
    {
        return Mage::helper('core')->isModuleEnabled('Maho_Blog');
    }
}
