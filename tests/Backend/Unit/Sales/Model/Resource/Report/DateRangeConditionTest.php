<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

use Maho\Db\Select;

uses(Tests\MahoBackendTestCase::class);

/**
 * Tests for MySQL 8.0+ compatibility fix: date range conditions should use WHERE, not HAVING.
 *
 * MySQL 8.0+ enforces ONLY_FULL_GROUP_BY by default, which restricts HAVING clauses to only
 * reference SELECT aliases, GROUP BY columns, or aggregate functions. Date range filtering
 * should use WHERE (evaluated before GROUP BY) rather than HAVING (evaluated after GROUP BY).
 *
 * The fix also ensures cross-database compatibility by filtering on source date columns
 * directly rather than using LIKE on DATE columns (which doesn't work in PostgreSQL).
 *
 * @see https://github.com/MahoCommerce/maho/issues/532
 */
describe('Report Date Range Condition - MySQL 8.0+ Compatibility', function () {
    beforeEach(function () {
        $this->adapter = Mage::getSingleton('core/resource')->getConnection('core_read');
    });

    it('applies date range conditions via WHERE clause, not HAVING', function () {
        // Create a select similar to what report aggregation does
        $select = $this->adapter->select();
        $select->from(['t' => 'some_table'], ['period', 'store_id', 'total' => new Maho\Db\Expr('SUM(amount)')]);

        // The fix: use where() with direct date column filtering
        $select->where('t.created_at >= ?', '2025-01-01');
        $select->where('t.created_at <= ?', '2025-01-03');
        $select->group(['period', 'store_id']);

        // Get the full SQL to verify structure
        $sql = (string) $select;

        // Verify date condition is in WHERE clause (appears before GROUP BY)
        expect($sql)->toContain('WHERE');
        expect($sql)->toContain('created_at');

        // WHERE should come before GROUP BY
        $wherePos = strpos($sql, 'WHERE');
        $groupPos = strpos($sql, 'GROUP BY');
        expect($wherePos)->toBeLessThan($groupPos, 'WHERE should come before GROUP BY');

        // HAVING should not appear (no aggregate conditions added)
        expect($sql)->not->toContain('HAVING');
    });

    it('allows aggregate conditions in HAVING clause', function () {
        // Create a select with both date range (WHERE) and aggregate (HAVING) conditions
        $select = $this->adapter->select();
        $select->from(['t' => 'some_table'], [
            'period',
            'store_id',
            'orders_count' => new Maho\Db\Expr('COUNT(*)'),
        ]);

        // Date range condition goes in WHERE on source column
        $select->where('t.created_at >= ?', '2025-01-01');
        $select->where('t.created_at <= ?', '2025-01-02');

        // Aggregate condition goes in HAVING (this is correct usage)
        $select->having('COUNT(*) > 0');

        $select->group(['period', 'store_id']);

        // Get the full SQL to verify structure
        $sql = (string) $select;

        // Both WHERE and HAVING should be present
        expect($sql)->toContain('WHERE');
        expect($sql)->toContain('HAVING');

        // Date condition in WHERE
        expect($sql)->toContain('created_at');

        // Aggregate condition in HAVING
        expect($sql)->toContain('COUNT(*) > 0');

        // Verify correct order: WHERE before GROUP BY before HAVING
        $wherePos = strpos($sql, 'WHERE');
        $groupPos = strpos($sql, 'GROUP BY');
        $havingPos = strpos($sql, 'HAVING');

        expect($wherePos)->toBeLessThan($groupPos, 'WHERE should come before GROUP BY');
        expect($groupPos)->toBeLessThan($havingPos, 'GROUP BY should come before HAVING');
    });

    it('generates valid SQL with WHERE clause for date filtering', function () {
        $select = $this->adapter->select();
        $select->from(['o' => 'sales_order'], [
            'period' => new Maho\Db\Expr('DATE(created_at)'),
            'store_id',
            'orders_count' => new Maho\Db\Expr('COUNT(*)'),
            'total_amount' => new Maho\Db\Expr('SUM(grand_total)'),
        ]);

        // Date condition in WHERE on source column (the fix)
        $select->where('o.created_at >= ?', '2025-01-01');
        $select->where('o.created_at <= ?', '2025-01-31');

        $select->group([new Maho\Db\Expr('DATE(created_at)'), 'store_id']);

        $sql = (string) $select;

        // Verify SQL structure
        expect($sql)->toContain('WHERE');
        expect($sql)->toContain('created_at >=');
        expect($sql)->toContain('GROUP BY');

        // The date conditions should appear BEFORE GROUP BY in the SQL
        $wherePos = strpos($sql, 'WHERE');
        $groupPos = strpos($sql, 'GROUP BY');
        expect($wherePos)->toBeLessThan($groupPos, 'WHERE should come before GROUP BY');
    });
});

