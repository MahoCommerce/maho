<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

uses(Tests\MahoBackendTestCase::class);

describe('Core Helper Validation Methods', function () {
    beforeEach(function () {
        $this->helper = Mage::helper('core');
    });

    describe('isValidEmail', function () {
        it('returns true for valid emails', function () {
            expect($this->helper->isValidEmail('test@example.com'))->toBeTrue();
            expect($this->helper->isValidEmail('user.name@domain.org'))->toBeTrue();
            expect($this->helper->isValidEmail('user+tag@example.co.uk'))->toBeTrue();
        });

        it('returns false for invalid emails', function () {
            expect($this->helper->isValidEmail('invalid-email'))->toBeFalse();
            expect($this->helper->isValidEmail('test@'))->toBeFalse();
            expect($this->helper->isValidEmail('@example.com'))->toBeFalse();
        });
    });

    describe('isValidNotBlank', function () {
        it('returns true for non-blank values', function () {
            expect($this->helper->isValidNotBlank('test'))->toBeTrue();
            expect($this->helper->isValidNotBlank('0'))->toBeTrue();
            expect($this->helper->isValidNotBlank(123))->toBeTrue();
            expect($this->helper->isValidNotBlank(' value '))->toBeTrue();
        });

        it('returns false for blank values', function () {
            expect($this->helper->isValidNotBlank(''))->toBeFalse();
            expect($this->helper->isValidNotBlank(null))->toBeFalse();
            expect($this->helper->isValidNotBlank(false))->toBeFalse();
            expect($this->helper->isValidNotBlank([]))->toBeFalse();
        });
    });

    describe('isValidRegex', function () {
        it('returns true for values matching regex patterns', function () {
            expect($this->helper->isValidRegex('test123', '/^[a-z0-9]+$/'))->toBeTrue();
            expect($this->helper->isValidRegex('admin/user', '/^[-_a-zA-Z0-9]+\/[-_a-zA-Z0-9\/]+$/'))->toBeTrue();
            expect($this->helper->isValidRegex('variable_name', '/^[-_a-zA-Z0-9\/]*$/'))->toBeTrue();
        });

        it('returns false for values not matching regex patterns', function () {
            expect($this->helper->isValidRegex('Test123', '/^[a-z0-9]+$/'))->toBeFalse();
            expect($this->helper->isValidRegex('invalid!name', '/^[-_a-zA-Z0-9]+\/[-_a-zA-Z0-9\/]+$/'))->toBeFalse();
            expect($this->helper->isValidRegex('invalid@name', '/^[-_a-zA-Z0-9\/]*$/'))->toBeFalse();
        });
    });

    describe('isValidLength', function () {
        it('validates minimum length correctly', function () {
            expect($this->helper->isValidLength('test', 3))->toBeTrue();
            expect($this->helper->isValidLength('test', 4))->toBeTrue();
            expect($this->helper->isValidLength('te', 3))->toBeFalse();
        });

        it('validates maximum length correctly', function () {
            expect($this->helper->isValidLength('test', null, 5))->toBeTrue();
            expect($this->helper->isValidLength('test', null, 4))->toBeTrue();
            expect($this->helper->isValidLength('toolong', null, 4))->toBeFalse();
        });

        it('validates length range correctly', function () {
            expect($this->helper->isValidLength('test', 3, 5))->toBeTrue();
            expect($this->helper->isValidLength('te', 3, 5))->toBeFalse();
            expect($this->helper->isValidLength('toolong', 3, 5))->toBeFalse();
        });
    });

    describe('isValidRange', function () {
        it('validates numeric ranges correctly', function () {
            expect($this->helper->isValidRange(5, 1, 10))->toBeTrue();
            expect($this->helper->isValidRange(1, 1, 10))->toBeTrue();
            expect($this->helper->isValidRange(10, 1, 10))->toBeTrue();
            expect($this->helper->isValidRange(0, 1, 10))->toBeFalse();
            expect($this->helper->isValidRange(11, 1, 10))->toBeFalse();
        });

        it('validates float ranges correctly', function () {
            expect($this->helper->isValidRange(5.5, 1.0, 10.0))->toBeTrue();
            expect($this->helper->isValidRange(0.5, 1.0, 10.0))->toBeFalse();
            expect($this->helper->isValidRange(10.5, 1.0, 10.0))->toBeFalse();
        });
    });

    describe('isValidUrl', function () {
        it('returns true for valid URLs', function () {
            expect($this->helper->isValidUrl('https://example.com'))->toBeTrue();
            expect($this->helper->isValidUrl('http://test.org/path'))->toBeTrue();
        });

        it('returns false for invalid URLs', function () {
            expect($this->helper->isValidUrl('not-a-url'))->toBeFalse();
            expect($this->helper->isValidUrl('://invalid'))->toBeFalse();
        });
    });

    describe('isValidDate', function () {
        it('returns true for valid date formats', function () {
            expect($this->helper->isValidDate('2025-01-15'))->toBeTrue();
            expect($this->helper->isValidDate('2025-12-31'))->toBeTrue();
        });

        it('returns false for invalid date formats', function () {
            expect($this->helper->isValidDate('invalid-date'))->toBeFalse();
            expect($this->helper->isValidDate('2025-13-01'))->toBeFalse();
            expect($this->helper->isValidDate('2025-02-30'))->toBeFalse();
        });
    });

    describe('isValidDateTime', function () {
        it('returns true for valid datetime formats', function () {
            expect($this->helper->isValidDateTime('2025-01-15 10:30:00'))->toBeTrue();
            expect($this->helper->isValidDateTime('2025-12-31 23:59:59'))->toBeTrue();
        });

        it('returns false for invalid datetime formats', function () {
            expect($this->helper->isValidDateTime('invalid-datetime'))->toBeFalse();
            expect($this->helper->isValidDateTime('2025-01-15 25:00:00'))->toBeFalse();
        });
    });
});
