<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package MahoCLI
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use Mage;
use Mage_Catalog_Helper_Data;
use Mage_Catalog_Model_Product;
use Maho\Db\Select;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @phpstan-type PriceRow array{sku: string, what: string, scope: string, default: float|null, stored: float, rate: float|null}
 */
#[AsCommand(
    name: 'catalog:price:website-overrides',
    description: 'List price rows scoped to a store or website, flagging those that look like an old converted seed',
)]
class CatalogPriceWebsiteOverrides extends BaseMahoCommand
{
    /** @var array<int, float|null> */
    private array $storeRates = [];

    private ?int $limit = null;

    #[\Override]
    protected function configure(): void
    {
        $this->addOption('suspect-only', null, InputOption::VALUE_NONE, 'Show only rows that match a rate')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'List at most this many rows');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initMaho();

        $this->limit = $input->getOption('limit') === null ? null : (int) $input->getOption('limit');
        if ($this->limit !== null && $this->limit < 1) {
            $output->writeln('<error>--limit must be 1 or more.</error>');
            return Command::FAILURE;
        }

        $found = [];
        foreach (Mage_Catalog_Helper_Data::websiteScopePriceRowSelects() as $kind => $entry) {
            $found = array_merge($found, match ($kind) {
                'attribute' => $this->attributeRows($entry['select'], $entry['table']),
                'option' => $this->optionRows($entry['select'], $entry['table']),
                'option_value' => $this->optionValueRows($entry['select'], $entry['table']),
                'link' => $this->linkRows($entry['select'], $entry['table']),
                default => throw new \LogicException(sprintf('No listing for the "%s" price rows', $kind)),
            });
        }
        usort($found, fn(array $a, array $b): int => [$a['sku'], $a['what'], $a['scope']] <=> [$b['sku'], $b['what'], $b['scope']]);

        if ($this->limit !== null) {
            $found = array_slice($found, 0, $this->limit);
        }

        $suspectOnly = (bool) $input->getOption('suspect-only');
        $rows = [];
        $suspects = 0;

        foreach ($found as $row) {
            $implied = $row['default'] > 0 ? $row['stored'] / $row['default'] : null;
            // Compare against the number the old conversion wrote, not against the ratio: the
            // stored row keeps 4 decimals, so a small default price inflates the ratio error.
            $suspect = $implied !== null && $row['rate'] !== null && $row['rate'] != 1.0
                && abs($row['stored'] - round($row['default'] * $row['rate'], 4)) < 0.00001;
            if ($suspect) {
                $suspects++;
            } elseif ($suspectOnly) {
                continue;
            }

            $rows[] = [
                $row['sku'],
                $row['what'],
                $row['scope'],
                $row['default'] === null ? '-' : (string) $row['default'],
                (string) $row['stored'],
                $implied === null ? '-' : sprintf('%.4f', $implied),
                $row['rate'] === null ? 'none' : sprintf('%.4f', $row['rate']),
                $suspect ? 'yes' : '',
            ];
        }

        if (!$rows) {
            $output->writeln('No store-scope or website-scope price rows found.');
            return Command::SUCCESS;
        }

        $table = new Table($output);
        $table->setHeaders(['sku', 'what', 'scope', 'default', 'stored', 'stored/default', 'rate', 'matches rate']);
        $table->setRows($rows);
        $table->render();

        $output->writeln('');
        $output->writeln(sprintf(
            '%d row(s) listed, %d of which match the current rate exactly.',
            count($rows),
            $suspects,
        ));
        if ($this->limit !== null) {
            $output->writeln(sprintf(
                'The --limit option read the first %d row(s) of each kind, ordered by sku. The counts'
                . ' above cover the listed rows only, not the whole catalog.',
                $this->limit,
            ));
        }
        $output->writeln(
            'A row matching the rate was most likely written by the old save-time conversion rather than'
            . ' by a merchant. Since Maho no longer seeds them, such a row now counts as an explicit price'
            . ' and stops following the rate. Delete the ones you did not set yourself.',
        );

