<?php

/**
 * The PHP type of each data key of a DataObject class. setData() casts a value with it.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Data;

use Mage;
use Maho\DataObject;

/**
 * A class takes the parameter types from its typed setters. The map is cached under the
 * config tag. A request reads the cache once. A new class is reflected once and added.
 */
final class TypeMap
{
    public const CACHE_ID = 'data_type_map';

    public const TYPE_INT = 'int';
    public const TYPE_FLOAT = 'float';
    public const TYPE_STRING = 'string';
    public const TYPE_BOOL = 'bool';

    /** @var array<string, array<string, string>>|null class => key => type. Null until loaded. */
    private static ?array $map = null;

    private static bool $loadFailed = false;

    /**
     * Get the data types of a class from its setters that take one scalar parameter.
     *
     * @param class-string $class
     * @return array<string, string> data key => PHP type
     */
    public static function forClass(string $class): array
    {
        self::load();
        if (isset(self::$map[$class])) {
            return self::$map[$class];
        }

        $types = self::reflectSetters($class);
        if (self::$map !== null && !self::$loadFailed) {
            self::$map[$class] = $types;
            self::save();
        }
        return $types;
    }

    private static function load(): void
    {
        // Before the application boots there is no cache. Cast nothing.
        if (self::$map !== null || Mage::getConfig() === null) {
            return;
        }

        try {
            $cached = Mage::app()->loadCache(self::CACHE_ID);
            $map = is_string($cached) && $cached !== '' ? unserialize($cached, ['allowed_classes' => false]) : [];
            self::$map = is_array($map) ? $map : [];
        } catch (\Throwable $e) {
            Mage::logException($e);
            // Stop for this process. A retry on each setData() call repeats the failure.
            self::$loadFailed = true;
            self::$map = [];
        }
    }

    private static function save(): void
    {
        try {
            Mage::app()->saveCache(serialize(self::$map), self::CACHE_ID, [\Mage_Core_Model_Config::CACHE_TAG]);
        } catch (\Throwable $e) {
            Mage::logException($e);
        }
    }

    /**
     * @param class-string $class
     * @return array<string, string>
     */
    private static function reflectSetters(string $class): array
    {
        $types = [];
        foreach (get_class_methods($class) as $method) {
            if (!str_starts_with($method, 'set') || strlen($method) < 4 || $method === 'setData') {
                continue;
            }
            $reflection = new \ReflectionMethod($class, $method);
            if ($reflection->isStatic() || $reflection->getNumberOfParameters() !== 1) {
                continue;
            }
            $type = $reflection->getParameters()[0]->getType();
            if (!$type instanceof \ReflectionNamedType || !$type->isBuiltin()) {
                continue;
            }
            $name = $type->getName();
            if (in_array($name, [self::TYPE_INT, self::TYPE_FLOAT, self::TYPE_STRING, self::TYPE_BOOL], true)) {
                $types[DataObject::underscore(substr($method, 3))] = $name;
            }
        }
        return $types;
    }
}
