<?php

/**
 * Resolves the live list of values a property accepts, named in its API property metadata.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

namespace Maho\ApiPlatform\Metadata;

/**
 * Some values live in the store, not in code: page layouts come from the theme, store
 * views and customer groups from the database. A property names its source and the
 * schema shows the values as an enum, so a client, and a model, picks from a closed list:
 *
 *     #[ApiProperty(extraProperties: [EnumSource::KEY => 'page/source_layout'])]
 *
 * The source is one of:
 * - a list of values, for a set that code defines: ['enabled', 'disabled']
 * - a Maho model alias with toOptionArray(), getAllOptions() or getOptions()
 * - a model alias with a method: 'sales/order_config::getStatuses'
 * - a static callable: 'Maho\ApiPlatform\Metadata\ValueLists::websites'
 * A method returns a list of values, option arrays (value and label, groups included) or
 * a value => label map. The list is read when the schema is built, so it never goes stale
 * through the metadata cache. The schema factory casts the values to the property type and
 * puts them on the items of an array property.
 */
final class EnumSource
{
    public const KEY = 'enumSource';

    /**
     * @return list<int|string>
     */
    public static function values(mixed $source): array
    {
        if (is_array($source)) {
            return array_values(array_unique(array_map(static fn(mixed $v): int|string => is_int($v) ? $v : (string) $v, $source), SORT_REGULAR));
        }
        if (!is_string($source) || $source === '') {
            return [];
        }
        try {
            $options = self::options($source);
        } catch (\Throwable $e) {
            \Mage::logException($e);

            return [];
        }

        $values = [];
        self::collect(is_iterable($options) ? $options : [], $values);

        return $values;
    }

    /**
     * @param list<int|string> $values
     */
    private static function collect(iterable $options, array &$values): void
    {
        foreach ($options as $key => $option) {
            if (is_array($option)) {
                $value = $option['value'] ?? null;
                if (is_iterable($value)) {
                    self::collect($value, $values);
                    continue;
                }
            } else {
                $value = is_int($key) ? $option : $key;
            }
            if (!is_scalar($value) || (string) $value === '') {
                continue;
            }
            $value = is_int($value) ? $value : (string) $value;
            if (!in_array($value, $values, true)) {
                $values[] = $value;
            }
        }
    }

    private static function options(string $source): mixed
    {
        if (!str_contains($source, '::')) {
            return self::modelOptions($source);
        }
        [$target, $method] = explode('::', $source, 2);
        if (str_contains($target, '/')) {
            return \Mage::getSingleton($target)->{$method}();
        }

        return \call_user_func($source);
    }

    private static function modelOptions(string $alias): iterable
    {
        $model = \Mage::getSingleton($alias);
        if (!is_object($model)) {
            return [];
        }
        foreach (['toOptionArray', 'getAllOptions', 'getOptions'] as $method) {
            if (method_exists($model, $method)) {
                return $model->{$method}();
            }
        }

        return [];
    }
}
