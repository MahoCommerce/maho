<?php

/**
 * Read the monthly targets of the report explorer.
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
use Maho\ApiPlatform\Exception\ValidationException;
use Maho\ApiPlatform\Http\RequestInput;
use Maho\ApiPlatform\Security\AdminAcl;

final class ReportTargetProvider extends \Maho\ApiPlatform\Provider
{
    use ExplorerAccessTrait;

    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): TraversablePaginator|\Mage_Reports_Model_Target
    {
        AdminAcl::checkPath(ReportTarget::ADMIN_RESOURCE);
        $access = $this->explorerAccess($this->requireUser());
        $service = \Mage::getService('reports/target');
        if ($operation instanceof CollectionOperationInterface) {
            $input = RequestInput::of(ReportProviderBase::requestFilters($context));
            $dataset = $input->string('dataset') ?? throw ValidationException::requiredField('dataset');
            $targets = array_map($this->readable(...), $service->listFor($dataset, $access, $input->string('metric')));
            ['page' => $page, 'pageSize' => $pageSize] = $this->extractPagination($context, $this->defaultPageSize, $this->maxPageSize);
            return new TraversablePaginator(new \ArrayIterator(array_slice($targets, ($page - 1) * $pageSize, $pageSize)), $page, $pageSize, count($targets));
        }
        if ($operation instanceof Post && !isset($uriVariables['id'])) {
            return $service->newTarget();
        }
        return $this->readable($service->getById((int) ($uriVariables['id'] ?? 0), $access));
    }

    /** The table holds the first day of the month, and the API shows the month. */
    private function readable(\Mage_Reports_Model_Target $target): \Mage_Reports_Model_Target
    {
        return $target->setMonth(substr((string) $target->getMonth(), 0, 7));
    }
}
