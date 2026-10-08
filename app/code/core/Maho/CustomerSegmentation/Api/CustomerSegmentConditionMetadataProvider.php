<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_CustomerSegmentation
 */

declare(strict_types=1);

namespace Maho\CustomerSegmentation\Api;

use ApiPlatform\Metadata\Operation;
use Maho\ApiPlatform\Security\AdminAcl;
use Maho\ApiPlatform\Trait\ConditionMetadataProviderTrait;
use Symfony\Component\HttpFoundation\JsonResponse;

final class CustomerSegmentConditionMetadataProvider extends \Maho\ApiPlatform\Provider
{
    use ConditionMetadataProviderTrait;
    use ConditionMetadataTrait;

    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): JsonResponse
    {
        AdminAcl::checkPath(CustomerSegment::ACL_MANAGE);
        return $this->conditionMetadataResponse($context);
    }
}
