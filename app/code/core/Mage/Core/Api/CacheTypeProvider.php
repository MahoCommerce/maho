<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

namespace Mage\Core\Api;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use Maho\DataObject;

final class CacheTypeProvider extends \Maho\ApiPlatform\Provider
{
    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object
    {
        $this->requireUser();

        if ($operation instanceof CollectionOperationInterface) {
            $items = array_values(array_map($this->toCacheTypeDto(...), self::service()->getTypes()));
            return new TraversablePaginator(new \ArrayIterator($items), 1, max(1, count($items)), count($items));
        }

        return $this->toCacheTypeDto(self::service()->getByCode((string) ($uriVariables['code'] ?? '')));
    }

    public function toCacheTypeDto(DataObject $type): CacheType
    {
        $dto = new CacheType();
        $dto->code = (string) $type->getData('id');
        $dto->label = (string) $type->getData('cache_type');
        $dto->description = (string) $type->getData('description') ?: null;
        $dto->enabled = (bool) $type->getData('status');
        $dto->invalidated = self::service()->isInvalidated($dto->code);
        $dto->tags = (string) $type->getData('tags') ?: null;

        return $dto;
    }

    public static function service(): \Mage_Core_Service_Cache
    {
        return \Mage::getService('core/cache');
    }
}
