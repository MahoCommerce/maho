<?php

/**
 * Maho
 *
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Canonical schema dumper for cross-branch / pre-vs-post-migration parity checks.
 *
 * Reads DB_ENGINE, DB_HOST, DB_NAME, DB_USER, DB_PASS from the environment and
 * emits a deterministic text representation of the schema to STDOUT. Two dumps
 * that compare equal describe behaviourally identical schemas.
 *
 * Usage:
 *     DB_ENGINE=mysql DB_NAME=maho ... php .github/schema-dump.php > dump.txt
 *
 * The engine-specific renderings are normalized so the same logical schema
 * dumps identically across MySQL/MariaDB (display widths, quoted defaults) and
 * Postgres (varchar(1024) text, SERIAL-vs-IDENTITY, ::type casts). The same
 * normalizer is the one the Schema Parity workflow uses; keeping it in one
 * committed file lets both the parity and migration workflows share it.
 */

declare(strict_types=1);

$engine = getenv('DB_ENGINE');
$host   = getenv('DB_HOST') ?: '127.0.0.1';
$db     = getenv('DB_NAME') ?: 'maho';
$user   = getenv('DB_USER') ?: '';
$pass   = getenv('DB_PASS') ?: '';

$opts = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];
[$pdo, $driver] = match ($engine) {
    'mysql'  => [new PDO("mysql:host=$host;dbname=$db", $user, $pass, $opts), 'mysql'],
    'pgsql'  => [new PDO("pgsql:host=$host;dbname=$db;user=$user;password=$pass", null, null, $opts), 'pgsql'],
    'sqlite' => [new PDO("sqlite:$db", null, null, $opts), 'sqlite'],
    default  => throw new RuntimeException("Unknown DB_ENGINE: $engine"),
};

// Tables known to be seed-populated by install — row counts compared.
const SEED_TABLES = [
    'core_website', 'core_store', 'core_store_group',
    'directory_country', 'directory_country_region',
    'directory_currency_rate',
    'eav_entity_type', 'eav_attribute_set', 'eav_attribute_group',
    'tax_class',
];

function q(string $driver, string $ident): string
{
    return $driver === 'mysql' ? "`$ident`" : '"' . $ident . '"';
}

function tables(PDO $pdo, string $driver): array
{
    return match ($driver) {
        'mysql'  => $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE' ORDER BY TABLE_NAME")->fetchAll(PDO::FETCH_COLUMN),
        'pgsql'  => $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' AND table_type = 'BASE TABLE' ORDER BY table_name")->fetchAll(PDO::FETCH_COLUMN),
        'sqlite' => $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN),
    };
}

