<?php

/**
 * Explain the change of a metric of the report explorer between two periods.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Reports
 */

declare(strict_types=1);

namespace Mage\Reports\Api;

use ApiPlatform\Metadata\Operation;
use Maho\ApiPlatform\Http\RequestInput;
use Maho\ApiPlatform\Exception\ValidationException;
use Symfony\Component\HttpFoundation\JsonResponse;

final class ExplorerExplanationProvider extends \Maho\ApiPlatform\Provider
{
    use ExplorerAccessTrait;

    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): JsonResponse
    {
        $access = $this->explorerAccess($this->requireUser());
        $input = $this->explorerInput($context);
        $read = RequestInput::of($input);
        $metric = $read->string('metric') ?? throw ValidationException::requiredField('metric');
        $request = $this->explorerRequest($input + ['metrics' => [$metric]], $access, ['metric', 'explainDimensions', 'top']);
        $dimensions = is_string($input['explainDimensions'] ?? null)
            ? array_values(array_filter(array_map(trim(...), explode(',', $input['explainDimensions']))))
            : $read->list('explainDimensions');
        return $this->respondRaw(\Mage::getService('reports/explorer')->explain(
            $request,
            $access,
            $metric,
            $dimensions === null ? null : array_map(strval(...), $dimensions),
            min(20, max(1, $read->int('top') ?? 5)),
        ));
    }
}
