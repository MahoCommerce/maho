<?php

/**
 * Give the index process operations of the API to the index process service.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Index
 */

declare(strict_types=1);

namespace Mage\Index\Api;

use ApiPlatform\Metadata\Operation;
use Maho\ApiPlatform\Exception\ValidationException;
use Maho\Job\StepState;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class IndexProcessProcessor extends \Maho\ApiPlatform\Processor
{
    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): IndexProcess|JsonResponse
    {
        $this->requireUser();
        $service = IndexProcessProvider::service();
        $name = $operation->getName();

        if ($name === 'index_process_reindex_all') {
            set_time_limit(0);
            $queue = $service->getQueue();
            $oldData = [];
            foreach ($queue as $process) {
                $oldData[$process->getIndexerCode()] = $process->getData();
            }
            $service->reindexAll(
                $queue,
                onEnd: function (\Mage_Index_Model_Process $process, array $result) use ($oldData): void {
                    if ($result['state'] === StepState::Success) {
                        $this->logApiActivity('index_process', 'reindex', $oldData[$process->getIndexerCode()] ?? null, $process);
                    }
                },
            );
            return $this->respondRaw([
                'success' => true,
                'reindexed' => count($queue),
                'processes' => array_map(
                    static fn(\Mage_Index_Model_Process $process): array => IndexProcess::fromModel($process)->toArray(),
                    $service->getList(),
                ),
            ]);
        }

        $process = $service->getById((int) ($uriVariables['id'] ?? 0));
        $oldData = $process->getData();

        if ($name === 'index_process_reindex') {
            set_time_limit(0);
            $service->reindex($process);
            $this->logApiActivity('index_process', 'reindex', $oldData, $process);
            return IndexProcess::fromModel($service->getById((int) $process->getId()));
        }

        $body = $this->parseRequestBody($context['request'] ?? null);
        if (!array_key_exists('mode', $body)) {
            throw ValidationException::requiredField('mode');
        }
        if (!is_string($body['mode'])) {
            throw new BadRequestHttpException('mode must be a string');
        }
        $service->setMode($process, $body['mode']);
        $this->logApiActivity('index_process', 'update', $oldData, $process);

        return IndexProcess::fromModel($service->getById((int) $process->getId()));
    }
}
