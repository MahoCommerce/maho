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
    [
        'engines' => ['mysql'],
        'table' => 'catalog_product_index_website',
        'replace' => [
            '  COLUMN rate float NULL DEFAULT 1' => '  COLUMN rate double NULL DEFAULT 1',
        ],
    ],

    // permission_block / permission_variable: legacy DDL declared is_allowed
    // as TINYINT(1); declarative schema uses SMALLINT for cross-engine parity
    // (no native TINYINT on PgSQL). permission_variable's PK was a redundant
    // composite (variable_id, variable_name) since variable_id is autoincrement;
    // declarative schema uses a single-column PK.
    [
        'engines' => ['mysql'],
        'table' => 'permission_block',
        'replace' => [
            '  COLUMN is_allowed tinyint(1) NOT NULL DEFAULT 0' => '  COLUMN is_allowed smallint NOT NULL DEFAULT 0',
        ],
    ],
    [
        'engines' => ['mysql'],
        'table' => 'permission_variable',
        'replace' => [
            '  COLUMN is_allowed tinyint(1) NOT NULL DEFAULT 0' => '  COLUMN is_allowed smallint NOT NULL DEFAULT 0',
            '  INDEX [PK] (variable_id,variable_name)' => '  INDEX [PK] (variable_id)',
        ],
    ],

    // Legacy DDL declared various datetime columns with DEFAULT '0000-00-00 00:00:00'
    // (MySQL-only sentinel that triggers strict-mode errors). Declarative schema
    // drops the bogus default and stores NULL/expects explicit value at insert.
    [
        'engines' => ['mysql'],
        'table' => 'feedmanager_log',
        'replace' => [
            '  COLUMN started_at datetime NOT NULL DEFAULT 0000-00-00 00:00:00' => '  COLUMN started_at datetime NOT NULL',
        ],
    ],
    [
        'engines' => ['mysql'],
        'table' => 'giftcard',
        'replace' => [
            '  COLUMN created_at datetime NOT NULL DEFAULT 0000-00-00 00:00:00' => '  COLUMN created_at datetime NOT NULL',
            '  COLUMN updated_at datetime NOT NULL DEFAULT 0000-00-00 00:00:00' => '  COLUMN updated_at datetime NOT NULL',
        ],
    ],
    [
        'engines' => ['mysql'],
        'table' => 'giftcard_history',
        'replace' => [
            '  COLUMN created_at datetime NOT NULL DEFAULT 0000-00-00 00:00:00' => '  COLUMN created_at datetime NOT NULL',
        ],
    ],

    // Legacy DDL declared FeedManager varchar(500) columns as TYPE_VARCHAR which
    // legacy Maho converts to TEXT when length > 255; declarative schema uses
    // VARCHAR(500). Same for cases column which legacy declared as TYPE_TEXT
    // 'medium' but the legacy parser fell through to default 1024 → TEXT;
    // declarative correctly produces MEDIUMTEXT.
    [
        'engines' => ['mysql'],
        'table' => 'feedmanager_category_mapping',
        'replace' => [
            '  COLUMN platform_category_path text NOT NULL' => '  COLUMN platform_category_path varchar(500) NOT NULL',
        ],
    ],
    [
        'engines' => ['mysql'],
        'table' => 'feedmanager_feed',
        'replace' => [
            '  COLUMN no_image_url text NULL' => '  COLUMN no_image_url varchar(500) NULL',
        ],
    ],
    [
        'engines' => ['mysql'],
        'table' => 'feedmanager_log',
        'replace' => [
            '  COLUMN upload_message text NULL' => '  COLUMN upload_message varchar(500) NULL',
        ],
    ],
    [
        'engines' => ['mysql'],
        'table' => 'feedmanager_dynamic_rule',
        'replace' => [
            '  COLUMN cases text NULL' => '  COLUMN cases mediumtext NULL',
        ],
    ],

    // Customer vat_id / vat_request_id / vat_request_date: legacy DDL stored as
    // TEXT, declarative uses VARCHAR(255) (sufficient for any VAT identifier).
    [
        'engines' => ['mysql'],
        'table' => 'sales_flat_order_address',
        'replace' => [
            '  COLUMN vat_id text NULL' => '  COLUMN vat_id varchar(255) NULL',
            '  COLUMN vat_request_id text NULL' => '  COLUMN vat_request_id varchar(255) NULL',
            '  COLUMN vat_request_date text NULL' => '  COLUMN vat_request_date varchar(255) NULL',
        ],
    ],
    [
        'engines' => ['mysql'],
        'table' => 'sales_flat_quote_address',
        'replace' => [
            '  COLUMN vat_id text NULL' => '  COLUMN vat_id varchar(255) NULL',
            '  COLUMN vat_request_id text NULL' => '  COLUMN vat_request_id varchar(255) NULL',
            '  COLUMN vat_request_date text NULL' => '  COLUMN vat_request_date varchar(255) NULL',
        ],
    ],

    // PayPal trial_billing_amount: legacy DDL stored as TEXT, declarative uses
    // VARCHAR(255). It's a money string ("X.YY"), no need for TEXT.
    [
        'engines' => ['mysql'],
        'table' => 'sales_recurring_profile',
        'replace' => [
            '  COLUMN trial_billing_amount text NULL' => '  COLUMN trial_billing_amount varchar(255) NULL',
        ],
    ],

    // Sales order aggregated tables: declarative explicitly sets default '' on
    // order_status; legacy DDL has it nullable / no default. Functionally
    // equivalent for these aggregation tables.
    [
        'engines' => ['mysql'],
        'table' => 'sales_order_aggregated_created',
        'replace' => [
            '  COLUMN order_status varchar(50) NULL' => '  COLUMN order_status varchar(50) NOT NULL DEFAULT ',
        ],
    ],
    [
        'engines' => ['mysql'],
        'table' => 'sales_order_aggregated_updated',
        'replace' => [
            '  COLUMN order_status varchar(50) NOT NULL' => '  COLUMN order_status varchar(50) NOT NULL DEFAULT ',
        ],
    ],

    // Newsletter subscriber_confirm_code: legacy DDL emits explicit DEFAULT NULL,
    // declarative omits redundant default (nullable column already defaults to NULL).
    [
        'engines' => ['mysql'],
        'table' => 'newsletter_subscriber',
        'replace' => [
            '  COLUMN subscriber_confirm_code varchar(32) NULL DEFAULT NULL' => '  COLUMN subscriber_confirm_code varchar(32) NULL',
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
    [
        'table' => 'weee_discount',
        'remove' => [
            '  FK customer_group_id -> customer_group(customer_group_id) ON DELETE CASCADE ON UPDATE CASCADE',
            '  FK entity_id -> catalog_product_entity(entity_id) ON DELETE CASCADE ON UPDATE CASCADE',
        ],
    ],
    [
        'table' => 'customer_segment_customer',
        'remove' => [
            '  FK customer_id -> customer_entity(entity_id) ON DELETE CASCADE ON UPDATE NO ACTION',
        ],
    ],
    [
        'table' => 'customer_segment_email_sequence',
        'remove' => [
            '  INDEX [IDX] (coupon_sales_rule_id)',
            '  FK coupon_sales_rule_id -> salesrule(rule_id) ON DELETE SET NULL ON UPDATE NO ACTION',
        ],
    ],
    [
        'table' => 'customer_segment_sequence_progress',
        'remove' => [
            '  FK customer_id -> customer_entity(entity_id) ON DELETE CASCADE ON UPDATE NO ACTION',
        ],
    ],

    // Sales bestsellers FKs to catalog_product_entity dropped because the
    // declarative schema is applied before catalog_product_entity (still legacy).
    [
        'table' => 'sales_bestsellers_aggregated_daily',
        'remove' => [
            '  FK product_id -> catalog_product_entity(entity_id) ON DELETE CASCADE ON UPDATE CASCADE',
        ],
    ],
    [
        'table' => 'sales_bestsellers_aggregated_monthly',
        'remove' => [
            '  FK product_id -> catalog_product_entity(entity_id) ON DELETE CASCADE ON UPDATE CASCADE',
        ],
    ],
    [
        'table' => 'sales_bestsellers_aggregated_yearly',
        'remove' => [
            '  FK product_id -> catalog_product_entity(entity_id) ON DELETE CASCADE ON UPDATE CASCADE',
        ],
    ],
    // sales_flat_quote_item FK to catalog_product_entity: ordering reason.
    [
        'table' => 'sales_flat_quote_item',
        'remove' => [
            '  FK product_id -> catalog_product_entity(entity_id) ON DELETE CASCADE ON UPDATE CASCADE',
        ],
    ],
    // FeedManager category mapping FK to catalog_category_entity: ordering reason.
    [
        'table' => 'feedmanager_category_mapping',
        'remove' => [
            '  INDEX [IDX] (category_id)',
            '  FK category_id -> catalog_category_entity(entity_id) ON DELETE CASCADE ON UPDATE NO ACTION',
        ],
    ],
    // customer_entity composite (email,website_id) index dropped because it
    // duplicates the unique index already present on (email,website_id).
    [
        'table' => 'customer_entity',
        'remove' => [
            '  INDEX [IDX] (email,website_id)',
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
