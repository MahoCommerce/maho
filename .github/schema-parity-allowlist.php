<?php

/**
 * Maho
 *
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Schema-parity allowlist.
 *
 * The schema-parity CI workflow dumps the live DB schema on the main branch
 * and on the PR branch, then diffs the two text dumps. Any difference fails
 * the build. This script post-processes the main dump so that *intentional*
 * differences (improvements baked into the declarative schema, or temporary
 * regressions for cross-module ordering) don't show up in the diff.
 *
 * Usage:
 *     php .github/schema-parity-allowlist.php <engine> < main.txt > main.normalized.txt
 *
 * Where <engine> is one of: mysql, pgsql, sqlite.
 *
 * Each entry below is scoped to a table and applies either:
 *   - 'replace': swap a literal line for a different literal line
 *   - 'remove':  drop a literal line entirely
 *
 * The 'engines' key restricts an entry to specific engines (omit = all).
 *
 * IMPORTANT: only list drift that is *intentional* and *documented*. Bugs
 * in the declarative schema should be fixed in the schema.php files, not
 * hidden here.
 */

declare(strict_types=1);

$entries = [
    // ----- Intentional precision upgrade -----
    //
    // Doctrine DBAL maps Types::FLOAT to DOUBLE on MySQL (and DOUBLE PRECISION
    // on Postgres) by design: most RDBMS default to 8-byte floats, MySQL is
    // the odd one out. The declarative schema therefore emits 'double' where
    // the legacy DDL emitted 'float'. Double is also a better engineering
    // choice for these aggregation columns, where sums of many small tax
    // amounts can accumulate enough rounding error to chew through float's
    // ~7 significant digits.
    [
        'engines' => ['mysql'],
        'table' => 'sales_order_tax_aggregated_created',
        'replace' => [
            '  COLUMN percent float NULL' => '  COLUMN percent double NULL',
            '  COLUMN tax_base_amount_sum float NULL' => '  COLUMN tax_base_amount_sum double NULL',
        ],
    ],
    [
        'engines' => ['mysql'],
        'table' => 'sales_order_tax_aggregated_updated',
        'replace' => [
            '  COLUMN percent float NULL' => '  COLUMN percent double NULL',
            '  COLUMN tax_base_amount_sum float NULL' => '  COLUMN tax_base_amount_sum double NULL',
        ],
    ],

    // ----- Intentional signedness fix -----
    //
    // Auto-increment PK should be unsigned (doubles the available ID range).
    // Legacy DDL left it signed by oversight; the declarative schema fixes it.
    [
        'engines' => ['mysql'],
        'table' => 'weee_tax',
        'replace' => [
            '  COLUMN value_id int NOT NULL [auto_increment]' => '  COLUMN value_id int unsigned NOT NULL [auto_increment]',
        ],
    ],

    // ----- Temporary FK drops while cross-module schemas remain legacy -----
    //
    // The schema applier creates all declarative tables up front; legacy
    // install scripts run separately. FKs from a declarative table to a
    // still-legacy table cannot be created at apply time (referenced table
    // doesn't exist yet). These FKs are reinstated when the referenced
    // module is converted to declarative schema.
    [
        'table' => 'sales_order_tax_item',
        'remove' => [
            '  FK item_id -> sales_flat_order_item(item_id) ON DELETE CASCADE ON UPDATE CASCADE',
            '  FK tax_id -> sales_order_tax(tax_id) ON DELETE CASCADE ON UPDATE CASCADE',
        ],
    ],
    [
        'table' => 'weee_tax',
        'remove' => [
            '  FK attribute_id -> eav_attribute(attribute_id) ON DELETE CASCADE ON UPDATE CASCADE',
            '  FK entity_id -> catalog_product_entity(entity_id) ON DELETE CASCADE ON UPDATE CASCADE',
        ],
    ],
];

$engine = $argv[1] ?? '';
if (!in_array($engine, ['mysql', 'pgsql', 'sqlite'], true)) {
    fwrite(STDERR, "Usage: php schema-parity-allowlist.php <mysql|pgsql|sqlite> < main.txt > main.normalized.txt\n");
    exit(1);
}

$ops = [];
foreach ($entries as $entry) {
    if (isset($entry['engines']) && !in_array($engine, $entry['engines'], true)) {
        continue;
    }
    $table = $entry['table'];
    $ops[$table] ??= ['replace' => [], 'remove' => []];
    $ops[$table]['replace'] = array_merge($ops[$table]['replace'], $entry['replace'] ?? []);
    $ops[$table]['remove']  = array_merge($ops[$table]['remove'], $entry['remove']  ?? []);
}

$input = stream_get_contents(STDIN);
if ($input === false) {
    fwrite(STDERR, "Failed to read stdin\n");
    exit(1);
}

$lines        = explode("\n", $input);
$out          = [];
$currentTable = null;

foreach ($lines as $line) {
    if (preg_match('/^TABLE (.+)$/', $line, $m)) {
        $currentTable = $m[1];
        $out[]        = $line;
        continue;
    }
    if ($currentTable !== null && isset($ops[$currentTable])) {
        if (in_array($line, $ops[$currentTable]['remove'], true)) {
            continue;
        }
        if (isset($ops[$currentTable]['replace'][$line])) {
            $out[] = $ops[$currentTable]['replace'][$line];
            continue;
        }
    }
    $out[] = $line;
}

echo implode("\n", $out);
