<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Log
 */

declare(strict_types=1);

class Mage_Log_Block_Dashboard_Trends extends Mage_Adminhtml_Block_Dashboard_Graph
{
    public function __construct()
    {
        $this->setHtmlId('visitor_trends');
        parent::__construct();
    }

    #[\Override]
    protected function _prepareData(): void
    {
        $this->setDataHelperName('log/dashboard');

        // Get trend data
        $trendData = Mage::helper('log/dashboard')->getVisitorTrends(30);

        $maxValue = empty($trendData['data']) ? 0 : max($trendData['data']);
        $this->_axisLabels = [
            'x' => $trendData['labels'],
            'y' => range(0, $maxValue),
        ];

        $this->_allSeries = [
            'visitors' => $trendData['data'],
        ];

        // Don't call parent as we're handling data differently
    }

    #[\Override]
    public function getAxisLabels(string $axis = 'x'): array
    {
        if (!$this->_axisLabels) {
            $this->_prepareData();
        }
        return $this->_axisLabels[$axis] ?? [];
    }

    #[\Override]
    public function getAllSeries(): array
    {
        if (!$this->_allSeries) {
            $this->_prepareData();
        }
        return $this->_allSeries;
    }

    #[\Override]
    public function getCount(): bool
    {
        $series = $this->getAllSeries();
        return !empty($series['visitors']) && array_sum($series['visitors']) > 0;
    }

    #[\Override]
    public function processData(): array
    {
        // Data is already processed in _prepareData
        return [];
    }
}
