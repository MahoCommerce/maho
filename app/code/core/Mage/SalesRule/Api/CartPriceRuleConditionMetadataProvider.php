<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_SalesRule
 */

declare(strict_types=1);

namespace Mage\SalesRule\Api;

use ApiPlatform\Metadata\Operation;
use Symfony\Component\HttpFoundation\JsonResponse;

final class CartPriceRuleConditionMetadataProvider extends \Maho\ApiPlatform\Provider
{
    use ConditionMetadataTrait;

    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): JsonResponse
    {
        $user = $this->requireUser();
        $document = $this->conditionMetadata($this->adminLocale());

        $filters = ($context['filters'] ?? []) + ($context['request']?->query->all() ?? []);
        $knownVersion = $this->stringFilter($filters, 'knownVersion');
        if ($knownVersion !== null && hash_equals($document['version'], $knownVersion)) {
            return $this->respondRaw([
                'version' => $document['version'],
                'unchanged' => true,
                'scope' => $this->ruleScope($user),
            ]);
        }

        $document['scope'] = $this->ruleScope($user);
        return $this->respondRaw($document);
    }
}
