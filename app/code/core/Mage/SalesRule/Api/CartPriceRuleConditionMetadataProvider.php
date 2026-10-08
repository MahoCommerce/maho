<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_SalesRule
 */

declare(strict_types=1);

namespace Mage\SalesRule\Api;

use ApiPlatform\Metadata\Operation;
use Maho\ApiPlatform\Trait\ConditionMetadataProviderTrait;
use Symfony\Component\HttpFoundation\JsonResponse;

final class CartPriceRuleConditionMetadataProvider extends \Maho\ApiPlatform\Provider
{
    use ConditionMetadataProviderTrait;
    use ConditionMetadataTrait;

    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): JsonResponse
    {
        return $this->conditionMetadataResponse($context);
    }
}
