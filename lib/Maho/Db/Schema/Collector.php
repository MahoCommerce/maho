<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Db\Schema;

use Closure;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\SchemaEditor;
use Doctrine\DBAL\Schema\Table;
use Mage;
use Maho;
use ReflectionFunction;
use ReflectionNamedType;
use RuntimeException;

final class Collector
{
    /**
     * Every active module that ships a sql/schema.php, as module name =>
     * absolute path, in module load order (depends_on honored).
     *
     * @return array<string, string>
     */
    public static function sourceFiles(): array
    {
        $files = [];
        foreach (Mage::getConfig()->getNode('modules')->children() as $modName => $module) {
            if (!$module->is('active')) {
                continue;
            }

            $sqlDir = Mage::getConfig()->getModuleDir('sql', (string) $modName);
            $file = Maho::findFile("$sqlDir/schema.php");
            if ($file === false) {
                continue;
            }

            $files[(string) $modName] = $file;
        }

        return $files;
    }

    /**
     * Walk every active module, load its sql/schema.php closure if present,
     * and let each closure add tables to one shared SchemaEditor. Then apply
     * the configured table_prefix and the default table options Maho's legacy
     * adapter uses (charset/collation).
     *
     * Module load order respects depends_on, so a later module can call
     * $schema->modifyTableByUnquotedName('foo', ...) on a table defined by an
     * earlier one.
     *
     * @return array{0: Schema, 1: list<string>} the final target schema, and the names of modules that contributed
     */
    public static function collect(): array
    {
        $editor = Schema::editor();
        $contributors = [];
        foreach (self::sourceFiles() as $modName => $file) {
            $closure = require $file;
            if (!is_callable($closure)) {
                throw new RuntimeException(
                    "Expected $file to return a callable that adds tables to the SchemaEditor, got " . get_debug_type($closure),
                );
            }

            self::declare($closure, $editor);
            $contributors[] = $modName;
        }

        $prefix = self::tablePrefix();
        $tables = [];
        foreach ($editor->create()->getTables() as $table) {
            $declared = $table->hasOption('engine') ? (string) $table->getOption('engine') : null;
            if ($declared !== null && strcasecmp($declared, 'InnoDB') !== 0) {
                Mage::log(sprintf(
                    'Declarative schema: table "%s" declares storage engine "%s"; forced to InnoDB. '
                    . 'Non-InnoDB engines hold no foreign keys, and writing them inside a transaction '
                    . 'fails under MySQL 8.4+ (enforce_gtid_consistency=ON, SQLSTATE 1785).',
                    $table->getObjectName()->getUnqualifiedName()->getValue(),
                    $declared,
                ), Mage::LOG_NOTICE);
            }

            $tables[] = self::finalizeTable($table, $prefix);
        }
        $schema = Schema::editor()->setTables(...$tables)->create();

        // The implicit single-column indexes DBAL adds on FK local columns
        // (Table::_addForeignKeyConstraint) are kept. DBAL's Index::isFulfilledBy
        // demands exact column count, so even when a multi-col PK starts with
        // the FK column, DBAL adds a dedicated index. That matches Postgres'
        // needs (no auto-indexing on FKs) and is harmless on MySQL (InnoDB
        // already keeps one when nothing covers). Legacy installs lack these
        // on Postgres and let InnoDB silently add them on MySQL; the
        // declarative schema makes the indexes explicit on every engine,
        // which is the more portable shape.

        Renamer::validate($schema);

        return [$schema, $contributors];
    }

    /**
     * Run one schema.php closure. A closure written before DBAL 4.5 takes a
     * Schema and changes it in place, so it gets one built from the editor,
     * and its tables go back into the editor.
     */
    private static function declare(callable $closure, SchemaEditor $editor): void
    {
        $type = (new ReflectionFunction(Closure::fromCallable($closure))->getParameters()[0] ?? null)?->getType();
        if ($type instanceof ReflectionNamedType && $type->getName() === SchemaEditor::class) {
            $closure($editor);
            return;
        }

        $schema = $editor->create();
        $closure($schema);
        $editor->setTables(...$schema->getTables());
    }

    /**
     * Bring a declared table to its final form:
     *
     *  - Name each unnamed foreign key. DBAL keys it under the name that
     *    Table::addForeignKeyConstraint() generates (lowercased), so installs
     *    keep the FK_<hash> names they had before the editor API.
     *  - Apply the table prefix to the table and to each referenced table.
     *    Schema authors declare unprefixed names; the prefix lives in
     *    app/etc/local.xml.
     *  - Apply the charset/collation of Maho's legacy adapter
     *    (Maho\Db\Ddl\Table::$_options defaults to charset=utf8,
     *    collate=utf8_general_ci). Without it, MySQL refuses foreign keys between
     *    a declarative table (database-default charset, often utf8mb4) and a
     *    legacy table (utf8). A table may declare its own charset or collation.
     *  - Force InnoDB, even when the table declares no engine: DBAL then emits
     *    no ENGINE clause and the table inherits @@default_storage_engine. Inert
     *    on PostgreSQL and SQLite.
     */
    private static function finalizeTable(Table $table, string $prefix): Table
    {
        $foreignKeys = [];
        foreach ($table->getForeignKeys() as $key => $foreignKey) {
            $editor = $foreignKey->edit();
            if ($foreignKey->getObjectName() === null) {
                $editor->setUnquotedName(strtoupper((string) $key));
            }
            // getValue() rather than toString(): the latter wraps quoted
            // identifiers in quotes, which would corrupt the concatenation.
            $referenced = $foreignKey->getReferencedTableName()->getUnqualifiedName()->getValue();
            $foreignKeys[] = $editor->setUnquotedReferencedTableName($prefix . $referenced)->create();
        }

        // Table::edit() moves the comment out of the options, so keep it out.
        $options = array_diff_key($table->getOptions(), ['comment' => true]);
        $options += ['charset' => 'utf8', 'collation' => 'utf8_general_ci'];
        $options['engine'] = 'InnoDB';

        return $table->edit()
            ->setUnquotedName($prefix . $table->getObjectName()->getUnqualifiedName()->getValue())
            ->setForeignKeyConstraints(...$foreignKeys)
            ->setOptions(Renamer::applyPrefix($options, $prefix))
            ->create();
    }

    /**
     * The configured table_prefix, or '' when the database is Maho's alone.
     */
    public static function tablePrefix(): string
    {
        return (string) Mage::getConfig()->getTablePrefix();
    }
}
