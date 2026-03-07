<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

uses(Tests\MahoBackendTestCase::class);

describe('Locale::date() DateTime handling', function () {
    beforeEach(function () {
        $this->locale = Mage::app()->getLocale();
    });

    it('handles null input and returns current date', function () {
        $result = $this->locale->date();

        expect($result)->toBeInstanceOf(DateTime::class);
    });

    it('handles string date input', function () {
        $result = $this->locale->date('2025-01-15 10:30:00');

        expect($result)->toBeInstanceOf(DateTime::class);
        expect($result->format('Y-m-d'))->toBe('2025-01-15');
    });

    it('handles integer timestamp input', function () {
        $timestamp = strtotime('2025-06-15 12:00:00');
        $result = $this->locale->date($timestamp);

        expect($result)->toBeInstanceOf(DateTime::class);
        expect($result->format('Y-m-d'))->toBe('2025-06-15');
    });

    it('handles DateTime object input without format parameter', function () {
        $inputDate = new DateTime('2025-03-20 15:45:00');
        $result = $this->locale->date($inputDate);

        expect($result)->toBeInstanceOf(DateTime::class);
        expect($result->format('Y-m-d'))->toBe('2025-03-20');
    });

    it('handles DateTime object input with format parameter', function () {
        // This was the bug - passing DateTime with $part caused TypeError
        $inputDate = new DateTime('2025-03-20 15:45:00');
        $result = $this->locale->date($inputDate, 'Y-m-d H:i:s');

        expect($result)->toBeInstanceOf(DateTime::class);
        expect($result->format('Y-m-d'))->toBe('2025-03-20');
    });

    it('handles DateTimeImmutable object input', function () {
        $inputDate = new DateTimeImmutable('2025-04-10 09:30:00');
        $result = $this->locale->date($inputDate);

        expect($result)->toBeInstanceOf(DateTime::class);
        expect($result->format('Y-m-d'))->toBe('2025-04-10');
    });

    it('handles DateTimeImmutable object input with format parameter', function () {
        $inputDate = new DateTimeImmutable('2025-04-10 09:30:00');
        $result = $this->locale->date($inputDate, 'Y-m-d H:i:s');

        expect($result)->toBeInstanceOf(DateTime::class);
        expect($result->format('Y-m-d'))->toBe('2025-04-10');
    });

    it('does not modify the original DateTime object', function () {
        $inputDate = new DateTime('2025-05-25 14:00:00', new DateTimeZone('UTC'));
        $originalTimestamp = $inputDate->getTimestamp();

        $this->locale->date($inputDate, null, null, true);

        // Original should be unchanged
        expect($inputDate->getTimestamp())->toBe($originalTimestamp);
    });

    it('handles string date with format parameter', function () {
        $result = $this->locale->date('15-03-2025', 'd-m-Y');

        expect($result)->toBeInstanceOf(DateTime::class);
        expect($result->format('Y-m-d'))->toBe('2025-03-15');
    });
});

describe('Locale::storeDate() DateTime handling', function () {
    beforeEach(function () {
        $this->locale = Mage::app()->getLocale();
    });

    it('handles DateTime object input', function () {
        $inputDate = new DateTime('2025-01-15 10:30:00', new DateTimeZone('UTC'));
        $result = $this->locale->storeDate(null, $inputDate, true);

        expect($result)->toBeInstanceOf(DateTime::class);
    });

    it('handles DateTimeImmutable object input', function () {
        $inputDate = new DateTimeImmutable('2025-01-15 10:30:00', new DateTimeZone('UTC'));
        $result = $this->locale->storeDate(null, $inputDate, true);

        expect($result)->toBeInstanceOf(DateTime::class);
    });

    it('returns HTML5 date format when requested', function () {
        $inputDate = '2025-01-15 10:30:00';
        $result = $this->locale->storeDate(null, $inputDate, false, 'html5');

        expect($result)->toBe('2025-01-15');
    });

    it('returns HTML5 datetime format when requested', function () {
        $inputDate = new DateTime('2025-01-15 10:30:00', new DateTimeZone('UTC'));
        $result = $this->locale->storeDate(null, $inputDate, true, 'html5');

        expect($result)->toBeString();
        expect($result)->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/');
    });
});
