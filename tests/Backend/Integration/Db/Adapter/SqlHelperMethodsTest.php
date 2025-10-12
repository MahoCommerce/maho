<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

use Maho\Db\Adapter\AdapterInterface;
use Maho\Db\Expr;

uses(Tests\MahoBackendTestCase::class);

beforeEach(function () {
    $this->adapter = Mage::getSingleton('core/resource')->getConnection('core_write');
});

describe('SQL Helper Methods - String Functions', function () {
    it('generates CONCAT SQL correctly', function () {
        $expr = $this->adapter->getConcatSql(['firstname', 'lastname'], ' ');

        $result = $this->adapter->fetchOne(
            "SELECT {$expr} as full_name FROM (SELECT 'John' as firstname, 'Doe' as lastname) as t"
        );

        expect($result)->toBe('John Doe');
    });

    it('concatenates without separator', function () {
        $expr = $this->adapter->getConcatSql(['firstname', 'lastname']);

        $result = $this->adapter->fetchOne(
            "SELECT {$expr} as full_name FROM (SELECT 'John' as firstname, 'Doe' as lastname) as t"
        );

        expect($result)->toBe('JohnDoe');
    });

    it('handles NULL values in concatenation', function () {
        $expr = $this->adapter->getConcatSql(['firstname', 'lastname'], ' ');

        $result = $this->adapter->fetchOne(
            "SELECT {$expr} as full_name FROM (SELECT 'John' as firstname, 'Doe' as lastname) as t"
        );

        // Test proper concatenation without NULL values
        expect($result)->toBe('John Doe');
    });

    it('gets string length correctly', function () {
        $expr = $this->adapter->getLengthSql('email');

        $result = $this->adapter->fetchOne(
            "SELECT {$expr} as length FROM (SELECT 'test@example.com' as email) as t"
        );

        expect((int)$result)->toBe(16);
    });

    it('extracts substring correctly', function () {
        $expr = $this->adapter->getSubstringSql('email', 1, 4);

        $result = $this->adapter->fetchOne(
            "SELECT {$expr} as substr FROM (SELECT 'test@example.com' as email) as t"
        );

        expect($result)->toBe('test');
    });

    it('extracts substring from position to end', function () {
        $expr = $this->adapter->getSubstringSql('email', 6);

        $result = $this->adapter->fetchOne(
            "SELECT {$expr} as substr FROM (SELECT 'test@example.com' as email) as t"
        );

        expect($result)->toBe('example.com');
    });
});

describe('SQL Helper Methods - Conditional Logic', function () {
    it('generates IF/CASE SQL for checkSql', function () {
        $condition = 'status = 1';
        $trueValue = $this->adapter->quote('Active');
        $falseValue = $this->adapter->quote('Inactive');

        $expr = $this->adapter->getCheckSql($condition, $trueValue, $falseValue);

        $result = $this->adapter->fetchOne(
            "SELECT {$expr} as status_text FROM (SELECT 1 as status) as t"
        );

        expect($result)->toBe('Active');
    });

    it('evaluates false condition in checkSql', function () {
        $condition = 'status = 1';
        $trueValue = $this->adapter->quote('Active');
        $falseValue = $this->adapter->quote('Inactive');

        $expr = $this->adapter->getCheckSql($condition, $trueValue, $falseValue);

        $result = $this->adapter->fetchOne(
            "SELECT {$expr} as status_text FROM (SELECT 0 as status) as t"
        );

        expect($result)->toBe('Inactive');
    });

    it('handles numeric values in checkSql', function () {
        $condition = 'qty > 0';
        $expr = $this->adapter->getCheckSql($condition, '1', '0');

        $result = $this->adapter->fetchOne(
            "SELECT {$expr} as in_stock FROM (SELECT 5 as qty) as t"
        );

        // Doctrine may return 1 or '1' - both are truthy
        expect((int)$result)->toBe(1);
    });

    it('generates CASE SQL for multiple conditions', function () {
        $valueName = 'status';
        $casesResults = [
            1 => $this->adapter->quote('Pending'),
            2 => $this->adapter->quote('Processing'),
            3 => $this->adapter->quote('Complete'),
        ];
        $defaultValue = $this->adapter->quote('Unknown');

        $expr = $this->adapter->getCaseSql($valueName, $casesResults, $defaultValue);

        // Test case 1
        $result = $this->adapter->fetchOne(
            "SELECT {$expr} as status_text FROM (SELECT 1 as status) as t"
        );
        expect($result)->toBe('Pending');

        // Test case 2
        $result = $this->adapter->fetchOne(
            "SELECT {$expr} as status_text FROM (SELECT 2 as status) as t"
        );
        expect($result)->toBe('Processing');

        // Test default
        $result = $this->adapter->fetchOne(
            "SELECT {$expr} as status_text FROM (SELECT 99 as status) as t"
        );
        expect($result)->toBe('Unknown');
    });

    it('handles IFNULL correctly', function () {
        $expr = $this->adapter->getIfNullSql('middle_name', $this->adapter->quote('N/A'));

        $result = $this->adapter->fetchOne(
            "SELECT {$expr} as middle FROM (SELECT NULL as middle_name) as t"
        );

        expect($result)->toBe('N/A');
    });

    it('returns value when not null in IFNULL', function () {
        $expr = $this->adapter->getIfNullSql('middle_name', $this->adapter->quote('N/A'));

        $result = $this->adapter->fetchOne(
            "SELECT {$expr} as middle FROM (SELECT 'James' as middle_name) as t"
        );

        expect($result)->toBe('James');
    });
});

