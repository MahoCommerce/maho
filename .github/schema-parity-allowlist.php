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

    // ----- Zero-date sentinel removal -----
    //
    // Legacy install scripts seeded DATETIME columns with the MySQL-ism
    // '0000-00-00 00:00:00' (rewritten to '1970-01-01 00:00:00' by the legacy
    // Postgres adapter). MySQL 8 strict-mode rejects the sentinel on insert,
    // and a "fake date" default is poor schema design anyway. The declarative
    // schema drops the default — datetime value columns in EAV become NULL,
    // and customer_flowpassword.requested_date keeps NOT NULL but relies on
    // Mage_Customer_Model_Flowpassword::_beforeSave() to populate it.
    [
        'engines' => ['mysql'],
        'table' => 'customer_flowpassword',
        'replace' => [
            '  COLUMN requested_date varchar(255) NOT NULL DEFAULT 0000-00-00 00:00:00' => '  COLUMN requested_date varchar(255) NOT NULL',
        ],
    ],
    [
        'engines' => ['pgsql'],
        'table' => 'customer_flowpassword',
        'replace' => [
            "  COLUMN requested_date character varying(255) NOT NULL DEFAULT '0000-00-00 00:00:00'" => '  COLUMN requested_date character varying(255) NOT NULL',
        ],
    ],
    [
        'engines' => ['mysql'],
        'table' => 'eav_entity_datetime',
        'replace' => [
            '  COLUMN value datetime NOT NULL DEFAULT 0000-00-00 00:00:00' => '  COLUMN value datetime NULL',
        ],
    ],
    [
        'engines' => ['pgsql'],
        'table' => 'eav_entity_datetime',
        'replace' => [
            "  COLUMN value timestamp without time zone NOT NULL DEFAULT '1970-01-01 00:00:00'" => '  COLUMN value timestamp without time zone NULL',
        ],
    ],
    [
        'engines' => ['mysql'],
        'table' => 'customer_address_entity_datetime',
        'replace' => [
            '  COLUMN value datetime NOT NULL DEFAULT 0000-00-00 00:00:00' => '  COLUMN value datetime NULL',
        ],
    ],
    [
        'engines' => ['pgsql'],
        'table' => 'customer_address_entity_datetime',
        'replace' => [
            "  COLUMN value timestamp without time zone NOT NULL DEFAULT '1970-01-01 00:00:00'" => '  COLUMN value timestamp without time zone NULL',
        ],
    ],
    [
        'engines' => ['mysql'],
        'table' => 'customer_entity_datetime',
        'replace' => [
            '  COLUMN value datetime NOT NULL DEFAULT 0000-00-00 00:00:00' => '  COLUMN value datetime NULL',
        ],
    ],
    [
        'engines' => ['pgsql'],
        'table' => 'customer_entity_datetime',
        'replace' => [
            "  COLUMN value timestamp without time zone NOT NULL DEFAULT '1970-01-01 00:00:00'" => '  COLUMN value timestamp without time zone NULL',
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
    // api_user.lognum: legacy install declares it SMALLINT; an upgrade widens
    // it to INTEGER via changeColumn, which works on MySQL but Maho's pgsql
    // adapter's ALTER COLUMN TYPE leaves it at SMALLINT. The declarative
    // schema reflects the intended post-upgrade INTEGER type.
    [
        'engines' => ['pgsql'],
        'table' => 'api_user',
        'replace' => [
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

    // Tables where declarative is NOT NULL without default; the global regex
    // added CURRENT_TIMESTAMP to main, but PR doesn't have it. Strip it back.
    [
        'engines' => ['pgsql'],
        'table' => 'sales_flat_order',
        'replace' => [
            '  COLUMN created_at timestamp without time zone NOT NULL DEFAULT CURRENT_TIMESTAMP' => '  COLUMN created_at timestamp without time zone NOT NULL',
            '  COLUMN updated_at timestamp without time zone NOT NULL DEFAULT CURRENT_TIMESTAMP' => '  COLUMN updated_at timestamp without time zone NOT NULL',
        ],
    ],
    [
        'engines' => ['pgsql'],
        'table' => 'giftcard',
        'replace' => [
            '  COLUMN created_at timestamp without time zone NOT NULL DEFAULT CURRENT_TIMESTAMP' => '  COLUMN created_at timestamp without time zone NOT NULL',
            '  COLUMN updated_at timestamp without time zone NOT NULL DEFAULT CURRENT_TIMESTAMP' => '  COLUMN updated_at timestamp without time zone NOT NULL',
        ],
    ],
    [
        'engines' => ['pgsql'],
        'table' => 'giftcard_history',
        'replace' => [
            '  COLUMN created_at timestamp without time zone NOT NULL DEFAULT CURRENT_TIMESTAMP' => '  COLUMN created_at timestamp without time zone NOT NULL',
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
            '  COLUMN is_allowed tinyint NOT NULL DEFAULT 0' => '  COLUMN is_allowed smallint NOT NULL DEFAULT 0',
        ],
    ],
    [
        'engines' => ['mysql'],
        'table' => 'permission_variable',
        'replace' => [
            '  COLUMN is_allowed tinyint NOT NULL DEFAULT 0' => '  COLUMN is_allowed smallint NOT NULL DEFAULT 0',
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
 * Implicit single-column indexes DBAL adds on FK local columns.
 *
 * DBAL's Table::_addForeignKeyConstraint creates an index per FK because its
 * Index::isFulfilledBy demands exact column count: a 2-col PK starting with
 * the FK column does NOT count as covering a 1-col FK in DBAL's view. Real
 * RDBMSes are smarter (InnoDB skips the auto-index when a covering composite
 * exists; Postgres relies on user-declared indexes). The declarative schema
 * intentionally keeps DBAL's extras: they're harmless on MySQL (redundant
 * with InnoDB's own implicit index), genuinely useful on Postgres (legacy
 * install creates none, leaving FK columns unindexed), and make the schema
 * shape consistent across engines.
 *
 * Listed per engine because main's legacy install produces different gaps:
 * MySQL's InnoDB silently creates indexes for some FKs, Postgres for none.
 * The two arrays mirror the actual schema-parity diff against main. They
 * will be removed wholesale when the legacy install paths are deleted.
 */
$dbalImplicitFkIndexes = [
    'mysql' => [
        'admin_rule' => ['role_id'],
        'api2_acl_rule' => ['role_id'],
        'api_rule' => ['role_id'],
        'blog_category_store' => ['category_id'],
        'blog_post_category' => ['post_id'],
        'blog_post_store' => ['post_id'],
        'catalog_category_product' => ['category_id'],
        'catalog_category_product_index' => ['category_id', 'product_id', 'store_id'],
        'catalog_product_bundle_option_value' => ['option_id'],
        'catalog_product_bundle_price_index' => ['entity_id'],
        'catalog_product_bundle_selection_price' => ['selection_id'],
        'catalog_product_enabled_index' => ['product_id'],
        'catalog_product_entity_media_gallery_value' => ['value_id'],
        'catalog_product_index_group_price' => ['entity_id'],
        'catalog_product_index_price' => ['entity_id'],
        'catalog_product_index_tier_price' => ['entity_id'],
        'catalog_product_relation' => ['parent_id'],
        'catalog_product_website' => ['product_id'],
        'cataloginventory_stock_status' => ['product_id'],
        'checkout_agreement_store' => ['agreement_id'],
        'cms_block_store' => ['block_id'],
        'cms_page_store' => ['page_id'],
        'core_email_queue_recipients' => ['message_id'],
        'core_layout_link' => ['store_id'],
        'customer_eav_attribute_website' => ['attribute_id'],
        'customer_segment_customer' => ['segment_id'],
        'customer_segment_email_sequence' => ['segment_id'],
        'customer_segment_sequence_progress' => ['segment_id'],
        'eav_attribute_group' => ['attribute_set_id'],
        'eav_attribute_set' => ['entity_type_id'],
        'eav_entity_attribute' => ['attribute_group_id'],
        'eav_form_type_entity' => ['type_id'],
        'feedmanager_attribute_mapping' => ['feed_id'],
        'feedmanager_log' => ['feed_id'],
        'index_process_event' => ['process_id'],
        'newsletter_queue_store_link' => ['queue_id'],
        'rating_store' => ['rating_id'],
        'rating_title' => ['rating_id'],
        'report_compared_product_index' => ['customer_id'],
        'report_viewed_product_index' => ['customer_id'],
        'review_store' => ['review_id'],
        'sales_billing_agreement_order' => ['agreement_id'],
        'sales_order_status_label' => ['status'],
        'sales_order_status_state' => ['status'],
        'sales_recurring_profile_order' => ['profile_id'],
        'salesrule_customer' => ['customer_id', 'rule_id'],
        'salesrule_product_attribute' => ['rule_id'],
        'tag_properties' => ['tag_id'],
    ],
    'pgsql' => [
        'admin_rule' => ['role_id'],
        'api2_acl_rule' => ['role_id'],
        'api2_acl_user' => ['role_id'],
        'api_rule' => ['role_id'],
        'blog_category_store' => ['category_id'],
        'blog_post_category' => ['post_id'],
        'blog_post_store' => ['post_id'],
        'catalog_category_product' => ['category_id'],
        'catalog_category_product_index' => ['category_id', 'product_id', 'store_id'],
        'catalog_product_bundle_option_value' => ['option_id'],
        'catalog_product_bundle_price_index' => ['entity_id'],
        'catalog_product_bundle_selection_price' => ['selection_id'],
        'catalog_product_enabled_index' => ['product_id'],
        'catalog_product_entity_media_gallery_value' => ['value_id'],
        'catalog_product_index_group_price' => ['entity_id'],
        'catalog_product_index_price' => ['entity_id'],
        'catalog_product_index_tier_price' => ['entity_id'],
        'catalog_product_relation' => ['parent_id'],
        'catalog_product_website' => ['product_id'],
        'cataloginventory_stock_status' => ['product_id'],
        'checkout_agreement_store' => ['agreement_id'],
        'cms_block_store' => ['block_id'],
        'cms_page_store' => ['page_id'],
        'core_email_queue_recipients' => ['message_id'],
        'core_layout_link' => ['store_id'],
        'core_url_rewrite' => ['category_id', 'product_id'],
        'customer_eav_attribute_website' => ['attribute_id'],
        'customer_segment_customer' => ['segment_id'],
        'customer_segment_email_sequence' => ['coupon_sales_rule_id', 'segment_id', 'template_id'],
        'customer_segment_sequence_progress' => ['queue_id', 'segment_id'],
        'eav_attribute_group' => ['attribute_set_id'],
        'eav_attribute_set' => ['entity_type_id'],
        'eav_entity_attribute' => ['attribute_group_id'],
        'eav_form_type_entity' => ['type_id'],
        'feedmanager_attribute_mapping' => ['feed_id'],
        'feedmanager_category_mapping' => ['category_id'],
        'feedmanager_log' => ['feed_id'],
        'index_process_event' => ['process_id'],
        'newsletter_queue_store_link' => ['queue_id'],
        'oauth_token' => ['admin_id', 'customer_id'],
        'rating_option_vote' => ['review_id'],
        'rating_store' => ['rating_id'],
        'rating_title' => ['rating_id'],
        'report_compared_product_index' => ['customer_id'],
        'report_viewed_product_index' => ['customer_id'],
        'review_store' => ['review_id'],
        'sales_billing_agreement_order' => ['agreement_id'],
        'sales_order_status_label' => ['status'],
        'sales_order_status_state' => ['status'],
        'sales_recurring_profile_order' => ['profile_id'],
        'salesrule_customer' => ['customer_id', 'rule_id'],
        'salesrule_product_attribute' => ['rule_id'],
        'tag' => ['first_customer_id', 'first_store_id'],
        'tag_properties' => ['tag_id'],
        'wishlist_item_option' => ['wishlist_item_id'],
    ],
];
foreach ($dbalImplicitFkIndexes as $eng => $tables) {
    foreach ($tables as $table => $columns) {
        $entries[] = [
            'engines' => [$eng],
            'table' => $table,
            'add' => array_map(static fn(string $c): string => "  INDEX [IDX] ({$c})", $columns),
        ];
    }
}

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
    // Apply global regex transforms first so per-table replace/remove
    // entries can be written against the post-transform line (which is
    // also what shows up in the diff output).
    foreach ($activeGlobalRegex as $rule) {
        $newLine = preg_replace($rule['pattern'], $rule['replacement'], $line);
        if ($newLine !== null && $newLine !== $line) {
            $line = $newLine;
            break;
        }
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
$flushAdds();

echo implode("\n", $out);
