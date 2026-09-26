<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Reports
 */

declare(strict_types=1);

namespace Mage\Reports\Api;

use Maho\ApiPlatform\Security\ApiUser;

final class SearchTermsReportProvider extends ReportProviderBase
{
    /**
     * Sort value => column and direction of the search terms.
     */
    public const SORTS = [
        'popularity' => ['popularity', 'DESC'],
        'results' => ['num_results', 'DESC'],
        'updatedAt' => ['updated_at', 'DESC'],
    ];

    #[\Override]
    protected function buildReport(array $filters, ApiUser $user): array
    {
        $query = ReportQuery::forScope($filters, $user);
        $sort = $query->readEnum($filters, 'sort', array_keys(self::SORTS)) ?? 'popularity';
        ['page' => $page, 'pageSize' => $pageSize] = $this->extractPagination(['filters' => $filters], 20, 100);

        /** @var \Mage_CatalogSearch_Model_Resource_Query_Collection $collection */
        $collection = \Mage::getResourceModel('catalogsearch/query_collection');
        if ($query->scoped) {
            $collection->addFieldToFilter('main_table.store_id', ['in' => $query->storeIds]);
        }
        foreach ($this->searchWords($this->stringFilter($filters, 'search')) as $word) {
            $collection->addFieldToFilter('main_table.query_text', ['like' => '%' . $word . '%']);
        }
        [$column, $direction] = self::SORTS[$sort];
        $collection->getSelect()->order("main_table.{$column} {$direction}")->order('main_table.query_id DESC');
        $collection->setPageSize($pageSize)->setCurPage($page);

        $total = (int) $collection->getSize();
        $member = [];
        if (($page - 1) * $pageSize < $total) {
            foreach ($collection as $term) {
                $member[] = [
                    'id' => (int) $term->getId(),
                    'queryText' => (string) $term->getData('query_text'),
                    'storeId' => (int) $term->getData('store_id'),
                    'results' => (int) $term->getData('num_results'),
                    'uses' => (int) $term->getData('popularity'),
                    'updatedAt' => self::isoDate($term->getData('updated_at')),
                ];
            }
        }

        return [
            'report' => 'search-terms',
            'sort' => $sort,
            'scope' => $query->scopeArray(),
            'totalItems' => $total,
            'page' => $page,
            'pageSize' => $pageSize,
            'member' => $member,
        ];
    }
}
