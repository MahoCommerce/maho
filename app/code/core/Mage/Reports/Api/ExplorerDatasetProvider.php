<?php

/**
 * List the datasets of the report explorer that the caller may read.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Reports
 */

declare(strict_types=1);

namespace Mage\Reports\Api;

use ApiPlatform\Metadata\Operation;
use Symfony\Component\HttpFoundation\JsonResponse;

final class ExplorerDatasetProvider extends \Maho\ApiPlatform\Provider
{
    use ExplorerAccessTrait;

    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): JsonResponse
    {
        $access = $this->explorerAccess($this->requireUser());
        return $this->respondRaw(\Mage::getService('reports/explorer')->datasets($access));
    }
}