function columns(PDO $pdo, string $driver, string $table): array
{
    $out = [];
    if ($driver === 'mysql') {
        $st = $pdo->prepare('SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION');
        $st->execute([$table]);
        foreach ($st as $r) {
            $type     = $r['COLUMN_TYPE'];
            $nullable = $r['IS_NULLABLE'] === 'YES';
            $default  = $r['COLUMN_DEFAULT'];
            // MariaDB renders integer types with the legacy display width
            // (e.g. "int(11)") while MySQL 8.4 dropped it. Strip on all
            // integer-family types so the dump renders the same on both.
            $type = preg_replace(
                '/^(tinyint|smallint|mediumint|int|bigint)\(\d+\)/',
                '$1',
                $type,
            );
            // MariaDB renders a NULL default explicitly on nullable columns;
            // MySQL 8.4 omits it. Strip the redundant default so lines match.
            if ($nullable && $default === 'NULL') {
                $default = null;
            }
            // MariaDB wraps string defaults in single quotes; MySQL 8.4 returns
            // the bare value. Strip the surrounding quotes.
            if ($default !== null && strlen($default) >= 2
                && $default[0] === "'" && $default[strlen($default) - 1] === "'") {
                $default = substr($default, 1, -1);
            }
            $out[] = sprintf(
                '  COLUMN %s %s %s%s%s',
                $r['COLUMN_NAME'],
                $type,
                $nullable ? 'NULL' : 'NOT NULL',
                $default !== null ? ' DEFAULT ' . $default : '',
                $r['EXTRA'] !== '' ? ' [' . $r['EXTRA'] . ']' : '',
            );
        }
    } elseif ($driver === 'pgsql') {
        $st = $pdo->prepare("SELECT column_name, data_type, character_maximum_length, numeric_precision, numeric_scale, is_nullable, column_default, is_identity FROM information_schema.columns WHERE table_schema = 'public' AND table_name = ? ORDER BY ordinal_position");
        $st->execute([$table]);
        foreach ($st as $r) {
            $type = $r['data_type'];
            // Normalize "character varying(1024)" -> "text". The legacy Postgres
            // adapter renders TYPE_TEXT without explicit length as varchar(1024);
            // the declarative schema uses Types::TEXT which emits "text".
            if ($r['data_type'] === 'character varying' && (int) $r['character_maximum_length'] === 1024) {
                $type = 'text';
            } elseif ($r['character_maximum_length']) {
                $type .= "({$r['character_maximum_length']})";
            } elseif (in_array($r['data_type'], ['numeric', 'decimal'], true) && $r['numeric_precision']) {
                $type .= "({$r['numeric_precision']},{$r['numeric_scale']})";
            }
            $default = $r['column_default'];
            $isIdentity = $r['is_identity'] === 'YES';
            // Treat SERIAL (nextval-based) defaults as IDENTITY: both encode
            // "autoincrement column".
            if ($default !== null && preg_match("/^nextval\\('[^']+_seq'(::regclass)?\\)$/", $default)) {
                $default = null;
                $isIdentity = true;
            }
            // Strip postgres-style ::type casts from default literals.
            if ($default !== null) {
                $default = preg_replace('/::[a-zA-Z_ ]+(\\([^)]*\\))?/', '', $default);
                if (preg_match("/^'(-?\\d+(?:\\.\\d+)?)'$/", $default, $m)) {
                    $default = $m[1];
                }
                // A numeric default's trailing zeros carry no meaning: the legacy
                // adapter rendered some as '0'::numeric and others as 0.0000 for
                // the same value, which is also why the migration's Comparator
                // emits no change between them. Normalize so the dump's notion of
                // equivalence matches the migration's.
                if (preg_match('/^-?\\d+\\.\\d+$/', $default)) {
                    $default = rtrim(rtrim($default, '0'), '.');
                }
            }
            if ($r['is_nullable'] === 'YES' && $default === 'NULL') {
                $default = null;
            }
            $out[] = sprintf(
                '  COLUMN %s %s %s%s%s',
                $r['column_name'],
                $type,
                $r['is_nullable'] === 'YES' ? 'NULL' : 'NOT NULL',
                $default !== null ? ' DEFAULT ' . $default : '',
                $isIdentity ? ' [IDENTITY]' : '',
            );
        }
    } else {
        $st = $pdo->query('PRAGMA table_info(' . q('sqlite', $table) . ')');
        foreach ($st as $r) {
            $out[] = sprintf(
                '  COLUMN %s %s %s%s%s',
                $r['name'],
                $r['type'],
                $r['notnull'] ? 'NOT NULL' : 'NULL',
                $r['dflt_value'] !== null ? ' DEFAULT ' . $r['dflt_value'] : '',
                $r['pk'] ? ' [PK]' : '',
            );
        }
    }
    return $out;
}

function indexes(PDO $pdo, string $driver, string $table): array
{
    $out = [];
    if ($driver === 'mysql') {
        $st = $pdo->prepare('SELECT INDEX_NAME, NON_UNIQUE, GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS cols FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? GROUP BY INDEX_NAME, NON_UNIQUE');
        $st->execute([$table]);
        foreach ($st as $r) {
            $type = $r['INDEX_NAME'] === 'PRIMARY' ? 'PK' : ($r['NON_UNIQUE'] ? 'IDX' : 'UNIQ');
            $out[] = sprintf('  INDEX [%s] (%s)', $type, $r['cols']);
        }
    } elseif ($driver === 'pgsql') {
        $st = $pdo->prepare("
            SELECT i.relname AS name,
                   ix.indisprimary AS is_pk,
                   ix.indisunique AS is_unique,
                   (
                       SELECT string_agg(att.attname, ',' ORDER BY ord)
                       FROM unnest(ix.indkey) WITH ORDINALITY AS u(attnum, ord)
                       JOIN pg_attribute att
                         ON att.attrelid = ix.indrelid AND att.attnum = u.attnum
                   ) AS cols
            FROM pg_class t
            JOIN pg_index ix ON t.oid = ix.indrelid
            JOIN pg_class i ON i.oid = ix.indexrelid
            JOIN pg_namespace n ON n.oid = t.relnamespace
            WHERE t.relname = ? AND n.nspname = 'public'
        ");
        $st->execute([$table]);
        foreach ($st as $r) {
            $isPk     = $r['is_pk'] === 't' || $r['is_pk'] === true || $r['is_pk'] === 1;
            $isUnique = $r['is_unique'] === 't' || $r['is_unique'] === true || $r['is_unique'] === 1;
            $type     = $isPk ? 'PK' : ($isUnique ? 'UNIQ' : 'IDX');
            $out[]    = sprintf('  INDEX [%s] (%s)', $type, $r['cols']);
        }
    } else {
        $st = $pdo->query('PRAGMA index_list(' . q('sqlite', $table) . ')');
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $idx) {
            $colsSt = $pdo->query('PRAGMA index_info(' . q('sqlite', $idx['name']) . ')');
            $cols = [];
            foreach ($colsSt as $c) {
                $cols[(int) $c['seqno']] = $c['name'];
            }
            ksort($cols);
            $type = $idx['unique'] ? 'UNIQ' : 'IDX';
            $out[] = sprintf('  INDEX [%s] (%s)', $type, implode(',', $cols));
        }
    }
    // Legacy installs sometimes declare the same index twice; dedupe so the
    // dump renders identically on both sides.
    $out = array_values(array_unique($out));
    sort($out);
    return $out;
}

