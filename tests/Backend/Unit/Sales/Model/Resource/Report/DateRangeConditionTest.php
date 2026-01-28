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

        // Simulate what _makeConditionFromDateRangeSelect returns
        $dateCondition = "period IN ('2025-01-01', '2025-01-02', '2025-01-03')";

        // The fix: use where() instead of having() for date conditions
        $select->where($dateCondition);
        $select->group(['period', 'store_id']);

        // Get the full SQL to verify structure
        $sql = (string) $select;

        // Verify date condition is in WHERE clause (appears before GROUP BY)
        expect($sql)->toContain('WHERE');
        expect($sql)->toContain('period IN');

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

        // Date range condition goes in WHERE
        $select->where("period IN ('2025-01-01', '2025-01-02')");

        // Aggregate condition goes in HAVING (this is correct usage)
        $select->having('COUNT(*) > 0');

        $select->group(['period', 'store_id']);

        // Get the full SQL to verify structure
        $sql = (string) $select;

        // Both WHERE and HAVING should be present
        expect($sql)->toContain('WHERE');
        expect($sql)->toContain('HAVING');

        // Date condition in WHERE
        expect($sql)->toContain('period IN');

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

        // Date condition in WHERE (the fix)
        $select->where("DATE(created_at) >= '2025-01-01'");
        $select->where("DATE(created_at) <= '2025-01-31'");

        $select->group([new Maho\Db\Expr('DATE(created_at)'), 'store_id']);

        $sql = (string) $select;

        // Verify SQL structure
        expect($sql)->toContain('WHERE');
        expect($sql)->toContain("DATE(created_at) >= '2025-01-01'");
        expect($sql)->toContain('GROUP BY');

        // The date conditions should appear BEFORE GROUP BY in the SQL
        $wherePos = strpos($sql, 'WHERE');
        $groupPos = strpos($sql, 'GROUP BY');
        expect($wherePos)->toBeLessThan($groupPos, 'WHERE should come before GROUP BY');
    });
});

describe('Report Resource _makeConditionFromDateRangeSelect Usage', function () {
    it('Order Createdat report uses where() for date range', function () {
        $reportResource = Mage::getResourceModel('sales/report_order_createdat');

        // Use reflection to check the aggregate method's query building
        $reflection = new ReflectionClass($reportResource);

        // Read the source file to verify the fix is in place
        $sourceFile = $reflection->getFileName();
        $sourceCode = file_get_contents($sourceFile);

        // The fix: should use ->where() not ->having() for _makeConditionFromDateRangeSelect
        expect($sourceCode)->toContain('$select->where($this->_makeConditionFromDateRangeSelect($subSelect, \'period\'))');
        expect($sourceCode)->not->toContain('$select->having($this->_makeConditionFromDateRangeSelect($subSelect, \'period\'))');
    });

    it('Bestsellers report uses where() for date range', function () {
        $reportResource = Mage::getResourceModel('sales/report_bestsellers');
        $reflection = new ReflectionClass($reportResource);
        $sourceCode = file_get_contents($reflection->getFileName());

        expect($sourceCode)->toContain('$select->where($this->_makeConditionFromDateRangeSelect($subSelect, \'period\'))');
        expect($sourceCode)->not->toContain('$select->having($this->_makeConditionFromDateRangeSelect($subSelect, \'period\'))');
    });

    it('Invoiced report uses where() for date range', function () {
        $reportResource = Mage::getResourceModel('sales/report_invoiced');
        $reflection = new ReflectionClass($reportResource);
        $sourceCode = file_get_contents($reflection->getFileName());

        // Should have where() calls, not having() calls for date range
        $whereCount = substr_count($sourceCode, '$select->where($this->_makeConditionFromDateRangeSelect');
        $havingCount = substr_count($sourceCode, '$select->having($this->_makeConditionFromDateRangeSelect');

        expect($whereCount)->toBeGreaterThan(0, 'Should use where() for date range conditions');
        expect($havingCount)->toBe(0, 'Should NOT use having() for date range conditions');
    });

    it('Refunded report uses where() for date range', function () {
        $reportResource = Mage::getResourceModel('sales/report_refunded');
        $reflection = new ReflectionClass($reportResource);
        $sourceCode = file_get_contents($reflection->getFileName());

        $whereCount = substr_count($sourceCode, '$select->where($this->_makeConditionFromDateRangeSelect');
        $havingCount = substr_count($sourceCode, '$select->having($this->_makeConditionFromDateRangeSelect');

        expect($whereCount)->toBeGreaterThan(0);
        expect($havingCount)->toBe(0);
    });

    it('Shipping report uses where() for date range', function () {
        $reportResource = Mage::getResourceModel('sales/report_shipping');
        $reflection = new ReflectionClass($reportResource);
        $sourceCode = file_get_contents($reflection->getFileName());

        $whereCount = substr_count($sourceCode, '$select->where($this->_makeConditionFromDateRangeSelect');
        $havingCount = substr_count($sourceCode, '$select->having($this->_makeConditionFromDateRangeSelect');

        expect($whereCount)->toBeGreaterThan(0);
        expect($havingCount)->toBe(0);
    });

    it('Tax report uses where() for date range', function () {
        $reportResource = Mage::getResourceModel('tax/report_tax_createdat');
        $reflection = new ReflectionClass($reportResource);
        $sourceCode = file_get_contents($reflection->getFileName());

        expect($sourceCode)->toContain('$select->where($this->_makeConditionFromDateRangeSelect($subSelect, \'period\'))');
        expect($sourceCode)->not->toContain('$select->having($this->_makeConditionFromDateRangeSelect($subSelect, \'period\'))');
    });

    it('SalesRule report uses where() for date range', function () {
        $reportResource = Mage::getResourceModel('salesrule/report_rule_createdat');
        $reflection = new ReflectionClass($reportResource);
        $sourceCode = file_get_contents($reflection->getFileName());

        expect($sourceCode)->toContain('$select->where($this->_makeConditionFromDateRangeSelect($subSelect, \'period\'))');
        expect($sourceCode)->not->toContain('$select->having($this->_makeConditionFromDateRangeSelect($subSelect, \'period\'))');
    });

    it('Product Viewed report uses where() for date range', function () {
        $reportResource = Mage::getResourceModel('reports/report_product_viewed');
        $reflection = new ReflectionClass($reportResource);
        $sourceCode = file_get_contents($reflection->getFileName());

        // Product Viewed has special handling - date condition should be in where()
        expect($sourceCode)->toContain('$select->where($subSelectWherePart)');
        // And aggregate condition (views_num > 0) should remain in having()
        expect($sourceCode)->toContain('$select->having($adapter->prepareSqlCondition($viewsNumExpr');
    });
});
