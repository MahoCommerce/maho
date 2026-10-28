<?php

/**
 * Percent-encode the stored link and sample URLs that the RFC 3986 parser refuses, so that downloads keep working.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Downloadable
 */

/** @var Mage_Catalog_Model_Resource_Setup $this */
$installer = $this;
$connection = $installer->getConnection();

$encode = static function (string $url): ?string {
    $authorityStart = strpos($url, '://');
    if ($authorityStart === false) {
        return null;
    }
    // The host stays as it is, so a URL with a broken host stays refused
    $pathStart = $authorityStart + 3 + strcspn($url, '/?#', $authorityStart + 3);
    $tail = preg_replace_callback(
        "/[^A-Za-z0-9\\-._~!$&'()*+,;=:@\\/?#%]/",
        fn(array $match): string => rawurlencode($match[0]),
        substr($url, $pathStart),
    );
    $fixed = substr($url, 0, $pathStart) . $tail;
    return strlen($fixed) <= 255 && Uri\Rfc3986\Uri::parse($fixed) !== null ? $fixed : null;
};

$columns = [
    ['downloadable/link', 'link_id', 'link_url', 'link_type'],
    ['downloadable/link', 'link_id', 'sample_url', 'sample_type'],
    ['downloadable/sample', 'sample_id', 'sample_url', 'sample_type'],
    ['downloadable/link_purchased_item', 'item_id', 'link_url', 'link_type'],
];
foreach ($columns as [$alias, $idColumn, $urlColumn, $typeColumn]) {
    $table = $installer->getTable($alias);
    $select = $connection->select()
        ->from($table, [$idColumn, $urlColumn])
        ->where($typeColumn . ' = ?', Mage_Downloadable_Helper_Download::LINK_TYPE_URL)
        ->where($urlColumn . ' IS NOT NULL');
    foreach ($connection->fetchPairs($select) as $id => $url) {
        if (Uri\Rfc3986\Uri::parse((string) $url) !== null) {
            continue;
        }
        $fixed = $encode((string) $url);
        if ($fixed !== null) {
            $connection->update($table, [$urlColumn => $fixed], [$idColumn . ' = ?' => $id]);
        }
    }
}
