<?php

/**
 * Product attribute sets by name, copied from a skeleton set on creation.
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

class AttributeSets extends AbstractImporter
{
    #[\Override]
    protected function requiredColumns(): array
    {
        return ['name'];
    }

    #[\Override]
    protected function prepare(CsvFile $file, array $options): array
    {
        $rows = [];
        foreach ($file as $line => $row) {
            $this->requireValue($file, $line, $row, 'name');
            $row['skeleton'] = ($row['skeleton'] ?? '') !== '' ? $row['skeleton'] : 'Default';
            $this->at($file, $line, fn() => $this->resolver->attributeSetId($row['skeleton']));
            $rows[$line] = $row;
        }
        return $rows;
    }

    #[\Override]
    protected function write(CsvFile $file, array $rows, array $options, Reporter $reporter): Result
    {
        $result = new Result();
        $entityTypeId = (int) Mage::getSingleton('eav/config')->getEntityType('catalog_product')->getId();
        foreach ($rows as $row) {
            $existing = Mage::getResourceModel('eav/entity_attribute_set_collection')
                ->setEntityTypeFilter($entityTypeId)
                ->addFieldToFilter('attribute_set_name', $row['name'])
                ->getFirstItem();
            if ($existing->getId()) {
                if (($row['sort_order'] ?? '') !== '') {
                    Mage::getModel('eav/entity_attribute_set')->load($existing->getId())->setSortOrder((int) $row['sort_order'])->save();
                }
                $result->updated++;
                continue;
            }
            $set = Mage::getModel('eav/entity_attribute_set')
                ->setEntityTypeId($entityTypeId)
                ->setAttributeSetName($row['name']);
            if (($row['sort_order'] ?? '') !== '') {
                $set->setSortOrder((int) $row['sort_order']);
            }
            $set->validate();
            $set->save();
            $set->initFromSkeleton($this->resolver->attributeSetId($row['skeleton']))->save();
            $result->created++;
            $reporter->info("attribute set {$row['name']} created");
        }
        $this->resolver->reset();
        return $result;
    }
}
