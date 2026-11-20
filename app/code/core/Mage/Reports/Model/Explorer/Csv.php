<?php

/**
 * Write the rows of a report request as CSV, with the labels of the dimensions and, for a comparison, the earlier values and the change.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Reports
 */

declare(strict_types=1);

use Maho\Report\Request;

class Mage_Reports_Model_Explorer_Csv
{
    /**
     * @param resource $handle
     * @return int the number of rows
     */
    public function write($handle, Request $request, Mage_Reports_Model_Explorer_Access $access, int $maxRows): int
    {
        $helper = Mage::helper('reports');
        $core = Mage::helper('core');
        $header = null;
        return Mage::getSingleton('reports/explorer_runner')->each(
            $request,
            $access,
            $maxRows,
            function (array $row, array $result) use ($handle, $helper, $core, &$header): void {
                $compare = $result['comparePeriod'] !== null;
                if ($header === null) {
                    $header = [];
                    foreach ($result['columns'] as $column) {
                        $header[] = $column['label'];
                        if ($column['kind'] === 'metric' && $compare) {
                            $header[] = $helper->__('%s (earlier period)', $column['label']);
                            $header[] = $helper->__('%s change %%', $column['label']);
                        }
                    }
                    fputcsv($handle, $core->getEscapedCSVData($header), ',', '"', '');
                }
                $line = [];
                foreach ($result['columns'] as $column) {
                    $key = $column['key'];
                    if ($column['kind'] === 'dimension') {
                        $line[] = $row['labels'][$key] ?? (string) ($row['dimensions'][$key] ?? '');
                        continue;
                    }
                    $line[] = $row['values'][$key] ?? '';
                    if ($compare) {
                        $line[] = $row['compare'][$key] ?? '';
                        $line[] = $row['change'][$key]['percent'] ?? '';
                    }
                }
                fputcsv($handle, $core->getEscapedCSVData($line), ',', '"', '');
            },
        );
    }
}
