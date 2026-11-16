<?php

/**
 * The report datasets that the modules declare under global/reports/datasets in their config.xml.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Reports
 */

declare(strict_types=1);

use Maho\Db\Adapter\AdapterInterface;
use Maho\Report\DatasetProvider;
use Maho\Report\Definition\Dataset;

class Mage_Reports_Model_Explorer_Registry
{
    public const CONNECTION = 'report_read';
    public const EVENT_BUILD = 'report_dataset_build';

    /** @var array<string, Dataset>|null */
    private ?array $datasets = null;

    /** The connection of the report queries. local.xml can point it at a replica, or give it a timeout. */
    public function getAdapter(): AdapterInterface
    {
        $adapter = Mage::getSingleton('core/resource')->getConnection(self::CONNECTION);
        if (!$adapter instanceof AdapterInterface) {
            throw new RuntimeException('The report_read database connection is not available.');
        }
        return $adapter;
    }

    /** @return array<string, Dataset> */
    public function getDatasets(): array
    {
        if ($this->datasets !== null) {
            return $this->datasets;
        }

        $this->datasets = [];
        $node = Mage::getConfig()->getNode('global/reports/datasets');
        if (!$node) {
            return $this->datasets;
        }
        $adapter = $this->getAdapter();
        foreach ($node->children() as $code => $config) {
            $alias = (string) $config->class;
            $provider = $alias === '' ? null : Mage::getModel($alias);
            if (!$provider instanceof DatasetProvider) {
                Mage::log("The report dataset {$code} names no class that implements DatasetProvider.", Mage::LOG_WARNING);
                continue;
            }
            $dataset = $provider->build($adapter);
            Mage::dispatchEvent(self::EVENT_BUILD, ['dataset' => $dataset]);
            $this->datasets[$dataset->code] = $dataset;
        }
        return $this->datasets;
    }

    public function getDataset(string $code): ?Dataset
    {
        return $this->getDatasets()[$code] ?? null;
    }

    /** Forget the datasets, so that the next call builds them again. */
    public function reset(): void
    {
        $this->datasets = null;
    }
}
