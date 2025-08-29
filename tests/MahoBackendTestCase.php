<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Tests;

use Mage;
use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * Base test case for admin/backend tests
 */
abstract class MahoBackendTestCase extends BaseTestCase
{
    private bool $useTransactions = false;
    private ?Varien_Db_Adapter_Interface $connection = null;
    private array $createdRecords = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Set the correct Maho root path and initialize Maho for backend
        $this->setMahoRoot();
        Mage::register('isSecureArea', true);
        Mage::app();

        // Start database transaction if enabled
        if ($this->shouldUseTransactions()) {
            $this->startTransaction();
        }
    }

    protected function setMahoRoot(): void
    {
        Mage::setRoot(dirname(__DIR__));
    }

    protected function tearDown(): void
    {
        // Clean up tracked records first (explicit cleanup)
        $this->cleanupTrackedRecords();

        // Then rollback transaction if we started one (safety net)
        if ($this->useTransactions && $this->connection) {
            $this->rollbackTransaction();
        }

        // Reset created records for next test
        $this->createdRecords = [];

        // Reset Maho state and restore error handlers
        Mage::reset();
        restore_error_handler();
        restore_exception_handler();

        parent::tearDown();
    }

    /**
     * Enable database transactions for this test
     * Call this in your test's beforeEach() to isolate database changes
     */
    protected function useTransactions(): void
    {
        $this->useTransactions = true;
    }

    /**
     * Check if test should use transactions
     */
    protected function shouldUseTransactions(): bool
    {
        return $this->useTransactions;
    }

    /**
     * Start database transaction
     */
    private function startTransaction(): void
    {
        try {
            $this->connection = Mage::getSingleton('core/resource')->getConnection('core_write');
            $this->connection->beginTransaction();
        } catch (Exception $e) {
            // Fallback: disable transactions if they fail
            $this->useTransactions = false;
            error_log('Transaction setup failed: ' . $e->getMessage());
        }
    }

    /**
     * Rollback database transaction
     */
    private function rollbackTransaction(): void
    {
        try {
            if ($this->connection) {
                $this->connection->rollback();
            }
        } catch (Exception $e) {
            // Log error but don't fail the test
            error_log('Failed to rollback transaction: ' . $e->getMessage());
        }
    }

    /**
     * Track a record that was created during the test for cleanup
     */
    protected function trackCreatedRecord(string $table, int $id): void
    {
        if (!isset($this->createdRecords[$table])) {
            $this->createdRecords[$table] = [];
        }
        $this->createdRecords[$table][] = $id;
    }

    /**
     * Clean up only the specific records that were tracked during this test
     */
    private function cleanupTrackedRecords(): void
    {
        if (empty($this->createdRecords)) {
            return;
        }

        try {
            $connection = Mage::getSingleton('core/resource')->getConnection('core_write');

            // Clean up in dependency order (children first) - relationships handled by CASCADE
            $cleanupOrder = ['sales_flat_order', 'customer_entity', 'customer_segment'];

            foreach ($cleanupOrder as $table) {
                if (isset($this->createdRecords[$table]) && !empty($this->createdRecords[$table])) {
                    $ids = array_unique($this->createdRecords[$table]);
                    $idField = $this->getIdFieldForTable($table);
                    $connection->delete($table, $connection->quoteInto($idField . ' IN (?)', $ids));
                }
            }

        } catch (Exception $e) {
            // Log error but don't fail the test
            error_log('Failed to cleanup tracked records: ' . $e->getMessage());
        }
    }

    /**
     * Get the ID field name for a given table
     */
    private function getIdFieldForTable(string $table): string
    {
        $idFields = [
            'customer_segment' => 'segment_id',
            'customer_entity' => 'entity_id',
            'sales_flat_order' => 'entity_id',
        ];

        return $idFields[$table] ?? 'id';
    }
}
