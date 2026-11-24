<?php

/**
 * Read the notes on the days of the report charts.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Reports
 */

declare(strict_types=1);

namespace Mage\Reports\Api;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\Pagination\TraversablePaginator;
use Maho\ApiPlatform\Http\RequestInput;
use Maho\ApiPlatform\Security\AdminAcl;

final class ReportAnnotationProvider extends \Maho\ApiPlatform\Provider
{
    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): TraversablePaginator|\Mage_Reports_Model_Annotation
    {
        AdminAcl::checkPath(ReportAnnotation::ADMIN_RESOURCE);
        $this->requireUser();
        $service = \Mage::getService('reports/annotation');
        if ($operation instanceof CollectionOperationInterface) {
            $input = RequestInput::of(ReportProviderBase::requestFilters($context));
            $collection = \Mage::getResourceModel('reports/annotation_collection')->setOrder('annotation_date', 'ASC');
            if (($from = $input->string('from')) !== null) {
                $collection->addFieldToFilter('annotation_date', ['gteq' => $from]);
            }
            if (($to = $input->string('to')) !== null) {
                $collection->addFieldToFilter('annotation_date', ['lteq' => $to]);
            }
            ['page' => $page, 'pageSize' => $pageSize] = $this->extractPagination($context, $this->defaultPageSize, $this->maxPageSize);
            $collection->setPageSize($pageSize)->setCurPage($page);
            $items = array_values(array_filter($collection->getItems(), static fn($item): bool => $item instanceof \Mage_Reports_Model_Annotation));
            return new TraversablePaginator(new \ArrayIterator($items), $page, $pageSize, (int) $collection->getSize());
        }
        if ($operation instanceof Post && !isset($uriVariables['id'])) {
            return $service->newAnnotation();
        }
        $annotation = $service->getById((int) ($uriVariables['id'] ?? 0));
        // The table holds a date, and the API shows the day
        return $annotation->setAnnotationDate(substr((string) $annotation->getAnnotationDate(), 0, 10));
    }
}
