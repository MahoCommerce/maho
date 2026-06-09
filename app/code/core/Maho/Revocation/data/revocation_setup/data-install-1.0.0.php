<?php

/**
 * Maho
 *
 * @package    Maho_Revocation
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

/** @var Mage_Core_Model_Resource_Setup $this */
$this->startSetup();

$connection = $this->getConnection();

// Statuses are free labels under the existing 'complete' state: they give merchants
// grid visibility without introducing a new order state (and its FSM side effects).
$statuses = [
    Maho_Revocation_Model_Request::ORDER_STATUS_ACCEPTED => 'Revocation Accepted',
    Maho_Revocation_Model_Request::ORDER_STATUS_REJECTED => 'Revocation Rejected',
];

foreach ($statuses as $statusCode => $label) {
    $connection->insertOnDuplicate(
        $this->getTable('sales/order_status'),
        ['status' => $statusCode, 'label' => $label],
        ['label'],
    );
    $connection->insertOnDuplicate(
        $this->getTable('sales/order_status_state'),
        ['status' => $statusCode, 'state' => Mage_Sales_Model_Order::STATE_COMPLETE, 'is_default' => 0],
        ['is_default'],
    );
}

$this->endSetup();
