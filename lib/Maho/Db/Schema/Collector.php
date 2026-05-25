<?php

/**
 * Maho
 *
 * @package    Maho_Db
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace Maho\Db\Schema;

use Doctrine\DBAL\Schema\Schema;
use Mage;
use Maho;
use RuntimeException;

final class Collector
{
    /**
     * Walk every active module, load its etc/db_schema.php closure if present,
     * and let each closure contribute tables to a shared Schema instance.
     *
     * Module load order respects depends_on, so a later module can
     * $schema->getTable('foo') on a table defined by an earlier one.
     *
     * @return list<string> names of modules that contributed a schema
     */
    public static function collect(Schema $schema): array
    {
        $contributors = [];
        $modules = Mage::getConfig()->getNode('modules')->children();
        foreach ($modules as $modName => $module) {
            if (!$module->is('active')) {
                continue;
            }

            $etcDir = Mage::getConfig()->getModuleDir('etc', (string) $modName);
            $file = Maho::findFile("$etcDir/db_schema.php");
            if ($file === false) {
                continue;
            }

            $closure = require $file;
            if (!is_callable($closure)) {
                throw new RuntimeException(
                    "Expected $file to return a callable that mutates the Schema, got " . get_debug_type($closure),
                );
            }

            $closure($schema);
            $contributors[] = (string) $modName;
        }

        return $contributors;
    }

    /**
     * Convenience: build a target Schema from all module contributions.
     */
    public static function buildTargetSchema(): Schema
    {
        $schema = new Schema();
        self::collect($schema);
        return $schema;
    }
}