        return Command::SUCCESS;
    }

    /**
     * @return list<PriceRow>
     */
    private function attributeRows(Select $select, string $table): array
    {
        $select->columns(['store_id', 'attribute_id', 'store_value' => 'value'], 's')
            ->joinLeft(
                ['d' => $table],
                's.entity_id = d.entity_id AND s.attribute_id = d.attribute_id AND d.store_id = 0',
                ['default_value' => 'value'],
            )
            ->join(['e' => $this->table('catalog/product')], 'e.entity_id = s.entity_id', ['sku']);
        $this->applyLimit($select);

        $rows = [];
        foreach ($this->fetchAll($select) as $row) {
            $code = Mage::getSingleton('eav/config')
                ->getAttribute(Mage_Catalog_Model_Product::ENTITY, (int) $row['attribute_id'])
                ->getAttributeCode();
            $rows[] = $this->storeScopedRow($row, $code);
        }

        return $rows;
    }

    /**
     * @return list<PriceRow>
     */
    private function optionRows(Select $select, string $table): array
    {
        $select->columns(['store_id', 'option_id', 'store_value' => 'price'], 's')
            ->joinLeft(['d' => $table], 's.option_id = d.option_id AND d.store_id = 0', ['default_value' => 'price'])
            ->join(['o' => $this->table('catalog/product_option')], 'o.option_id = s.option_id', [])
            ->join(['e' => $this->table('catalog/product')], 'e.entity_id = o.product_id', ['sku']);
        $this->applyLimit($select);

        $rows = [];
        foreach ($this->fetchAll($select) as $row) {
            $rows[] = $this->storeScopedRow($row, sprintf('option #%d', $row['option_id']));
        }

        return $rows;
    }

    /**
     * @return list<PriceRow>
     */
    private function optionValueRows(Select $select, string $table): array
    {
        $select->columns(['store_id', 'option_type_id', 'store_value' => 'price'], 's')
            ->joinLeft(
                ['d' => $table],
                's.option_type_id = d.option_type_id AND d.store_id = 0',
                ['default_value' => 'price'],
            )
            ->join(['v' => $this->table('catalog/product_option_type_value')], 'v.option_type_id = s.option_type_id', [])
            ->join(['o' => $this->table('catalog/product_option')], 'o.option_id = v.option_id', [])
            ->join(['e' => $this->table('catalog/product')], 'e.entity_id = o.product_id', ['sku']);
        $this->applyLimit($select);

        $rows = [];
        foreach ($this->fetchAll($select) as $row) {
            $rows[] = $this->storeScopedRow($row, sprintf('option value #%d', $row['option_type_id']));
        }

        return $rows;
    }

    /**
     * @return list<PriceRow>
     */
    private function linkRows(Select $select, string $table): array
    {
        $select->columns(['website_id', 'link_id', 'store_value' => 'price'], 's')
            ->joinLeft(['d' => $table], 's.link_id = d.link_id AND d.website_id = 0', ['default_value' => 'price'])
            ->join(['l' => $this->table('downloadable/link')], 'l.link_id = s.link_id', [])
            ->join(['e' => $this->table('catalog/product')], 'e.entity_id = l.product_id', ['sku']);
        $this->applyLimit($select);

        $rows = [];
        foreach ($this->fetchAll($select) as $row) {
            $websiteId = (int) $row['website_id'];
            $rows[] = [
                'sku' => (string) $row['sku'],
                'what' => sprintf('link #%d', $row['link_id']),
                'scope' => $this->websiteLabel($websiteId),
                'default' => $row['default_value'] === null ? null : (float) $row['default_value'],
                'stored' => (float) $row['store_value'],
                'rate' => $this->websiteRate($websiteId),
            ];
        }

        return $rows;
    }

    /**
     * Reading every row to throw most of them away can exhaust memory on a large catalog. Sku is
     * the first sort key, so the first N rows of each kind hold the first N rows of the listing.
     */
    private function applyLimit(Select $select): void
    {
        if ($this->limit !== null) {
            $select->order('e.sku')->limit($this->limit);
        }
    }

    private function table(string $alias): string
    {
        return Mage::getSingleton('core/resource')->getTableName($alias);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchAll(Select $select): array
    {
        return Mage::getSingleton('core/resource')->getConnection('core_read')->fetchAll($select);
    }

    /**
     * @param array<string, mixed> $row
     * @return PriceRow
     */
    private function storeScopedRow(array $row, string $what): array
    {
        $storeId = (int) $row['store_id'];

        return [
            'sku' => (string) $row['sku'],
            'what' => $what,
            'scope' => $this->storeLabel($storeId),
            'default' => $row['default_value'] === null ? null : (float) $row['default_value'],
            'stored' => (float) $row['store_value'],
            'rate' => $this->storeRate($storeId),
        ];
    }

    private function storeLabel(int $storeId): string
    {
        try {
            return Mage::app()->getStore($storeId)->getCode();
        } catch (\Throwable) {
            return sprintf('store #%d, deleted', $storeId);
        }
    }

    private function storeRate(int $storeId): ?float
    {
        if (!array_key_exists($storeId, $this->storeRates)) {
            try {
                $this->storeRates[$storeId] = Mage::helper('catalog')->getWebsitePriceRate($storeId);
            } catch (\Throwable) {
                $this->storeRates[$storeId] = null;
            }
        }

        return $this->storeRates[$storeId];
    }

    private function websiteLabel(int $websiteId): string
    {
        try {
            return Mage::app()->getWebsite($websiteId)->getCode();
        } catch (\Throwable) {
            return sprintf('website #%d, deleted', $websiteId);
        }
    }

    private function websiteRate(int $websiteId): ?float
    {
        try {
            return $this->storeRate((int) Mage::app()->getWebsite($websiteId)->getDefaultStore()->getId());
        } catch (\Throwable) {
            return null;
        }
    }
}
