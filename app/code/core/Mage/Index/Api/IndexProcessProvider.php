<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Index
 */

declare(strict_types=1);

namespace Mage\Index\Api;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class IndexProcessProvider extends \Maho\ApiPlatform\Provider
{
    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object
    {
        $this->requireUser();

        if ($operation instanceof CollectionOperationInterface) {
            $items = array_map(IndexProcess::fromModel(...), $this->processes());
            return new TraversablePaginator(new \ArrayIterator($items), 1, max(1, count($items)), count($items));
        }

        return IndexProcess::fromModel($this->loadProcess((int) ($uriVariables['id'] ?? 0)));
    }

    /**
     * The visible processes, as System > Index Management lists them.
     *
     * @return list<\Mage_Index_Model_Process>
     */
    public function processes(): array
    {
        $processes = [];
        /** @var \Mage_Index_Model_Process $process */
        foreach (\Mage::getResourceModel('index/process_collection') as $process) {
            if ($process->getIndexer()->isVisible()) {
                $processes[] = $process;
            }
        }

        return $processes;
    }

    public function loadProcess(int $id): \Mage_Index_Model_Process
    {
        /** @var \Mage_Index_Model_Process $process */
        $process = $this->loadById('index/process', $id);
        if (!$process->getId() || !$process->getIndexer()->isVisible()) {
            throw new NotFoundHttpException("Index process $id not found");
        }

        return $process;
    }
}
