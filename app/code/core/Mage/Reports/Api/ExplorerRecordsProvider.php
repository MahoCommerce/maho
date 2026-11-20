<?php

/**
 * List the rows behind the numbers of a report of the report explorer.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Reports
 */

declare(strict_types=1);

namespace Mage\Reports\Api;

use ApiPlatform\Metadata\Operation;
use Maho\ApiPlatform\Http\RequestInput;
use Symfony\Component\HttpFoundation\JsonResponse;

final class ExplorerRecordsProvider extends \Maho\ApiPlatform\Provider
{
    use ExplorerAccessTrait;

    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): JsonResponse
    {
        $access = $this->explorerAccess($this->requireUser());
        $input = $this->explorerInput($context);
        $read = RequestInput::of($input);
        $request = $this->explorerRequest($input, $access, ['sort', 'limit', 'offset']);
        return $this->respondRaw(\Mage::getService('reports/explorer')->records(
            $request,
            $access,
            $read->string('sort'),
            $read->int('limit') ?? \Mage_Reports_Model_Explorer_Runner::RECORDS_LIMIT,
            max(0, $read->int('offset') ?? 0),
        ));
    }
}