function fks(PDO $pdo, string $driver, string $table): array
{
    $out = [];
    // NO ACTION and RESTRICT are semantically identical (both prevent the
    // parent row from being changed when a child row exists). Normalize.
    $normalizeFkAction = static fn(string $action): string => $action === 'NO ACTION' ? 'RESTRICT' : $action;

    if ($driver === 'mysql') {
        $st = $pdo->prepare('
            SELECT kcu.COLUMN_NAME, kcu.REFERENCED_TABLE_NAME, kcu.REFERENCED_COLUMN_NAME,
                   rc.UPDATE_RULE, rc.DELETE_RULE
            FROM information_schema.KEY_COLUMN_USAGE kcu
            JOIN information_schema.REFERENTIAL_CONSTRAINTS rc
              ON rc.CONSTRAINT_SCHEMA = kcu.TABLE_SCHEMA
             AND rc.CONSTRAINT_NAME = kcu.CONSTRAINT_NAME
            WHERE kcu.TABLE_SCHEMA = DATABASE()
              AND kcu.TABLE_NAME = ?
              AND kcu.REFERENCED_TABLE_NAME IS NOT NULL
        ');
        $st->execute([$table]);
        foreach ($st as $r) {
            $out[] = sprintf(
                '  FK %s -> %s(%s) ON DELETE %s ON UPDATE %s',
                $r['COLUMN_NAME'],
                $r['REFERENCED_TABLE_NAME'],
                $r['REFERENCED_COLUMN_NAME'],
                $normalizeFkAction($r['DELETE_RULE']),
                $normalizeFkAction($r['UPDATE_RULE']),
            );
        }
    } elseif ($driver === 'pgsql') {
        $st = $pdo->prepare("
            SELECT ku.column_name,
                   ccu.table_name AS ref_table,
                   ccu.column_name AS ref_column,
                   rc.update_rule,
                   rc.delete_rule
            FROM information_schema.table_constraints tc
            JOIN information_schema.key_column_usage ku
              ON tc.constraint_name = ku.constraint_name
             AND tc.table_schema = ku.table_schema
            JOIN information_schema.referential_constraints rc
              ON tc.constraint_name = rc.constraint_name
             AND tc.table_schema = rc.constraint_schema
            JOIN information_schema.constraint_column_usage ccu
              ON ccu.constraint_name = tc.constraint_name
             AND ccu.table_schema = tc.table_schema
            WHERE tc.constraint_type = 'FOREIGN KEY'
              AND tc.table_schema = 'public'
              AND tc.table_name = ?
        ");
        $st->execute([$table]);
        foreach ($st as $r) {
            $out[] = sprintf(
                '  FK %s -> %s(%s) ON DELETE %s ON UPDATE %s',
                $r['column_name'],
                $r['ref_table'],
                $r['ref_column'],
                $normalizeFkAction($r['delete_rule']),
                $normalizeFkAction($r['update_rule']),
            );
        }
    } else {
        $st = $pdo->query('PRAGMA foreign_key_list(' . q('sqlite', $table) . ')');
        foreach ($st as $r) {
            $out[] = sprintf(
                '  FK %s -> %s(%s) ON DELETE %s ON UPDATE %s',
                $r['from'],
                $r['table'],
                $r['to'],
                $r['on_delete'],
                $r['on_update'],
            );
        }
    }
    sort($out);
    return $out;
}

foreach (tables($pdo, $driver) as $table) {
    echo "TABLE $table\n";
    // Sort columns alphabetically: the declarative schema may add cross-module
    // grafted columns at different positions than the legacy install did, but
    // the semantics are identical. Sorting eliminates that false-positive.
    $cols = columns($pdo, $driver, $table);
    sort($cols);
    foreach ($cols as $line) {
        echo "$line\n";
    }
    foreach (indexes($pdo, $driver, $table) as $line) {
        echo "$line\n";
    }
    foreach (fks($pdo, $driver, $table) as $line) {
        echo "$line\n";
    }
    if (in_array($table, SEED_TABLES, true)) {
        $count = (int) $pdo->query('SELECT COUNT(*) FROM ' . q($driver, $table))->fetchColumn();
        echo "  SEEDED_ROWS $count\n";
    }
    echo "\n";
}
