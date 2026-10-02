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
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class CacheTypeProvider extends \Maho\ApiPlatform\Provider
{
    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object
    {
        $this->requireUser();

        if ($operation instanceof CollectionOperationInterface) {
            $items = array_values(array_map($this->toCacheTypeDto(...), $this->types()));
            return new TraversablePaginator(new \ArrayIterator($items), 1, max(1, count($items)), count($items));
        }

        return $this->cacheType((string) ($uriVariables['code'] ?? ''));
    }

    public function cacheType(string $code): CacheType
    {
        $type = $this->types()[$code] ?? null;
        if ($type === null) {
            throw new NotFoundHttpException("Cache type '$code' not found");
        }

        return $this->toCacheTypeDto($type);
    }

    /**
     * @param array<string, bool>|null $enabledOverride Cache options just saved, which the cache model has not loaded again yet
     */
    public function toCacheTypeDto(DataObject $type, ?array $enabledOverride = null): CacheType
    {
        $code = (string) $type->getData('id');
        $invalidated = \Mage::app()->getCache()->getInvalidatedTypes();

        $dto = new CacheType();
        $dto->code = $code;
        $dto->label = (string) $type->getData('cache_type');
        $dto->description = (string) $type->getData('description') ?: null;
        $dto->enabled = $enabledOverride === null ? (bool) $type->getData('status') : ($enabledOverride[$code] ?? false);
        $dto->invalidated = isset($invalidated[$code]);
        $dto->tags = (string) $type->getData('tags') ?: null;

        return $dto;
    }

    /**
     * @return array<string, DataObject>
     */
    public function types(): array
    {
        return \Mage::app()->getCache()->getTypes();
    }
}
