<?php

/**
 * Run a report of the report explorer for the GET and QUERY operations.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Reports
 */

declare(strict_types=1);

namespace Mage\Reports\Api;

use ApiPlatform\Metadata\Operation;
use Symfony\Component\HttpFoundation\JsonResponse;

final class ExplorerReportProvider extends \Maho\ApiPlatform\Provider
{
    use ExplorerAccessTrait;

    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): JsonResponse
    {
        $access = $this->explorerAccess($this->requireUser());
        $request = $this->explorerRequest($this->explorerInput($context), $access);
        return $this->respondRaw(\Mage::getService('reports/explorer')->run($request, $access));
    }
}
