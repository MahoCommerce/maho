<?php

/**
 * Maho
 *
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

use Maho\Db\Schema\Applier;

uses(Tests\MahoBackendTestCase::class);

describe('Applier::destructiveStatements', function () {
    it('flags top-level destructive statements', function () {
        $sql = [
            'DROP TABLE foo',
            'TRUNCATE TABLE foo',
            'RENAME TABLE foo TO bar',
        ];
        expect(Applier::destructiveStatements($sql))->toBe($sql);
    });

    it('passes pure CREATE and additive ALTER statements', function () {
        $sql = [
            'CREATE TABLE foo (id INT)',
            'ALTER TABLE `foo` ADD COLUMN a INT',
            'ALTER TABLE `foo` ADD CONSTRAINT fk FOREIGN KEY (a) REFERENCES bar (id)',
            'ALTER TABLE `foo` ADD PRIMARY KEY (a, change_log)',
        ];
        expect(Applier::destructiveStatements($sql))->toBe([]);
    });

    it('flags a leading-ADD ALTER that also drops a column', function () {
        // Doctrine's MySQL platform collapses alterations into one comma-separated
        // statement, so the destructive DROP trails a benign ADD. The guard must
        // still catch it.
        $sql = ['ALTER TABLE `foo` ADD COLUMN a INT, DROP COLUMN b'];
        expect(Applier::destructiveStatements($sql))->toBe($sql);
    });

    it('flags trailing CHANGE / MODIFY / RENAME clauses in a combined ALTER', function () {
        expect(Applier::destructiveStatements(['ALTER TABLE `foo` ADD a INT, CHANGE b c INT']))
            ->toBe(['ALTER TABLE `foo` ADD a INT, CHANGE b c INT']);
        expect(Applier::destructiveStatements(['ALTER TABLE `foo` ADD a INT, MODIFY b BIGINT']))
            ->toBe(['ALTER TABLE `foo` ADD a INT, MODIFY b BIGINT']);
        expect(Applier::destructiveStatements(['ALTER TABLE `foo` ADD a INT, RENAME COLUMN b TO c']))
            ->toBe(['ALTER TABLE `foo` ADD a INT, RENAME COLUMN b TO c']);
    });

    it('flags a first-clause DROP and handles schema-qualified names', function () {
        $sql = [
            'ALTER TABLE `foo` DROP COLUMN b',
            'ALTER TABLE foo.bar DROP COLUMN b',
        ];
        expect(Applier::destructiveStatements($sql))->toBe($sql);
    });

    it('does not match destructive verbs that are only substrings of identifiers', function () {
        // change_log / modify_date appear inside a parenthesized column list, not
        // as ALTER clauses; the word boundary keeps them from matching.
        $sql = [
            'ALTER TABLE `foo` ADD INDEX idx (a, change_log)',
            'ALTER TABLE `foo` ADD INDEX idx (a, modify_date)',
        ];
        expect(Applier::destructiveStatements($sql))->toBe([]);
    });
});
