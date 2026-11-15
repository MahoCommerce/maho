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

final class IndexProcessProvider extends \Maho\ApiPlatform\Provider
{
    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object
    {
        $this->requireUser();

        if ($operation instanceof CollectionOperationInterface) {
            $items = array_map(IndexProcess::fromModel(...), self::service()->getList());
            return new TraversablePaginator(new \ArrayIterator($items), 1, max(1, count($items)), count($items));
        }

        return IndexProcess::fromModel(self::service()->getById((int) ($uriVariables['id'] ?? 0)));
    }

    public static function service(): \Mage_Index_Service_Process
    {
        return \Mage::getService('index/process');
    }
}
