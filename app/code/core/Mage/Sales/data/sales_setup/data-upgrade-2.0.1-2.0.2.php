<?php

/**
 * Gives the orders that only have the quote copy of an uploaded option file an order copy.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

declare(strict_types=1);

/** @var Mage_Sales_Model_Resource_Setup $this */
$installer = $this;
$connection = $installer->getConnection();
$table = $installer->getTable('sales/order_item');
$fileType = Mage::getModel('catalog/product_option_type_file');
$stringHelper = Mage::helper('core/string');

// The cart cleanup deletes the quote files that no cart names. An order placed where the copy
// observer did not run (the REST API, the admin) can have only the quote copy.
$done = [];
$copyFiles = function (mixed $data) use (&$copyFiles, &$done, $fileType, $stringHelper): void {
    if (is_string($data) && str_contains($data, 'order_path')) {
        $data = $stringHelper->unserialize($data);
    }
    if (!is_array($data)) {
        return;
    }
    if (isset($data['quote_path'], $data['order_path'])) {
        $key = (string) $data['order_path'];
        if (!isset($done[$key])) {
            $done[$key] = true;
            try {
                $fileType->copyQuoteFileToOrder($data);
            } catch (Exception $e) {
                Mage::logException($e);
            }
        }
        return;
    }
    foreach ($data as $child) {
        $copyFiles($child);
    }
};

$lastId = 0;
do {
    $rows = $connection->fetchPairs(
        $connection->select()
            ->from($table, ['item_id', 'product_options'])
            ->where('item_id > ?', $lastId)
            ->where('product_options LIKE ?', '%order_path%')
            ->order('item_id')
            ->limit(1000),
    );
    foreach ($rows as $itemId => $productOptions) {
        $lastId = (int) $itemId;
        $copyFiles((string) $productOptions);
    }
} while (count($rows) === 1000);
