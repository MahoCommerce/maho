<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Reports
 */

declare(strict_types=1);

namespace Mage\Reports\Api;

use ApiPlatform\Metadata\Operation;
use Symfony\Component\HttpFoundation\JsonResponse;

final class ReportStatisticsProvider extends \Maho\ApiPlatform\Provider
{
    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): JsonResponse
    {
        $this->requireUser();
        return $this->respondRaw(self::statisticsList());
    }

    /**
     * @return array{totalItems: int, member: list<array{code: string, label: string, description: string, updatedAt: ?string}>}
     */
    public static function statisticsList(): array
    {
        /** @var \Mage_Reports_Model_Statistics $statistics */
        $statistics = \Mage::getModel('reports/statistics');
        $member = [];
        foreach ($statistics->getCodes() as $code) {
            $member[] = [
                'code' => $code,
                'label' => $statistics->getLabel($code),
                'description' => $statistics->getDescription($code),
                'updatedAt' => ReportProviderBase::isoDate($statistics->getUpdatedAt($code)),
            ];
        }
        return ['totalItems' => count($member), 'member' => $member];
    }
}
