<?php

/**
 * Websites, store groups, root categories and store views from one CSV, one row per store view.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho
 */

declare(strict_types=1);

namespace Maho\Import\Importer;

use Mage;
use Maho\Import\AbstractImporter;
use Maho\Import\CsvFile;
use Maho\Import\Reporter;
use Maho\Import\Result;

class Stores extends AbstractImporter
{
    private const WEBSITE_CODE = '/^[a-z]+[a-z0-9_]*$/';
    private const STORE_CODE = '/^[a-z]+[a-z0-9_\-]*$/';

    #[\Override]
    protected function requiredColumns(): array
    {
        return ['website_code', 'root_category', 'store_code'];
    }

    #[\Override]
    protected function prepare(CsvFile $file, array $options): array
    {
        $rows = [];
        $defaults = 0;
        $storeCodes = [];
        foreach ($file as $line => $row) {
            $websiteCode = $this->requireValue($file, $line, $row, 'website_code');
            $storeCode = $this->requireValue($file, $line, $row, 'store_code');
            $this->requireValue($file, $line, $row, 'root_category');
            if (!preg_match(self::WEBSITE_CODE, $websiteCode)) {
                $this->fail($file, $line, "website_code '$websiteCode' must match " . self::WEBSITE_CODE);
            }
            if (!preg_match(self::STORE_CODE, $storeCode) || $storeCode === \Mage_Core_Model_Store::ADMIN_CODE) {
                $this->fail($file, $line, "store_code '$storeCode' must match " . self::STORE_CODE . ' and cannot be admin');
            }
            if (isset($storeCodes[$storeCode])) {
                $this->fail($file, $line, "store_code '$storeCode' appears twice");
            }
            $storeCodes[$storeCode] = true;
            $row['website_is_default'] = $this->at($file, $line, fn() => CsvFile::bool($row['website_is_default'] ?? '', false));
            $row['store_is_active'] = $this->at($file, $line, fn() => CsvFile::bool($row['store_is_active'] ?? '', true));
            $row['store_is_default'] = $this->at($file, $line, fn() => CsvFile::bool($row['store_is_default'] ?? '', false));
            $defaults += $row['website_is_default'] ? 1 : 0;
            $rows[$line] = $row;
        }
        if ($defaults > 1) {
            $this->fail($file, 0, 'website_is_default is set on more than one row');
        }
        return $rows;
    }

    #[\Override]
    protected function write(CsvFile $file, array $rows, array $options, Reporter $reporter): Result
    {
        $result = new Result();
        $done = 0;
        foreach ($rows as $row) {
            $website = $this->website($row, $result);
            $rootCategoryId = $this->rootCategory($row['root_category'], $result);
            $group = $this->group($website, $row, $rootCategoryId);
            $store = $this->store($website, $group, $row, $result);

            if (!$website->getDefaultGroupId()) {
                $website->setDefaultGroupId((int) $group->getId())->save();
            }
            if (!$group->getDefaultStoreId() || $row['store_is_default']) {
                $group->setDefaultStoreId((int) $store->getId())->save();
            }
            $reporter->progress(++$done, count($rows), $row['store_code']);
        }
        Mage::app()->reinitStores();
        Mage::app()->getCache()->cleanType('config');
        $this->resolver->reset();
        return $result;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function website(array $row, Result $result): \Mage_Core_Model_Website
    {
        $code = $row['website_code'];
        $website = Mage::getModel('core/website')->load($code, 'code');
        if (!$website->getId() && ($row['website_previous_code'] ?? '') !== '') {
            $website = Mage::getModel('core/website')->load($row['website_previous_code'], 'code');
        }
        $isNew = !$website->getId();
        $website->setCode($code);
        if ($isNew || ($row['website_name'] ?? '') !== '') {
            $website->setName($row['website_name'] !== '' ? $row['website_name'] : ucfirst($code));
        }
        if (($row['website_sort_order'] ?? '') !== '') {
            $website->setSortOrder((int) $row['website_sort_order']);
        }
        if ($row['website_is_default']) {
            $website->setIsDefault(1);
        }
        $website->save();
        $isNew ? $result->created++ : $result->updated++;
        return $website;
    }

    private function rootCategory(string $name, Result $result): int
    {
        $id = $this->resolver->rootCategoryId($name);
        if ($id !== null) {
            return $id;
        }
        $category = Mage::getModel('catalog/category');
        $category->setStoreId(0)
            ->setName($name)
            ->setIsActive(1)
            ->setIncludeInMenu(1)
            ->setDisplayMode(\Mage_Catalog_Model_Category::DM_PRODUCT)
            ->setAttributeSetId($category->getDefaultAttributeSetId())
            ->setPath((string) \Mage_Catalog_Model_Category::TREE_ROOT_ID)
            ->save();
        $result->created++;
        $this->resolver->reset();
        return (int) $category->getId();
    }

    /**
     * The website's single group: matched by name, else its first group, else created.
     *
     * @param array<string, mixed> $row
     */
    private function group(\Mage_Core_Model_Website $website, array $row, int $rootCategoryId): \Mage_Core_Model_Store_Group
    {
        $name = ($row['group_name'] ?? '') !== '' ? $row['group_name'] : $website->getName();
        $groups = Mage::getResourceModel('core/store_group_collection')
            ->addFieldToFilter('website_id', (int) $website->getId());
        $group = null;
        foreach ($groups as $candidate) {
            if ($group === null || $candidate->getName() === $name) {
                $group = $candidate;
            }
        }
        $group ??= Mage::getModel('core/store_group')->setWebsiteId((int) $website->getId());
        $group->setName($name)->setRootCategoryId($rootCategoryId)->save();
        return $group;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function store(\Mage_Core_Model_Website $website, \Mage_Core_Model_Store_Group $group, array $row, Result $result): \Mage_Core_Model_Store
    {
        $code = $row['store_code'];
        $store = Mage::getModel('core/store')->load($code, 'code');
        if (!$store->getId() && ($row['store_previous_code'] ?? '') !== '') {
            $store = Mage::getModel('core/store')->load($row['store_previous_code'], 'code');
        }
        $isNew = !$store->getId();
        $store->setCode($code)
            ->setWebsiteId((int) $website->getId())
            ->setGroupId((int) $group->getId())
            ->setIsActive($row['store_is_active'] ? 1 : 0);
        if ($isNew || ($row['store_name'] ?? '') !== '') {
            $store->setName($row['store_name'] !== '' ? $row['store_name'] : ucfirst($code));
        }
        if (($row['store_sort_order'] ?? '') !== '') {
            $store->setSortOrder((int) $row['store_sort_order']);
        }
        $store->save();
        $isNew ? $result->created++ : $result->updated++;
        return $store;
    }
}
