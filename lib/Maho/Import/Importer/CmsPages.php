<?php

/**
 * CMS pages keyed by identifier and store set; `is_home` also points the store's home page at the page.
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

class CmsPages extends AbstractCmsImporter
{
    private const TEXT_COLUMNS = ['title', 'root_template', 'content_heading', 'meta_keywords', 'meta_description', 'layout_update_xml'];

    #[\Override]
    protected function requiredColumns(): array
    {
        return ['identifier'];
    }

    #[\Override]
    protected function prepare(CsvFile $file, array $options): array
    {
        $rows = [];
        foreach ($file as $line => $row) {
            $identifier = $this->requireValue($file, $line, $row, 'identifier');
            if (!preg_match('/^[a-z0-9][a-z0-9_\/-]*$/', $identifier)) {
                $this->fail($file, $line, "identifier '$identifier' must be lowercase letters, digits, dashes, underscores or slashes");
            }
            $row['store_ids'] = $this->storeIds($file, $line, $row);
            $row['is_active'] = $this->at($file, $line, fn() => CsvFile::bool($row['is_active'] ?? '', true));
            $row['is_home'] = $this->at($file, $line, fn() => CsvFile::bool($row['is_home'] ?? '', false));
            $row['page_id'] = $this->find($identifier, $row['store_ids']);
            $row['body'] = $this->content($file, $line, $row, $options, $row['page_id'] === null);
            if ($row['page_id'] === null && ($row['title'] ?? '') === '') {
                $this->fail($file, $line, 'title is required for a new page');
            }
            $rows[$line] = $row;
        }
        return $rows;
    }

    #[\Override]
    protected function write(CsvFile $file, array $rows, array $options, Reporter $reporter): Result
    {
        $result = new Result();
        $config = Mage::getModel('core/config');
        $configTouched = false;
        foreach ($rows as $row) {
            $page = Mage::getModel('cms/page');
            if ($row['page_id'] !== null) {
                $page->load($row['page_id']);
                $result->updated++;
            } else {
                $page->setRootTemplate('one_column');
                $result->created++;
            }
            $page->setIdentifier($row['identifier'])->setStores($row['store_ids'])->setIsActive($row['is_active'] ? 1 : 0);
            foreach (self::TEXT_COLUMNS as $column) {
                if (($row[$column] ?? '') !== '') {
                    $page->setData($column, $row[$column]);
                }
            }
            if ($row['body'] !== null) {
                $page->setContent($row['body']);
            }
            $page->save();
            if ($row['is_home']) {
                foreach ($row['store_ids'] as $storeId) {
                    $config->saveConfig(\Mage_Cms_Helper_Page::XML_PATH_HOME_PAGE, $row['identifier'], $storeId === 0 ? 'default' : 'stores', $storeId);
                }
                $configTouched = true;
            }
        }
        if ($configTouched) {
            Mage::app()->getCache()->cleanType('config');
        }
        $reporter->info(count($rows) . ' pages');
        return $result;
    }

    /**
     * @param list<int> $storeIds
     */
    private function find(string $identifier, array $storeIds): ?int
    {
        $pages = Mage::getResourceModel('cms/page_collection')->addFieldToFilter('identifier', $identifier);
        foreach ($pages as $candidate) {
            $page = Mage::getModel('cms/page')->load($candidate->getId());
            $stores = array_map(intval(...), (array) $page->getStoreId());
            sort($stores);
            if ($stores === $storeIds) {
                return (int) $page->getId();
            }
        }
        return null;
    }
}
