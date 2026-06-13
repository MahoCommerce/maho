<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Customer
 */

/**
 * @var Mage_Customer_Model_Resource_Setup $this
 * @var Maho\Db\Adapter\AdapterInterface $conn
 */
$conn = $this->getConnection();

//get all duplicated emails
$select  = $conn->select()
    ->from($this->getTable('customer/entity'), ['email', 'website_id', 'cnt' => 'COUNT(*)'])
    ->group('email')
    ->group('website_id')
    ->having('cnt > 1');
$emails = $conn->fetchAll($select);

foreach ($emails as $data) {
    $email = $data['email'];
    $websiteId = $data['website_id'];

    $select = $conn->select()
        ->from($this->getTable('customer/entity'), ['entity_id'])
        ->where('email = ?', $email)
        ->where('website_id = ?', $websiteId);
    $activeId = $conn->fetchOne($select);

    //receive all other duplicated customer ids
    $select = $conn->select()
        ->from($this->getTable('customer/entity'), ['entity_id', 'email'])
        ->where('email = ?', $email)
        ->where('website_id = ?', $websiteId)
        ->where('entity_id <> ?', $activeId);
    $result = $conn->fetchAll($select);

    //change email to unique value
    foreach ($result as $row) {
        $changedEmail = $conn->getConcatSql(['"(duplicate"', $row['entity_id'], '")"', '"' . $row['email'] . '"']);
        $conn->update(
            $this->getTable('customer/entity'),
            ['email' => $changedEmail],
            ['entity_id =?' => $row['entity_id']],
        );
    }
}

// The matching unique index on (email, website_id) is now declared in
// sql/schema.php; the schema applier creates/upgrades it ahead of this script.