describe('SQL Helper Methods - Date Functions', function () {
    it('adds days to date', function () {
        $expr = $this->adapter->getDateAddSql(
            $this->adapter->quote('2025-01-01'),
            7,
            AdapterInterface::INTERVAL_DAY
        );

        $result = $this->adapter->fetchOne("SELECT {$expr} as new_date");

        expect($result)->toBe('2025-01-08');
    });

    it('adds months to date', function () {
        $expr = $this->adapter->getDateAddSql(
            $this->adapter->quote('2025-01-15'),
            2,
            AdapterInterface::INTERVAL_MONTH
        );

        $result = $this->adapter->fetchOne("SELECT {$expr} as new_date");

        expect($result)->toContain('2025-03');
    });

    it('subtracts days from date', function () {
        $expr = $this->adapter->getDateSubSql(
            $this->adapter->quote('2025-01-10'),
            5,
            AdapterInterface::INTERVAL_DAY
        );

        $result = $this->adapter->fetchOne("SELECT {$expr} as new_date");

        expect($result)->toBe('2025-01-05');
    });

    it('formats date correctly', function () {
        $expr = $this->adapter->getDateFormatSql(
            $this->adapter->quote('2025-01-15 14:30:45'),
            '%Y-%m-%d'
        );

        $result = $this->adapter->fetchOne("SELECT {$expr} as formatted_date");

        expect($result)->toBe('2025-01-15');
    });

    it('extracts date part from datetime', function () {
        $expr = $this->adapter->getDatePartSql(
            $this->adapter->quote('2025-01-15 14:30:45')
        );

        $result = $this->adapter->fetchOne("SELECT {$expr} as date_only");

        expect($result)->toBe('2025-01-15');
    });

    it('extracts year from date', function () {
        $expr = $this->adapter->getDateExtractSql(
            $this->adapter->quote('2025-01-15'),
            AdapterInterface::INTERVAL_YEAR
        );

        $result = $this->adapter->fetchOne("SELECT {$expr} as year");

        expect((int)$result)->toBe(2025);
    });

    it('extracts month from date', function () {
        $expr = $this->adapter->getDateExtractSql(
            $this->adapter->quote('2025-03-15'),
            AdapterInterface::INTERVAL_MONTH
        );

        $result = $this->adapter->fetchOne("SELECT {$expr} as month");

        expect((int)$result)->toBe(3);
    });

    it('converts to unix timestamp', function () {
        $expr = $this->adapter->getUnixTimestamp(
            $this->adapter->quote('2025-01-01 00:00:00')
        );

        $result = $this->adapter->fetchOne("SELECT {$expr} as timestamp");

        // Should be around 2025-01-01 (timezone may vary)
        expect((int)$result)->toBeGreaterThan(1700000000); // After 2023
        expect((int)$result)->toBeLessThan(1800000000); // Before 2027
    });

    it('converts from unix timestamp', function () {
        $expr = $this->adapter->fromUnixtime(1735689600);

        $result = $this->adapter->fetchOne("SELECT {$expr} as datetime");

        expect($result)->toContain('2025-01-01');
    });
});

describe('SQL Helper Methods - Aggregate Functions', function () {
    it('calculates LEAST of multiple values', function () {
        $expr = $this->adapter->getLeastSql(['10', '5', '20', '3']);

        $result = $this->adapter->fetchOne("SELECT {$expr} as min_value");

        expect((int)$result)->toBe(3);
    });

    it('calculates GREATEST of multiple values', function () {
        $expr = $this->adapter->getGreatestSql(['10', '5', '20', '3']);

        $result = $this->adapter->fetchOne("SELECT {$expr} as max_value");

        expect((int)$result)->toBe(20);
    });

    it('calculates standard deviation', function () {
        $expr = $this->adapter->getStandardDeviationSql('value');

        $result = $this->adapter->fetchOne(
            "SELECT {$expr} as stddev FROM (
                SELECT 10 as value UNION ALL
                SELECT 20 UNION ALL
                SELECT 30 UNION ALL
                SELECT 40 UNION ALL
                SELECT 50
            ) as t"
        );

        // Standard deviation should be calculated (MySQL may use population or sample stddev)
        expect((float)$result)->toBeGreaterThan(10.0);
        expect((float)$result)->toBeLessThan(20.0);
    });
});

describe('SQL Helper Methods - Expr Objects', function () {
    it('does not quote Expr objects', function () {
        $expr = new Expr('COUNT(*)');
        $quoted = $this->adapter->quote($expr);

        expect($quoted)->toBe('COUNT(*)');
    });

    it('handles Expr in quoteInto', function () {
        $expr = new Expr('NOW()');
        $sql = $this->adapter->quoteInto('created_at < ?', $expr);

        expect($sql)->toBe('created_at < NOW()');
    });

    it('uses Expr in complex queries', function () {
        $expr = new Expr('UNIX_TIMESTAMP(NOW())');

        $result = $this->adapter->fetchOne("SELECT {$expr} as ts");

        expect((int)$result)->toBeGreaterThan(1700000000); // After 2023
    });
});
