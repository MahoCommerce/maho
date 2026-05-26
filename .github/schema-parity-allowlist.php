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
        'table' => 'tax_order_aggregated_created',
        'replace' => [
            '  COLUMN percent float NULL' => '  COLUMN percent double NULL',
            '  COLUMN tax_base_amount_sum float NULL' => '  COLUMN tax_base_amount_sum double NULL',
        ],
    ],
    [
        'engines' => ['mysql'],
        'table' => 'tax_order_aggregated_updated',
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

    // admin_user.rp_token: legacy DDL declared this TYPE_TEXT(256). The Maho
    // legacy MySQL adapter converts (length > 255) → text; Postgres keeps it
    // as varchar(256). Declarative schema unifies on varchar(256) since the
    // column never stores more than a 256-char token.
    [
        'engines' => ['mysql'],
        'table' => 'admin_user',
        'replace' => [
            '  COLUMN rp_token text NULL' => '  COLUMN rp_token varchar(256) NULL',
        ],
    ],

    // *_updated aggregation tables: legacy install clones structure via
    // createTableByDdl() which on Postgres drops the SERIAL/IDENTITY property
    // and leaves `id` as plain INTEGER. Declarative schema correctly emits
    // IDENTITY, restoring auto-increment behavior.
    [
        'engines' => ['pgsql'],
        'table' => 'coupon_aggregated_updated',
        'replace' => [
            '  COLUMN id integer NOT NULL' => '  COLUMN id integer NOT NULL [IDENTITY]',
        ],
    ],
    [
        'engines' => ['pgsql'],
        'table' => 'sales_order_aggregated_updated',
        'replace' => [
            '  COLUMN id integer NOT NULL' => '  COLUMN id integer NOT NULL [IDENTITY]',
        ],
    ],
    [
        'engines' => ['pgsql'],
        'table' => 'tax_order_aggregated_updated',
        'replace' => [
            '  COLUMN id integer NOT NULL' => '  COLUMN id integer NOT NULL [IDENTITY]',
        ],
    ],

    // Postgres pgsql legacy adapter doesn't widen rating IP columns the way
    // the MySQL adapter does. The relevant upgrade-1.6.0.0-1.6.0.1.php is wrapped
    // in `if instanceof Mysql`, so Postgres keeps BIGINT for *_ip_long columns
    // and VARCHAR(16) for remote_ip. Declarative schema unifies on
    // VARBINARY/VARCHAR(50) across engines.
    [
        'engines' => ['pgsql'],
        'table' => 'rating_option_vote',
        'replace' => [
            '  COLUMN remote_ip character varying(16) NOT NULL' => '  COLUMN remote_ip character varying(50) NULL',
            '  COLUMN remote_ip_long bigint NOT NULL DEFAULT 0' => '  COLUMN remote_ip_long bytea NULL',
        ],
    ],
    [
        'engines' => ['pgsql'],
        'table' => 'log_visitor_info',
        'replace' => [
            '  COLUMN remote_addr bigint NULL' => '  COLUMN remote_addr bytea NULL',
            '  COLUMN server_addr bigint NULL' => '  COLUMN server_addr bytea NULL',
        ],
    ],
    [
        'engines' => ['pgsql'],
        'table' => 'log_visitor_online',
        'replace' => [
            '  COLUMN remote_addr bigint NOT NULL' => '  COLUMN remote_addr bytea NULL',
        ],
    ],

    // catalog_product_index_website.rate: legacy Postgres adapter emits "real"
    // for TYPE_FLOAT; Doctrine emits "double precision" for Types::FLOAT on
    // Postgres (matching the engine default 8-byte precision). Same justification
    // as the MySQL float→double upgrade.
    [
        'engines' => ['pgsql'],
        'table' => 'catalog_product_index_website',
        'replace' => [
            '  COLUMN rate real NULL DEFAULT 1' => '  COLUMN rate double precision NULL DEFAULT 1',
        ],
    ],

    // tax_order_aggregated_*.percent / tax_base_amount_sum: same float→double
    // intentional precision upgrade as MySQL, but pgsql renders it differently.
    [
        'engines' => ['pgsql'],
        'table' => 'tax_order_aggregated_created',
        'replace' => [
            '  COLUMN percent real NULL' => '  COLUMN percent double precision NULL',
            '  COLUMN tax_base_amount_sum real NULL' => '  COLUMN tax_base_amount_sum double precision NULL',
        ],
    ],
    [
        'engines' => ['pgsql'],
        'table' => 'tax_order_aggregated_updated',
        'replace' => [
            '  COLUMN percent real NULL' => '  COLUMN percent double precision NULL',
            '  COLUMN tax_base_amount_sum real NULL' => '  COLUMN tax_base_amount_sum double precision NULL',
        ],
    ],

    // permission_block / permission_variable on Postgres: legacy DDL emits
    // boolean, declarative emits smallint (no cross-engine TINYINT exists,
    // and smallint is the common ground). Same fix as the MySQL allowlist
    // for tinyint(1) → smallint.
    [
        'engines' => ['pgsql'],
        'table' => 'permission_block',
        'replace' => [
            '  COLUMN is_allowed boolean NOT NULL DEFAULT false' => '  COLUMN is_allowed smallint NOT NULL DEFAULT 0',
        ],
    ],
    [
        'engines' => ['pgsql'],
        'table' => 'permission_variable',
        'replace' => [
            '  COLUMN is_allowed boolean NOT NULL DEFAULT false' => '  COLUMN is_allowed smallint NOT NULL DEFAULT 0',
            '  INDEX [PK] (variable_id,variable_name)' => '  INDEX [PK] (variable_id)',
        ],
    ],

    // Postgres legacy adapter writes literal string 'NULL' as the default for
    // some nullable VARCHAR columns; declarative schema omits the redundant
    // default (nullable column already defaults to actual NULL).
    [
        'engines' => ['pgsql'],
        'table' => 'newsletter_subscriber',
        'replace' => [
            "  COLUMN subscriber_confirm_code character varying(32) NULL DEFAULT 'NULL'" => '  COLUMN subscriber_confirm_code character varying(32) NULL',
        ],
    ],

    // Postgres legacy adapter doesn't accept '0000-00-00 00:00:00' as a
    // datetime sentinel; the SampleData install in the legacy world uses it
    // anyway in VARCHAR-typed "date" columns. Declarative schema uses Postgres's
    // valid epoch '1970-01-01 00:00:00' instead.
    [
        'engines' => ['pgsql'],
        'table' => 'customer_flowpassword',
        'replace' => [
            "  COLUMN requested_date character varying(255) NOT NULL DEFAULT '0000-00-00 00:00:00'" => "  COLUMN requested_date character varying(255) NOT NULL DEFAULT '1970-01-01 00:00:00'",
        ],
    ],

    // Customer vat_id / vat_request_id / vat_request_date on Postgres: legacy
    // emits text, declarative emits varchar(255). Same justification as MySQL.
    [
        'engines' => ['pgsql'],
        'table' => 'sales_flat_order_address',
        'replace' => [
            '  COLUMN vat_id text NULL' => '  COLUMN vat_id character varying(255) NULL',
            '  COLUMN vat_request_id text NULL' => '  COLUMN vat_request_id character varying(255) NULL',
            '  COLUMN vat_request_date text NULL' => '  COLUMN vat_request_date character varying(255) NULL',
        ],
    ],
    [
        'engines' => ['pgsql'],
        'table' => 'sales_flat_quote_address',
        'replace' => [
            '  COLUMN vat_id text NULL' => '  COLUMN vat_id character varying(255) NULL',
            '  COLUMN vat_request_id text NULL' => '  COLUMN vat_request_id character varying(255) NULL',
            '  COLUMN vat_request_date text NULL' => '  COLUMN vat_request_date character varying(255) NULL',
        ],
    ],

    // sales_flat_shipment.packages: legacy install passes length=20000 to
    // TYPE_TEXT, Postgres legacy adapter outputs varchar(20000). Declarative
    // schema uses Types::TEXT length 65535 → text. Both fit the data.
    [
        'engines' => ['pgsql'],
        'table' => 'sales_flat_shipment',
        'replace' => [
            '  COLUMN packages character varying(20000) NULL' => '  COLUMN packages text NULL',
        ],
    ],

    // Tables where declarative schema chose nullable timestamps (event-time
    // columns that aren't always known at insert time). The global regex
    // above promotes bare "timestamp NOT NULL" on main to "...DEFAULT
    // CURRENT_TIMESTAMP" since that's the right default for the bulk of
    // legacy schemas, but for these specific columns the declarative is
    // deliberately nullable, so we revert the regex's transformation here.
    [
        'engines' => ['pgsql'],
        'table' => 'api_session',
        'replace' => [
            '  COLUMN logdate timestamp without time zone NOT NULL DEFAULT CURRENT_TIMESTAMP' => '  COLUMN logdate timestamp without time zone NULL',
        ],
    ],
    [
        'engines' => ['pgsql'],
        'table' => 'log_customer',
        'replace' => [
            '  COLUMN login_at timestamp without time zone NOT NULL DEFAULT CURRENT_TIMESTAMP' => '  COLUMN login_at timestamp without time zone NULL',
        ],
    ],
    [
        'engines' => ['pgsql'],
        'table' => 'log_summary',
        'replace' => [
            '  COLUMN add_date timestamp without time zone NOT NULL DEFAULT CURRENT_TIMESTAMP' => '  COLUMN add_date timestamp without time zone NULL',
            // legacy declares smallint, declarative integer (matches MySQL int).
            '  COLUMN lognum smallint NOT NULL DEFAULT 0' => '  COLUMN lognum integer NOT NULL DEFAULT 0',
        ],
    ],
    [
        'engines' => ['pgsql'],
        'table' => 'log_url',
        'replace' => [
            '  COLUMN visit_time timestamp without time zone NOT NULL DEFAULT CURRENT_TIMESTAMP' => '  COLUMN visit_time timestamp without time zone NULL',
        ],
    ],
    [
        'engines' => ['pgsql'],
        'table' => 'log_visitor',
        'replace' => [
            '  COLUMN last_visit_at timestamp without time zone NOT NULL DEFAULT CURRENT_TIMESTAMP' => '  COLUMN last_visit_at timestamp without time zone NULL',
        ],
    ],
    [
        'engines' => ['pgsql'],
        'table' => 'product_alert_price',
        'replace' => [
            '  COLUMN add_date timestamp without time zone NOT NULL DEFAULT CURRENT_TIMESTAMP' => '  COLUMN add_date timestamp without time zone NULL',
        ],
    ],
    [
        'engines' => ['pgsql'],
        'table' => 'product_alert_stock',
        'replace' => [
            '  COLUMN add_date timestamp without time zone NOT NULL DEFAULT CURRENT_TIMESTAMP' => '  COLUMN add_date timestamp without time zone NULL',
        ],
    ],
    [
        'engines' => ['pgsql'],
        'table' => 'report_event',
        'replace' => [
            '  COLUMN logged_at timestamp without time zone NOT NULL DEFAULT CURRENT_TIMESTAMP' => '  COLUMN logged_at timestamp without time zone NULL',
        ],
    ],
    // feedmanager_log.started_at: declarative is NOT NULL (no default); legacy
    // is also NOT NULL but the global regex added CURRENT_TIMESTAMP. Strip it.
    [
        'engines' => ['pgsql'],
        'table' => 'feedmanager_log',
        'replace' => [
            '  COLUMN started_at timestamp without time zone NOT NULL DEFAULT CURRENT_TIMESTAMP' => '  COLUMN started_at timestamp without time zone NOT NULL',
        ],
    ],

    // sales_flat_order.created_at / updated_at: declarative is NOT NULL no
    // default; legacy too, but the global regex added CURRENT_TIMESTAMP.
    [
        'engines' => ['pgsql'],
        'table' => 'sales_flat_order',
        'replace' => [
            '  COLUMN created_at timestamp without time zone NOT NULL DEFAULT CURRENT_TIMESTAMP' => '  COLUMN created_at timestamp without time zone NOT NULL',
            '  COLUMN updated_at timestamp without time zone NOT NULL DEFAULT CURRENT_TIMESTAMP' => '  COLUMN updated_at timestamp without time zone NOT NULL',
        ],
    ],

    // core_translate on Postgres: legacy creates a UNIQUE index that
    // declarative makes the PK directly, so the redundant UNIQ is gone.
    [
        'engines' => ['pgsql'],
        'table' => 'core_translate',
        'remove' => [
            '  INDEX [UNIQ] (store_id,locale,string)',
        ],
    ],

    // checkout_agreement_store on Postgres: declarative explicitly adds an
    // index on store_id (needed for FK lookups since the column is the
    // non-prefix half of the composite PK). The legacy pgsql install doesn't
    // emit it, so add it to main to match.
    [
        'engines' => ['pgsql'],
        'table' => 'checkout_agreement_store',
        'add' => [
            '  INDEX [IDX] (store_id)',
        ],
    ],

    // PayPal trial_billing_amount: legacy text, declarative varchar(255).
    [
        'engines' => ['pgsql'],
        'table' => 'sales_recurring_profile',
        'replace' => [
            '  COLUMN trial_billing_amount text NULL' => '  COLUMN trial_billing_amount character varying(255) NULL',
        ],
    ],

    // numeric default formatting: declarative emits 0.0000, legacy emits 0.
    [
        'engines' => ['pgsql'],
        'table' => 'catalogrule',
        'replace' => [
            '  COLUMN discount_amount numeric(12,4) NOT NULL DEFAULT 0' => '  COLUMN discount_amount numeric(12,4) NOT NULL DEFAULT 0.0000',
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
        'table' => 'sales_invoiced_aggregated',
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
    [
        'engines' => ['pgsql'],
        'table' => 'sales_invoiced_aggregated',
        'replace' => [
            '  COLUMN order_status character varying(50) NULL' => "  COLUMN order_status character varying(50) NOT NULL DEFAULT ''",
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

/**
 * Global regex transformations applied to every line, regardless of table.
 * Each entry maps a regex (with delimiters) to its replacement; same engine
 * filtering as table-scoped entries.
 */
$globalRegex = [
    [
        // Postgres legacy adapter doesn't translate TIMESTAMP_INIT to a
        // DEFAULT CURRENT_TIMESTAMP clause; the declarative schema sets the
        // default explicitly for all engines. Add CURRENT_TIMESTAMP to bare
        // "timestamp NOT NULL" columns on main so they match the declarative
        // output. Safe because every such column in legacy code is a
        // created_at / updated_at / event-time field that was meant to default
        // to now() but was silently dropped by the adapter.
        'engines' => ['pgsql'],
        'pattern' => '/^(  COLUMN \\S+ timestamp without time zone NOT NULL)$/',
        'replacement' => '$1 DEFAULT CURRENT_TIMESTAMP',
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
    $ops[$table] ??= ['replace' => [], 'remove' => [], 'add' => []];
    $ops[$table]['replace'] = array_merge($ops[$table]['replace'], $entry['replace'] ?? []);
    $ops[$table]['remove']  = array_merge($ops[$table]['remove'], $entry['remove']  ?? []);
    $ops[$table]['add']     = array_merge($ops[$table]['add'], $entry['add'] ?? []);
}

$activeGlobalRegex = [];
foreach ($globalRegex as $rule) {
    if (!in_array($engine, $rule['engines'], true)) {
        continue;
    }
    $activeGlobalRegex[] = $rule;
}

$input = stream_get_contents(STDIN);
if ($input === false) {
    fwrite(STDERR, "Failed to read stdin\n");
    exit(1);
}

$lines        = explode("\n", $input);
$out          = [];
$currentTable = null;
$pendingAdds  = [];

// When flushing pending "add" lines, splice them into the current table block
// and re-sort only their respective category (COLUMN / INDEX / FK) so each
// inserted line lands where it would have if the dumper had emitted it.
$flushAdds = static function () use (&$out, &$pendingAdds): void {
    if (count($pendingAdds) === 0) {
        return;
    }

    $start = count($out) - 1;
    while ($start > 0 && !str_starts_with($out[$start], 'TABLE ')) {
        $start--;
    }
    $blockStart = $start + 1;
    $block      = array_slice($out, $blockStart);

    foreach ($pendingAdds as $addLine) {
        $prefix = '';
        if (str_starts_with($addLine, '  COLUMN ')) {
            $prefix = '  COLUMN ';
        } elseif (str_starts_with($addLine, '  INDEX ')) {
            $prefix = '  INDEX ';
        } elseif (str_starts_with($addLine, '  FK ')) {
            $prefix = '  FK ';
        } else {
            $block[] = $addLine;
            continue;
        }

        $insertAt = count($block);
        foreach ($block as $i => $existing) {
            if (!str_starts_with($existing, $prefix)) {
                continue;
            }
            if (strcmp($addLine, $existing) < 0) {
                $insertAt = $i;
                break;
            }
            $insertAt = $i + 1;
        }
        array_splice($block, $insertAt, 0, [$addLine]);
    }

    $out = array_merge(array_slice($out, 0, $blockStart), $block);
    $pendingAdds = [];
};

foreach ($lines as $line) {
    if (preg_match('/^TABLE (.+)$/', $line, $m)) {
        $flushAdds();
        $currentTable = $m[1];
        $out[]        = $line;
        $pendingAdds  = $ops[$currentTable]['add'] ?? [];
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
    foreach ($activeGlobalRegex as $rule) {
        $newLine = preg_replace($rule['pattern'], $rule['replacement'], $line);
        if ($newLine !== null && $newLine !== $line) {
            $line = $newLine;
            break;
        }
    }
    $out[] = $line;
}
$flushAdds();

echo implode("\n", $out);
