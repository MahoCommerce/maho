<?php

/**
 * Maho
 *
 * @package    Tests
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

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
        // Rollback transaction if we started one
        if ($this->useTransactions && $this->connection) {
            $this->rollbackTransaction();
        }

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
}
