<?php

/**
 * PHP types for the data keys of a DataObject class, so that setData() casts a value where it enters.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Data;

use Doctrine\DBAL\Types\Type;
use Mage;
use Maho\DataObject;
use Maho\Db\Schema\Collector;

/**
 * Two sources feed the map. A model with a database table takes the column types of the
 * declarative schema. Any class takes the parameter types of its typed setters. The map is
 * cached under the config tag: a request pays one cache read, and a class that is seen for
 * the first time is reflected once and added.
 */
final class TypeMap
{
    public const CACHE_ID = 'data_type_map';

    public const TYPE_INT = 'int';
    public const TYPE_FLOAT = 'float';
    public const TYPE_STRING = 'string';
    public const TYPE_BOOL = 'bool';

    private const COLUMN_TYPES = [
        'smallint' => self::TYPE_INT,
        'integer' => self::TYPE_INT,
        'bigint' => self::TYPE_INT,
        'decimal' => self::TYPE_FLOAT,
        'float' => self::TYPE_FLOAT,
        'smallfloat' => self::TYPE_FLOAT,
        'boolean' => self::TYPE_BOOL,
        'string' => self::TYPE_STRING,
        'ascii_string' => self::TYPE_STRING,
        'text' => self::TYPE_STRING,
    ];

    /** @var array<string, array<string, string>>|null table => column => type, null until loaded */
    private static ?array $columns = null;

    /** @var array<string, array<string, string>> class => key => type */
    private static array $setters = [];

    private static bool $loadFailed = false;

    /**
     * @return array<string, string> column name => PHP type, for a table the declarative schema declares
     */
    public static function forTable(string $table): array
    {
        self::load();
        return self::$columns[$table] ?? [];
    }

    /**
     * @param class-string $class
     * @return array<string, string> data key => PHP type, one per setter with a scalar parameter type
     */
    public static function forSetters(string $class): array
    {
        self::load();
        if (isset(self::$setters[$class])) {
            return self::$setters[$class];
        }

        $setters = self::reflectSetters($class);
        if (self::$columns !== null && !self::$loadFailed) {
            self::$setters[$class] = $setters;
            self::save();
        }
        return $setters;
    }

    public static function reset(): void
    {
        self::$columns = null;
        self::$setters = [];
        self::$loadFailed = false;
    }

    private static function load(): void
    {
        // Before the application is booted there is no cache and no schema: cast nothing, remember nothing.
        if (self::$columns !== null || Mage::getConfig() === null) {
            return;
        }

        try {
            $cached = Mage::app()->loadCache(self::CACHE_ID);
            if (is_string($cached) && $cached !== '') {
                $map = unserialize($cached, ['allowed_classes' => false]);
                if (is_array($map) && isset($map['columns'], $map['setters'])) {
                    self::$columns = $map['columns'];
                    self::$setters = $map['setters'];
                    return;
                }
            }
            self::$columns = self::collectColumns();
            self::$setters = [];
            self::save();
        } catch (\Throwable $e) {
            Mage::logException($e);
            // Give up for this process: a retry on every setData() call would repeat the failure.
            self::$loadFailed = true;
            self::$columns = [];
            self::$setters = [];
        }
    }

    private static function save(): void
    {
        try {
            Mage::app()->saveCache(
                serialize(['columns' => self::$columns, 'setters' => self::$setters]),
                self::CACHE_ID,
                [\Mage_Core_Model_Config::CACHE_TAG],
            );
        } catch (\Throwable $e) {
            Mage::logException($e);
        }
    }

    /**
     * @return array<string, array<string, string>>
     */
    private static function collectColumns(): array
    {
        [$schema] = Collector::collect();
        $columns = [];
        foreach ($schema->getTables() as $table) {
            foreach ($table->getColumns() as $column) {
                $type = self::COLUMN_TYPES[Type::lookupName($column->getType())] ?? null;
                if ($type !== null) {
                    $columns[$table->getObjectName()->getUnqualifiedName()->getValue()][$column->getObjectName()->getIdentifier()->getValue()] = $type;
                }
            }
        }
        return $columns;
    }

    /**
     * @param class-string $class
     * @return array<string, string>
     */
    private static function reflectSetters(string $class): array
    {
        $setters = [];
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
                $setters[DataObject::underscore(substr($method, 3))] = $name;
            }
        }
        return $setters;
    }
}
