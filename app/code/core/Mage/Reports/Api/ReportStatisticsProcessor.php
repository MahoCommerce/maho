<?php

/**
 * Give a refresh of the report statistics to the statistics service.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Reports
 */

declare(strict_types=1);

namespace Mage\Reports\Api;

use ApiPlatform\Metadata\Operation;
use Maho\ApiPlatform\Exception\ValidationException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class ReportStatisticsProcessor extends \Maho\ApiPlatform\Processor
{
    public const MODE_RECENT = 'recent';
    public const MODE_LIFETIME = 'lifetime';

    public const RATE_LIMIT_WINDOW = \Mage_Reports_Service_Statistics::RATE_LIMIT_WINDOW;

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): JsonResponse
    {
        $user = $this->requireUser();
        if ($user->getAllowedStoreIds() !== null) {
            throw new AccessDeniedHttpException('A token with a store restriction cannot refresh statistics, because a refresh changes the statistics of all stores');
        }

        $body = $this->parseRequestBody($context['request'] ?? null);
        $mode = $body['mode'] ?? self::MODE_RECENT;
        if (!in_array($mode, [self::MODE_RECENT, self::MODE_LIFETIME], true)) {
            throw new ValidationException('mode must be recent or lifetime', 'mode', 'Choice');
        }
        $reports = $body['reports'] ?? [];
        if (!is_array($reports) || !array_is_list($reports)) {
            throw new BadRequestHttpException('reports must be a list of report codes');
        }

        /** @var \Mage_Reports_Service_Statistics $service */
        $service = \Mage::getService('reports/statistics');
        if ($mode === self::MODE_RECENT) {
            $codes = $service->refreshRecent($reports, $user->getUserIdentifier());
            $alreadyQueued = [];
        } else {
            $queued = $service->refreshLifetime($reports, $user->getUserIdentifier());
            $codes = array_keys($queued);
            $alreadyQueued = array_keys(array_filter($queued, static fn(bool $queuedNow): bool => !$queuedNow));
        }

        return $this->respondRaw(
            ['mode' => $mode, 'queued' => $mode === self::MODE_LIFETIME, 'reports' => $codes, 'alreadyQueued' => $alreadyQueued]
                + ReportStatisticsProvider::statisticsList(),
            $mode === self::MODE_LIFETIME ? JsonResponse::HTTP_ACCEPTED : JsonResponse::HTTP_OK,
        );
    }
}