describe('Report Resource uses direct date filtering', function () {
    it('Order Createdat report uses direct date filtering in WHERE', function () {
        $reportResource = Mage::getResourceModel('sales/report_order_createdat');

        // Use reflection to check the source file
        $reflection = new ReflectionClass($reportResource);
        $sourceCode = file_get_contents($reflection->getFileName());

        // The fix: should filter by source date column directly
        expect($sourceCode)->toContain("->where('o.' . \$aggregationField . ' >= ?', \$from)");
        expect($sourceCode)->toContain("->where('o.' . \$aggregationField . ' <= ?', \$to)");

        // Should NOT use _makeConditionFromDateRangeSelect for the first query on source table
        // (it's still used for the second query on aggregated table where period is a real column)
    });

    it('Bestsellers report uses direct date filtering in WHERE', function () {
        $reportResource = Mage::getResourceModel('sales/report_bestsellers');
        $reflection = new ReflectionClass($reportResource);
        $sourceCode = file_get_contents($reflection->getFileName());

        expect($sourceCode)->toContain("->where('source_table.created_at >= ?', \$from)");
        expect($sourceCode)->toContain("->where('source_table.created_at <= ?', \$to)");
    });

    it('Invoiced report uses direct date filtering in WHERE', function () {
        $reportResource = Mage::getResourceModel('sales/report_invoiced');
        $reflection = new ReflectionClass($reportResource);
        $sourceCode = file_get_contents($reflection->getFileName());

        // Should have direct date filtering in WHERE
        expect($sourceCode)->toContain("->where('source_table.created_at >= ?', \$from)");
        expect($sourceCode)->toContain("->where('created_at >= ?', \$from)");
    });

    it('Refunded report uses direct date filtering in WHERE', function () {
        $reportResource = Mage::getResourceModel('sales/report_refunded');
        $reflection = new ReflectionClass($reportResource);
        $sourceCode = file_get_contents($reflection->getFileName());

        expect($sourceCode)->toContain("->where('created_at >= ?', \$from)");
        expect($sourceCode)->toContain("->where('source_table.created_at >= ?', \$from)");
    });

    it('Shipping report uses direct date filtering in WHERE', function () {
        $reportResource = Mage::getResourceModel('sales/report_shipping');
        $reflection = new ReflectionClass($reportResource);
        $sourceCode = file_get_contents($reflection->getFileName());

        expect($sourceCode)->toContain("->where('created_at >= ?', \$from)");
        expect($sourceCode)->toContain("->where('source_table.created_at >= ?', \$from)");
    });

    it('Tax report uses direct date filtering in WHERE', function () {
        $reportResource = Mage::getResourceModel('tax/report_tax_createdat');
        $reflection = new ReflectionClass($reportResource);
        $sourceCode = file_get_contents($reflection->getFileName());

        expect($sourceCode)->toContain("->where('e.' . \$aggregationField . ' >= ?', \$from)");
        expect($sourceCode)->toContain("->where('e.' . \$aggregationField . ' <= ?', \$to)");
    });

    it('SalesRule report uses direct date filtering in WHERE', function () {
        $reportResource = Mage::getResourceModel('salesrule/report_rule_createdat');
        $reflection = new ReflectionClass($reportResource);
        $sourceCode = file_get_contents($reflection->getFileName());

        expect($sourceCode)->toContain("->where('source_table.' . \$aggregationField . ' >= ?', \$from)");
        expect($sourceCode)->toContain("->where('source_table.' . \$aggregationField . ' <= ?', \$to)");
    });

    it('Product Viewed report uses direct date filtering in WHERE', function () {
        $reportResource = Mage::getResourceModel('reports/report_product_viewed');
        $reflection = new ReflectionClass($reportResource);
        $sourceCode = file_get_contents($reflection->getFileName());

        expect($sourceCode)->toContain("->where('source_table.logged_at >= ?', \$from)");
        expect($sourceCode)->toContain("->where('source_table.logged_at <= ?', \$to)");
    });
});

describe('_makeConditionFromDateRangeSelect uses IN instead of LIKE', function () {
    it('generates IN clause instead of multiple LIKE conditions', function () {
        $reflection = new ReflectionClass(Mage_Reports_Model_Resource_Report_Abstract::class);
        $sourceCode = file_get_contents($reflection->getFileName());

        // Should use IN clause for better performance and cross-database compatibility
        expect($sourceCode)->toContain('IN (?)');

        // Should NOT use LIKE for date conditions
        expect($sourceCode)->not->toContain("['like' => \$date]");
    });
});
