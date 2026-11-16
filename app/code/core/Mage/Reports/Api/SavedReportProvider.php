<?php

/**
 * Read the saved reports of the report explorer.
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
use Maho\ApiPlatform\Security\AdminAcl;

final class SavedReportProvider extends \Maho\ApiPlatform\Provider
{
    use ExplorerAccessTrait;

    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): TraversablePaginator|\Mage_Reports_Model_SavedReport
    {
        AdminAcl::checkPath(SavedReport::ADMIN_RESOURCE);
        $user = $this->requireUser();
        $service = \Mage::getService('reports/savedReport');

        if ($operation instanceof CollectionOperationInterface) {
            $reports = $service->listFor($user->getAdminId(), $this->explorerAccess($user));
            $pinned = $user->getAdminId() === null ? [] : $service->pinnedIds($user->getAdminId());
            foreach ($reports as $report) {
                $report->setIsPinned(in_array((int) $report->getId(), $pinned, true));
            }
            ['page' => $page, 'pageSize' => $pageSize] = $this->extractPagination($context, $this->defaultPageSize, $this->maxPageSize);
            $items = array_slice($reports, ($page - 1) * $pageSize, $pageSize);
            return new TraversablePaginator(new \ArrayIterator($items), $page, $pageSize, count($reports));
        }
        if ($operation instanceof Post && !isset($uriVariables['id'])) {
            return $service->newReport();
        }
        $report = $service->getById((int) ($uriVariables['id'] ?? 0), $user->getAdminId());
        return $report->setIsPinned($user->getAdminId() !== null && $service->isPinned($report, $user->getAdminId()));
    }
}
