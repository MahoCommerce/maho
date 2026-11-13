#!/bin/bash
# SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
# SPDX-License-Identifier: OSL-3.0
#
# Run Pest as PEST_WORKERS shards at the same time, in one CI job. Each worker needs its own
# database and its own var directory: the tests write the configuration and flush the cache,
# and one shared copy lets a worker change the store under the tests of another worker.
#
# Worker 1 runs in the checkout. Worker N runs in var/workers/N, a copy of the checkout with
# its own local.xml, var directory and database copy. Maho then needs no change to select them.
#
# Usage: pest-workers.sh <pest arguments>
# Environment: PEST_WORKERS (the number of workers), DB_ENGINE (mysql, pgsql or sqlite).

set -euo pipefail

ROOT="$PWD"
WORKERS="${PEST_WORKERS:?}"
DB_NAME="$(php -r 'echo simplexml_load_file("app/etc/local.xml")->global->resources->default_setup->connection->dbname;')"
if [ "$DB_ENGINE" = "sqlite" ] && [[ "$DB_NAME" == /* ]]; then
    echo "The SQLite path in local.xml must be relative to var/db, so that each copy finds its own file."
    exit 1
fi

copy_database() {
    local worker="$1" target="$2"
    case "$DB_ENGINE" in
        pgsql)
            # A file copy inside the server. The template must have no other open connection,
            # so the lock lets only one copy run at a time.
            flock "$ROOT/var/workers/pgsql.lock" docker exec "$(docker ps -q)" psql -U postgres -q -v ON_ERROR_STOP=1 \
                -c "CREATE DATABASE ${DB_NAME}_$worker TEMPLATE $DB_NAME"
            set_db_name "$target" "${DB_NAME}_$worker"
            ;;
        mysql)
            # shellcheck disable=SC2016
            local cli='exec "$(command -v mariadb || command -v mysql)" -uroot -proot'
            docker exec "$(docker ps -q)" sh -c "$cli -e 'CREATE DATABASE ${DB_NAME}_$worker'"
            docker exec -i "$(docker ps -q)" sh -c "$cli ${DB_NAME}_$worker" < "$ROOT/var/workers/dump.sql"
            set_db_name "$target" "${DB_NAME}_$worker"
            ;;
        sqlite)
            # The database is in WAL mode, so a plain cp can miss the last writes.
            # The relative path in local.xml resolves in the var/db of the copy.
            mkdir -p "$(dirname "$target/var/db/$DB_NAME")"
            # shellcheck disable=SC2016
            php -r '(new PDO("sqlite:" . $argv[1]))->exec("VACUUM INTO " . var_export($argv[2], true));' \
                "$ROOT/var/db/$DB_NAME" "$target/var/db/$DB_NAME"
            ;;
    esac
}

set_db_name() {
    local target="$1" name="$2"
    sed -i "s|<dbname><!\[CDATA\[$DB_NAME\]\]></dbname>|<dbname><![CDATA[$name]]></dbname>|" "$target/app/etc/local.xml"
    grep -q "<dbname><!\[CDATA\[$name\]\]></dbname>" "$target/app/etc/local.xml" \
        || { echo "Could not set the database name in $target/app/etc/local.xml"; exit 1; }
}

mkdir -p var/workers
if [ "$DB_ENGINE" = "mysql" ]; then
    # GTID mode is on for MySQL here, and a restore refuses a dump that sets GTID_PURGED.
    docker exec "$(docker ps -q)" sh -c "if command -v mariadb-dump >/dev/null; then exec mariadb-dump -uroot -proot --single-transaction --routines --triggers --events $DB_NAME; fi; exec mysqldump -uroot -proot --single-transaction --routines --triggers --events --set-gtid-purged=OFF $DB_NAME" > var/workers/dump.sql
fi

PIDS=()
for i in $(seq 2 "$WORKERS"); do
    (
        target="$ROOT/var/workers/$i"
        rsync -a --exclude=/.git --exclude=/node_modules --exclude=/var "$ROOT/" "$target/"
        mkdir -p "$target/var"
        copy_database "$i" "$target"
    ) &
    PIDS+=($!)
done
# A bare "wait" returns 0 even when a copy fails.
for pid in "${PIDS[@]}"; do wait "$pid"; done

PIDS=()
for i in $(seq 1 "$WORKERS"); do
    dir="$ROOT"
    if [ "$i" -gt 1 ]; then dir="$ROOT/var/workers/$i"; fi
    (cd "$dir" && ./vendor/bin/pest --colors=always --shard="$i/$WORKERS" "$@") > "var/workers/worker-$i.log" 2>&1 &
    PIDS+=($!)
done

STATUS=0
for i in $(seq 1 "$WORKERS"); do
    if wait "${PIDS[$((i - 1))]}"; then RESULT=passed; else RESULT=failed; STATUS=1; fi
    echo "::group::Worker $i of $WORKERS ($RESULT)"
    cat "var/workers/worker-$i.log"
    echo "::endgroup::"
done
exit "$STATUS"
