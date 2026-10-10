<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Widget
 */

declare(strict_types=1);

namespace Mage\Widget\Api;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use Maho\ApiPlatform\Provider;

final class WidgetTypeProvider extends Provider
{
    /**
     * @return WidgetType|TraversablePaginator<WidgetType>|null
     */
    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): WidgetType|TraversablePaginator|null
    {
        $widgets = $this->widgets();
        if ($operation instanceof CollectionOperationInterface) {
            ['page' => $page, 'pageSize' => $pageSize] = $this->extractPagination($context, 100, 500);
            // The list stays short enough for a model to read whole: parameters come with the item.
            $items = array_map(fn(array $widget): WidgetType => $this->toWidgetDto($widget, false), array_slice($widgets, ($page - 1) * $pageSize, $pageSize));

            return new TraversablePaginator(new \ArrayIterator($items), $page, $pageSize, count($widgets));
        }

        $id = (string) ($uriVariables['id'] ?? '');
        foreach ($widgets as $widget) {
            if ($widget['code'] === $id) {
                return $this->toWidgetDto($widget);
            }
        }

        return null;
    }

    /**
     * @return list<array{name: string, code: string, type: string, description: string}>
     */
    private function widgets(): array
    {
        $widgets = \Mage::getSingleton('widget/widget')->getWidgetsArray();
        usort($widgets, static fn(array $a, array $b): int => strcmp($a['code'], $b['code']));

        return $widgets;
    }

    /**
     * @param array{name: string, code: string, type: string, description: string} $widget
     */
    private function toWidgetDto(array $widget, bool $withParameters = true): WidgetType
    {
        $model = \Mage::getSingleton('widget/widget');
        $config = $model->getConfigAsObject($widget['type']);

        $dto = new WidgetType();
        $dto->id = $widget['code'];
        $dto->type = $widget['type'];
        $dto->name = $widget['name'];
        $dto->description = $widget['description'];
        $dto->isEmailCompatible = (bool) $config->getData('is_email_compatible');

        $defaults = [];
        foreach ((array) $config->getData('parameters') as $parameter) {
            if (!$parameter instanceof \Maho\DataObject || (string) $parameter->getData('visible') === '0') {
                continue;
            }
            $options = [];
            foreach ((array) $parameter->getData('values') as $option) {
                if (is_array($option)) {
                    $options[] = ['value' => (string) $option['value'], 'label' => (string) $option['label']];
                }
            }
            $default = $parameter->getData('value');
            $default = is_scalar($default) ? (string) $default : null;
            if ($default !== null && $default !== '') {
                $defaults[(string) $parameter->getData('key')] = $default;
            }
            if (!$withParameters) {
                continue;
            }
            $dto->parameters[] = [
                'name' => (string) $parameter->getData('key'),
                'label' => (string) $parameter->getData('label'),
                'type' => (string) ($parameter->getData('type') ?: 'text'),
                'required' => (string) $parameter->getData('required') === '1',
                'default' => $default,
                'options' => $options,
                'description' => (string) $parameter->getData('description'),
            ];
        }
        $dto->directive = $model->getWidgetDeclaration($widget['type'], $defaults);

        return $dto;
    }
}
