<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Maho\Db\Schema\Collector;
use Tests\MahoBackendTestCase;

/**
 * Consistency guard for the two halves of a `static` EAV attribute:
 * the column declared in a module's sql/schema.php (declarative schema) and
 * the attribute registered via Mage_Eav_Model_Entity_Setup::addAttribute(...,
 * ['type' => 'static']) in a data script.
 *
 * EAV attributes are data (append-only data scripts), table shape is schema
 * (declarative schema). The one failure mode of that split is silent drift: a
 * static attribute registered with no backing column. addAttribute() only
 * inserts the eav_attribute row, it does not create the column, and entity
 * loads are SELECT * — so the missing column surfaces not as an install error
 * but quietly (the attribute reads empty) or as an "unknown column" error only
 * when something saves that attribute with a value. A new, thinly-covered
 * static attribute (e.g. the customer 2FA attributes) can therefore slip
 * through the functional suite. This test catches it structurally: every
 * registered static attribute must have a matching column on its entity table
 * in the collected declarative Schema.
 *
 * The reverse direction (a column with no registered attribute) is intentionally
 * not flagged: entity tables legitimately carry many non-attribute columns
 * (entity_id, attribute_set_id, type_id, flat denormalized fields, ...), so it
 * is a convention tripwire rather than a bug detector and is out of scope.
 */

uses(MahoBackendTestCase::class);

/**
 * Static attributes that intentionally have no backing column, keyed by
 * "{entity_type_code}/{attribute_code}" with the reason. These are virtual
 * attributes: registered as `static` so the EAV layer treats them as part of
 * the entity row rather than an EAV value, but their values are computed or
 * persisted elsewhere through a backend model, so DBAL has no column to manage.
 *
 * Keep this list short and justified — every entry is a hole in the guard.
 */
const COLUMNLESS_STATIC_ATTRIBUTES = [
    // Stored via the catalog_category_product link table (see
    // Mage_Catalog_Model_Product::getCategoryIds), not a product row column.
    'catalog_product/category_ids' => 'virtual attribute backed by the category-product link table',
];

it('backs every static EAV attribute with a declarative schema column', function () {
    [$schema] = Collector::collect();

    $resource = Mage::getSingleton('core/resource');
    $read = $resource->getConnection('core_read');

    // Every static attribute, paired with the entity type that owns it. Only
    // backend_type = 'static' has a backing column; non-static attributes store
    // their values in the EAV value tables and must be excluded.
    $select = $read->select()
        ->from(['a' => $resource->getTableName('eav/attribute')], ['attribute_code', 'backend_type'])
        ->join(
            ['e' => $resource->getTableName('eav/entity_type')],
            'a.entity_type_id = e.entity_type_id',
            ['entity_type_code', 'entity_table'],
        )
        ->where('a.backend_type = ?', 'static')
        ->order(['e.entity_type_code', 'a.attribute_code']);

    $rows = $read->fetchAll($select);

    // Sanity: the core install registers plenty of static attributes (catalog
    // product sku/created_at, customer email, ...). An empty set means the
    // query or the install is broken, which would make the guard a silent no-op.
    expect($rows)->not->toBeEmpty();

    $missing = [];
    foreach ($rows as $row) {
        $key = $row['entity_type_code'] . '/' . $row['attribute_code'];
        if (isset(COLUMNLESS_STATIC_ATTRIBUTES[$key])) {
            continue;
        }

        $table = $resource->getTableName($row['entity_table']);

        if (!$schema->hasTable($table)) {
            $missing[] = sprintf(
                '%s.%s (entity "%s") — entity table is not in the declarative schema',
                $table,
                $row['attribute_code'],
                $row['entity_type_code'],
            );
            continue;
        }

        if (!$schema->getTable($table)->hasColumn($row['attribute_code'])) {
            $missing[] = sprintf(
                '%s.%s (entity "%s")',
                $table,
                $row['attribute_code'],
                $row['entity_type_code'],
            );
        }
    }

    expect($missing)->toBe([], sprintf(
        "Static EAV attributes without a backing column in the declarative schema:\n  %s\n"
        . 'Declare the column in the entity table\'s sql/schema.php, or remove/rename the attribute registration.',
        implode("\n  ", $missing),
    ));
});
