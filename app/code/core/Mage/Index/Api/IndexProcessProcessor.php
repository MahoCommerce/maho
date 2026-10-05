<?php

/**
 * Changes the mode of an index process and runs a reindex, as Mage_Index_Adminhtml_ProcessController does.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Index
 */

declare(strict_types=1);

namespace Mage\Index\Api;

use ApiPlatform\Metadata\Operation;
use Maho\ApiPlatform\Exception\ValidationException;
use Maho\ApiPlatform\Security\ApiUser;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class IndexProcessProcessor extends \Maho\ApiPlatform\Processor
{
    private const MODES = [
        \Mage_Index_Model_Process::MODE_REAL_TIME,
        \Mage_Index_Model_Process::MODE_MANUAL,
    ];

    public function __construct(
        Security $security,
        private readonly IndexProcessProvider $provider,
    ) {
        parent::__construct($security);
    }

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): IndexProcess|JsonResponse
    {
        $user = $this->requireUser();
        $name = $operation->getName();
        // The cached indexer keeps the done flag of an earlier reindex in a long worker, so each request starts a new one.
        \Mage::unregister('_singleton/index/indexer');

        if ($name === 'index_process_reindex_all') {
            return $this->reindexAll($user);
        }

        $process = $this->provider->loadProcess((int) ($uriVariables['id'] ?? 0));

        if ($name === 'index_process_reindex') {
            $this->reindex($process, $user);
            return IndexProcess::fromModel($this->provider->loadProcess((int) $process->getId()));
        }

        $body = $this->parseRequestBody($context['request'] ?? null);
        if (!array_key_exists('mode', $body)) {
            throw ValidationException::requiredField('mode');
        }
        $mode = $body['mode'];
        if (!is_string($mode) || !in_array($mode, self::MODES, true)) {
            throw new ValidationException('mode must be real_time or manual', 'mode', 'Choice');
        }

        $oldData = $process->getData();
        $process->setMode($mode);
        $this->safeSave($process, 'update index process');
        $this->logApiActivity('index_process', 'update', $oldData, $process, $user);

        return IndexProcess::fromModel($this->provider->loadProcess((int) $process->getId()));
    }

    private function reindex(\Mage_Index_Model_Process $process, ApiUser $user): void
    {
        if ($process->isLocked()) {
            throw new ConflictHttpException(sprintf('Index "%s" is working now. Try again later.', $process->getIndexer()->getName()));
        }

        set_time_limit(0);
        $oldData = $process->getData();
        try {
            $process->reindexEverything();
        } catch (\Mage_Core_Exception $e) {
            throw new ConflictHttpException($e->getMessage(), $e);
        }
        $this->logApiActivity('index_process', 'reindex', $oldData, $process, $user);
    }

    private function reindexAll(ApiUser $user): JsonResponse
    {
        // The queue holds each index once, with its dependencies first, so no index runs twice.
        $processes = \Mage::getModel('index/runner')->buildQueue();
        foreach ($processes as $process) {
            $this->reindex($process, $user);
        }

        $result = array_map(
            static fn(\Mage_Index_Model_Process $process): array => IndexProcess::fromModel($process)->toArray(),
            $this->provider->processes(),
        );

        return $this->respondRaw([
            'success' => true,
            'reindexed' => count($processes),
            'processes' => $result,
        ]);
    }
}
