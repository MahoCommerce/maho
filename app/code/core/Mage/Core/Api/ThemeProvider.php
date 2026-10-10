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
use Maho\ApiPlatform\Provider;

final class ThemeProvider extends Provider
{
    /**
     * @return Theme|TraversablePaginator<Theme>|null
     */
    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): Theme|TraversablePaginator|null
    {
        if ($operation instanceof CollectionOperationInterface) {
            return $this->listThemes($context);
        }

        $id = (string) ($uriVariables['id'] ?? '');
        if (!in_array($id, Theme::installedIds(), true)) {
            return null;
        }

        return $this->toThemeDto($id);
    }

    /**
     * @return TraversablePaginator<Theme>
     */
    private function listThemes(array $context): TraversablePaginator
    {
        $ids = Theme::installedIds();

        ['page' => $page, 'pageSize' => $pageSize] = $this->extractPagination($context, 100, 500);
        $pageIds = array_slice($ids, ($page - 1) * $pageSize, $pageSize);

        $items = array_map($this->toThemeDto(...), $pageIds);

        return new TraversablePaginator(new \ArrayIterator($items), $page, $pageSize, count($ids));
    }

    private function toThemeDto(string $id): Theme
    {
        [$package, $theme] = explode('/', $id, 2);

        $dto = new Theme();
        $dto->id = $id;
        $dto->package = $package;
        $dto->theme = $theme;
        $dto->isDefault = $id === Theme::defaultId();

        \Mage::dispatchEvent('api_theme_dto_build', ['dto' => $dto]);

        return $dto;
    }
}
