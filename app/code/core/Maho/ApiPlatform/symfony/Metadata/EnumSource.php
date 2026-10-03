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
 * The source is a Maho model alias with toOptionArray() or getOptions(), a model alias
 * with a method as "core/locale::getOptionWeekdays", or a static callable as
 * "Class::method"; each returns a list of values or option arrays. The list is read when
 * the schema is built, so it never goes stale through the metadata cache.
 */
final class EnumSource
{
    public const KEY = 'enumSource';

    /**
     * @return list<string>
     */
    public static function values(mixed $source): array
    {
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
        foreach (is_iterable($options) ? $options : [] as $key => $option) {
            $value = is_array($option) ? ($option['value'] ?? null) : (is_scalar($option) && !is_int($key) ? $key : $option);
            if (is_array($value)) {
                continue;
            }
            $value = (string) $value;
            if ($value !== '' && !in_array($value, $values, true)) {
                $values[] = $value;
            }
        }

        return $values;
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
        if (method_exists($model, 'toOptionArray')) {
            return $model->toOptionArray();
        }
        if (method_exists($model, 'getOptions')) {
            return $model->getOptions();
        }

        return [];
    }
}
