<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package MahoCLI
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use Mage;
use Mage_Catalog_Model_Product;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'legacy:fix-bundle-option-titles',
    description: 'Restore missing default bundle option titles from a store view',
    help: 'Choose a store view whose titles can also serve as default titles. The command copies a title only when the default title is missing or blank. Run it first to review the option IDs. Add --force to apply the changes.',
)]
class LegacyFixBundleOptionTitles extends BaseMahoCommand
{
    /** @param list<string|int> $option */
    public function __invoke(
        OutputInterface $output,
        #[Argument(description: 'Code of the store view to copy titles from')]
        string $sourceStore,
        #[Option(description: 'Repair only this option ID (repeat for each option)')]
        array $option = [],
        #[Option(description: 'Apply the changes instead of only reporting them', name: 'force')]
        bool $apply = false,
    ): int {
        $this->initMaho();

        $store = Mage::app()->getStores(false, true)[$sourceStore] ?? null;
        if ($store === null) {
            $output->writeln('<error>Choose the code of an existing store view.</error>');
            return Command::INVALID;
        }
        $optionIds = array_map(static fn(string|int $id): int|false => filter_var($id, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]), $option);
        if (in_array(false, $optionIds, true)) {
            $output->writeln('<error>Each option ID must be a positive integer.</error>');
            return Command::INVALID;
        }

        $resource = Mage::getSingleton('core/resource');
        $connection = $resource->getConnection('core_write');
        $table = $resource->getTableName('bundle/option_value');
        $storeId = (int) $store->getId();
        $select = $connection->select()
            ->from(['opt' => $resource->getTableName('bundle/option')], ['option_id', 'parent_id'])
            ->joinLeft(['def' => $table], 'def.option_id = opt.option_id AND def.store_id = 0', ['default_id' => 'value_id'])
            ->joinLeft(['src' => $table], 'src.option_id = opt.option_id AND src.store_id = ' . $storeId, ['source_title' => 'title'])
            ->where("def.title IS NULL OR TRIM(def.title) = ''")
            ->order('opt.option_id ASC');
        if ($optionIds !== []) {
            $select->where('opt.option_id IN (?)', $optionIds);
        }

        $missing = 0;
        $ready = [];
        foreach ($connection->fetchAll($select) as $row) {
            $id = (int) $row['option_id'];
            $title = $row['source_title'];
            if (!is_string($title) || trim($title) === '') {
                $output->writeln("Option $id has no title in the source store view.");
                $missing++;
                continue;
            }
            $ready[] = $row;
            if (!$apply) {
                $output->writeln("Would restore the default title of option $id.");
            }
        }
        $output->writeln("$missing option(s) have no title in the source store view.");
        if (!$apply) {
            $output->writeln(count($ready) . ' option title(s) would be restored.');
            return Command::SUCCESS;
        }

        $restored = 0;
        $tags = [];
        $connection->beginTransaction();
        try {
            foreach ($ready as $row) {
                $id = (int) $row['option_id'];
                if ($row['default_id'] === null) {
                    $changed = $connection->insertIgnore($table, ['option_id' => $id, 'store_id' => 0, 'title' => $row['source_title']]);
                } else {
                    $changed = $connection->update($table, ['title' => $row['source_title']], [
                        'option_id = ?' => $id,
                        'store_id = ?' => 0,
                        "title IS NULL OR TRIM(title) = ''",
                    ]);
                }
                if ($changed > 0) {
                    $restored += $changed;
                    $tags[] = Mage_Catalog_Model_Product::CACHE_TAG . '_' . $row['parent_id'];
                }
            }
            $connection->commit();
        } catch (\Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }

        if ($tags !== []) {
            Mage::app()->cleanCache(array_unique($tags));
        }
        $output->writeln("$restored option title(s) restored.");
        return Command::SUCCESS;
    }
}
