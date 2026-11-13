<?php

/**
 * CMS pages keyed by identifier and store set; `is_home` also points the store's home page at the page.
 * A custom attribute of CMS pages in the attribute set is a column too, with option labels for a dropdown.
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
        $attributes = Mage::getResourceSingleton('cms/page')->getCustomAttributes();
        $outsideSet = [];
        foreach (Mage::getSingleton('eav/config')->getAttributes(\Mage_Cms_Model_Page::ENTITY) as $attribute) {
            $code = (string) $attribute->getAttributeCode();
            if ($attribute->getIsUserDefined() && !isset($attributes[$code])) {
                $outsideSet[] = $code;
            }
        }
        foreach ($file as $line => $row) {
            $identifier = $this->requireValue($file, $line, $row, 'identifier');
            $error = Mage::getResourceSingleton('cms/page')->getIdentifierError($identifier);
            if ($error !== null) {
                $this->fail($file, $line, "identifier '$identifier': $error");
            }
            $row['store_ids'] = $this->storeIds($file, $line, $row);
            $row['is_active'] = $this->at($file, $line, fn() => CsvFile::bool($row['is_active'] ?? '', true));
            $row['is_home'] = $this->at($file, $line, fn() => CsvFile::bool($row['is_home'] ?? '', false));
            $row['page_id'] = $this->find($identifier, $row['store_ids']);
            $row['body'] = $this->content($file, $line, $row, $options, $row['page_id'] === null);
            if ($row['page_id'] === null && ($row['title'] ?? '') === '') {
                $this->fail($file, $line, 'title is required for a new page');
            }
            $row['custom'] = [];
            foreach ($outsideSet as $code) {
                if (($row[$code] ?? '') !== '') {
                    $this->fail($file, $line, "$code is not in the attribute set of CMS pages");
                }
            }
            foreach ($attributes as $code => $attribute) {
                if (($row[$code] ?? '') !== '') {
                    $row['custom'][$code] = $this->customValue($file, $line, $attribute, $row[$code]);
                }
            }
            if ($row['page_id'] === null) {
                // The save checks the attributes of a new page with the same call, so a row fails here and not in the write
                $page = Mage::getService('cms/page')->newPage()->setIsActive((bool) $row['is_active'])
                    ->addData(array_filter(array_intersect_key($row, array_flip(self::TEXT_COLUMNS)), fn($value) => $value !== ''))
                    ->addData($row['custom']);
                try {
                    Mage::getResourceSingleton('cms/page')->prepareCustomAttributes($page);
                } catch (\Mage_Core_Exception_Input $e) {
                    $error = $e->getErrors()[0];
                    $this->fail($file, $line, "{$error['field']}: {$error['message']}");
                }
            }
            $rows[$line] = $row;
        }
        return $rows;
    }

    #[\Override]
    protected function write(CsvFile $file, array $rows, array $options, Reporter $reporter): Result
    {
        $result = new Result();
        /** @var \Mage_Cms_Service_Page $service */
        $service = Mage::getService('cms/page');
        $homePages = [];
        foreach ($rows as $row) {
            if ($row['page_id'] !== null) {
                $page = Mage::getModel('cms/page')->load($row['page_id']);
                $result->updated++;
            } else {
                $page = $service->newPage();
                $result->created++;
            }
            $page->setIdentifier($row['identifier'])->setStores($row['store_ids'])->setIsActive((bool) $row['is_active']);
            foreach (self::TEXT_COLUMNS as $column) {
                if (($row[$column] ?? '') !== '') {
                    $page->setData($column, $row[$column]);
                }
            }
            if ($row['body'] !== null) {
                $page->setContent($row['body']);
            }
            foreach ($row['custom'] as $code => $value) {
                $page->setData($code, $value);
            }
            $service->save($page);
            if ($row['is_home']) {
                foreach ($row['store_ids'] as $storeId) {
                    $homePages[] = [\Mage_Cms_Helper_Page::XML_PATH_HOME_PAGE, $row['identifier'], $storeId === 0 ? 'default' : 'stores', $storeId];
                }
            }
        }
        if ($homePages !== []) {
            Mage::getService('core/config')->saveAll($homePages);
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
