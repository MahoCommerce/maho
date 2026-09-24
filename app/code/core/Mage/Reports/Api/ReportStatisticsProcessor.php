<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Reports
 */

declare(strict_types=1);

namespace Mage\Reports\Api;

use ApiPlatform\Metadata\Operation;
use Maho\Queue\QueueManager;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;

final class ReportStatisticsProcessor extends \Maho\ApiPlatform\Processor
{
    public const MODE_RECENT = 'recent';
    public const MODE_LIFETIME = 'lifetime';

    /**
     * The window of the rate limit system/rate_limit/reports_refresh, in seconds.
     */
    public const RATE_LIMIT_WINDOW = 60;

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
            throw new BadRequestHttpException('mode must be recent or lifetime');
        }
        $codes = $this->readCodes($body['reports'] ?? []);

        $this->checkRateLimit('reports_refresh:' . $user->getUserIdentifier(), 'reports_refresh', self::RATE_LIMIT_WINDOW);

        /** @var \Mage_Reports_Model_Statistics $statistics */
        $statistics = \Mage::getModel('reports/statistics');
        $queued = false;
        $alreadyQueued = [];

        if ($mode === self::MODE_RECENT) {
            $statistics->refreshRecent($codes);
        } elseif (\Mage::helper('core')->isModuleEnabled('Maho_Queue')) {
            $queued = true;
            foreach ($codes as $code) {
                $message = new \Mage_Reports_Model_Queue_RefreshStatistics($code);
                $envelope = QueueManager::dispatch(
                    $message,
                    queue: \Mage_Reports_Model_Queue_RefreshStatistics::QUEUE_NAME,
                    dedupeKey: $message->getDedupeKey(),
                );
                if ($envelope->last(TransportMessageIdStamp::class) === null) {
                    $alreadyQueued[] = $code;
                }
            }
        } else {
            $statistics->refreshLifetime($codes);
        }

        return $this->respondRaw(
            ['mode' => $mode, 'queued' => $queued, 'reports' => $codes, 'alreadyQueued' => $alreadyQueued]
                + ReportStatisticsProvider::statisticsList(),
            $queued ? JsonResponse::HTTP_ACCEPTED : JsonResponse::HTTP_OK,
        );
    }

    /**
     * @return list<string>
     */
    private function readCodes(mixed $reports): array
    {
        /** @var \Mage_Reports_Model_Statistics $statistics */
        $statistics = \Mage::getModel('reports/statistics');
        if ($reports === null || $reports === []) {
            return $statistics->getCodes();
        }
        if (!is_array($reports) || !array_is_list($reports)) {
            throw new BadRequestHttpException('reports must be a list of report codes');
        }
        $codes = [];
        foreach ($reports as $code) {
            if (!is_string($code) || !$statistics->isKnownCode($code)) {
                throw new BadRequestHttpException('reports must be a list of: ' . implode(', ', $statistics->getCodes()));
            }
            $codes[$code] = $code;
        }
        // The order of the map, so that the result does not depend on the order of the request
        return array_values(array_intersect($statistics->getCodes(), $codes));
    }
}
