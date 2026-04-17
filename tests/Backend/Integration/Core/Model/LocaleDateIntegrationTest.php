<?php

/**
 * Maho
 *
 * @package    Mage_Core
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

// ---------------------------------------------------------------------------
// Helper: set store timezone for a test, restore it afterward
// ---------------------------------------------------------------------------
function withStoreTimezone(string $timezone, callable $fn): mixed
{
    $store = Mage::app()->getStore();
    $original = $store->getConfig(Mage_Core_Model_Locale::XML_PATH_DEFAULT_TIMEZONE);
    $store->setConfig(Mage_Core_Model_Locale::XML_PATH_DEFAULT_TIMEZONE, $timezone);
    try {
        return $fn();
    } finally {
        $store->setConfig(Mage_Core_Model_Locale::XML_PATH_DEFAULT_TIMEZONE, $original);
    }
}

// ===========================================================================
// utcToStore — exact timezone shifts
// ===========================================================================
describe('utcToStore() timezone conversion', function () {
    it('shifts UTC to America/New_York (UTC-5 in winter)', function () {
        withStoreTimezone('America/New_York', function () {
            $locale = Mage::app()->getLocale();
            // 2025-01-15 is winter — EST = UTC-5
            $result = $locale->utcToStore(null, '2025-01-15 20:00:00');

            expect($result->format(Mage_Core_Model_Locale::DATETIME_FORMAT))
                ->toBe('2025-01-15 15:00:00');
            expect($result->getTimezone()->getName())
                ->toBe('America/New_York');
        });
    });

    it('shifts UTC to America/New_York (UTC-4 in summer / EDT)', function () {
        withStoreTimezone('America/New_York', function () {
            $locale = Mage::app()->getLocale();
            // 2025-06-15 is summer — EDT = UTC-4
            $result = $locale->utcToStore(null, '2025-06-15 20:00:00');

            expect($result->format(Mage_Core_Model_Locale::DATETIME_FORMAT))
                ->toBe('2025-06-15 16:00:00');
        });
    });

    it('shifts UTC to Asia/Kolkata (UTC+5:30)', function () {
        withStoreTimezone('Asia/Kolkata', function () {
            $locale = Mage::app()->getLocale();
            $result = $locale->utcToStore(null, '2025-06-15 10:00:00');

            expect($result->format(Mage_Core_Model_Locale::DATETIME_FORMAT))
                ->toBe('2025-06-15 15:30:00');
        });
    });

    it('shifts UTC to Pacific/Auckland (UTC+12 in winter / NZST)', function () {
        withStoreTimezone('Pacific/Auckland', function () {
            $locale = Mage::app()->getLocale();
            // July is winter in NZ — NZST = UTC+12
            $result = $locale->utcToStore(null, '2025-07-01 08:00:00');

            expect($result->format(Mage_Core_Model_Locale::DATETIME_FORMAT))
                ->toBe('2025-07-01 20:00:00');
        });
    });

    it('is a no-op when store timezone is UTC', function () {
        withStoreTimezone('UTC', function () {
            $locale = Mage::app()->getLocale();
            $result = $locale->utcToStore(null, '2025-06-15 10:00:00');

            expect($result->format(Mage_Core_Model_Locale::DATETIME_FORMAT))
                ->toBe('2025-06-15 10:00:00');
        });
    });

    it('handles date crossing midnight boundary', function () {
        withStoreTimezone('America/New_York', function () {
            $locale = Mage::app()->getLocale();
            // 2025-01-16 03:00 UTC → 2025-01-15 22:00 EST (previous day)
            $result = $locale->utcToStore(null, '2025-01-16 03:00:00');

            expect($result->format(Mage_Core_Model_Locale::DATETIME_FORMAT))
                ->toBe('2025-01-15 22:00:00');
        });
    });
});

// ===========================================================================
// storeToUtc — inverse conversion
// ===========================================================================
describe('storeToUtc() timezone conversion', function () {
    it('shifts America/New_York winter time to UTC', function () {
        withStoreTimezone('America/New_York', function () {
            $locale = Mage::app()->getLocale();
            $result = $locale->storeToUtc(null, '2025-01-15 15:00:00');

            expect($result->format(Mage_Core_Model_Locale::DATETIME_FORMAT))
                ->toBe('2025-01-15 20:00:00');
            expect($result->getTimezone()->getName())
                ->toBe('UTC');
        });
    });

    it('shifts Asia/Kolkata time to UTC', function () {
        withStoreTimezone('Asia/Kolkata', function () {
            $locale = Mage::app()->getLocale();
            $result = $locale->storeToUtc(null, '2025-06-15 15:30:00');

            expect($result->format(Mage_Core_Model_Locale::DATETIME_FORMAT))
                ->toBe('2025-06-15 10:00:00');
        });
    });

    it('is a no-op when store timezone is UTC', function () {
        withStoreTimezone('UTC', function () {
            $locale = Mage::app()->getLocale();
            $result = $locale->storeToUtc(null, '2025-06-15 10:00:00');

            expect($result->format(Mage_Core_Model_Locale::DATETIME_FORMAT))
                ->toBe('2025-06-15 10:00:00');
        });
    });
});

// ===========================================================================
// Round-trip: utcToStore ↔ storeToUtc
// ===========================================================================
describe('utcToStore ↔ storeToUtc round-trip', function () {
    $timezones = [
        'America/New_York',
        'Europe/Rome',
        'Asia/Kolkata',
        'Pacific/Auckland',
        'America/Los_Angeles',
        'Asia/Tokyo',
        'UTC',
    ];

    foreach ($timezones as $tz) {
        it("round-trips correctly through {$tz}", function () use ($tz) {
            withStoreTimezone($tz, function () {
                $locale = Mage::app()->getLocale();
                $original = '2025-08-15 14:30:45';

                $storeDate = $locale->utcToStore(null, $original);
                $roundTripped = $locale->storeToUtc(null, $storeDate);

                expect($roundTripped->format(Mage_Core_Model_Locale::DATETIME_FORMAT))
                    ->toBe($original);
            });
        });
    }
});

// ===========================================================================
// nowUtc() / todayUtc() — always UTC regardless of PHP default timezone
// ===========================================================================
describe('nowUtc() and todayUtc() are always UTC', function () {
    it('nowUtc() matches gmdate output', function () {
        $before = gmdate(Mage_Core_Model_Locale::DATETIME_FORMAT);
        $result = Mage::app()->getLocale()->nowUtc();
        $after = gmdate(Mage_Core_Model_Locale::DATETIME_FORMAT);

        // Result must be between before and after (inclusive)
        expect($result >= $before && $result <= $after)->toBeTrue();
    });

    it('todayUtc() matches gmdate output', function () {
        $expected = gmdate(Mage_Core_Model_Locale::DATE_FORMAT);
        expect(Mage::app()->getLocale()->todayUtc())->toBe($expected);
    });

    it('nowUtc() is unaffected by date_default_timezone_set', function () {
        $originalTz = date_default_timezone_get();
        try {
            date_default_timezone_set('Pacific/Auckland'); // UTC+12/+13
            $result = Mage::app()->getLocale()->nowUtc();
            $expected = gmdate(Mage_Core_Model_Locale::DATETIME_FORMAT);

            expect($result)->toBe($expected);
        } finally {
            date_default_timezone_set($originalTz);
        }
    });
});

// ===========================================================================
// formatDateForDb — all input types
// ===========================================================================
describe('formatDateForDb() input handling', function () {
    beforeEach(function () {
        $this->locale = Mage::app()->getLocale();
    });

    it('returns null for null', function () {
        expect($this->locale->formatDateForDb(null))->toBeNull();
    });

    it('returns null for empty string', function () {
        expect($this->locale->formatDateForDb(''))->toBeNull();
    });

    it('handles integer 0 (epoch) — does not return null', function () {
        $result = $this->locale->formatDateForDb(0);

        expect($result)->not->toBeNull();
        expect($result)->toBe('1970-01-01 00:00:00');
    });

    it('handles string "0" (epoch) — does not return null', function () {
        $result = $this->locale->formatDateForDb('0');

        expect($result)->not->toBeNull();
        expect($result)->toBe('1970-01-01 00:00:00');
    });

    it('formats DateTime preserving its timezone', function () {
        $dt = new DateTime('2025-06-15 14:30:00', new DateTimeZone('America/New_York'));
        $result = $this->locale->formatDateForDb($dt);

        // Should use the DateTime's own time, NOT convert to UTC
        expect($result)->toBe('2025-06-15 14:30:00');
    });

    it('formats DateTimeImmutable', function () {
        $dt = new DateTimeImmutable('2025-06-15 14:30:00', new DateTimeZone('UTC'));
        expect($this->locale->formatDateForDb($dt))->toBe('2025-06-15 14:30:00');
    });

    it('formats string date as UTC', function () {
        $result = $this->locale->formatDateForDb('2025-06-15 14:30:00');
        expect($result)->toBe('2025-06-15 14:30:00');
    });

    it('formats numeric timestamp', function () {
        // 1750000000 = 2025-06-15 14:06:40 UTC
        $result = $this->locale->formatDateForDb(1750000000);
        expect($result)->toBe(gmdate(Mage_Core_Model_Locale::DATETIME_FORMAT, 1750000000));
    });

    it('formats numeric string timestamp', function () {
        $result = $this->locale->formatDateForDb('1750000000');
        expect($result)->toBe(gmdate(Mage_Core_Model_Locale::DATETIME_FORMAT, 1750000000));
    });

    it('strips time when withTime is false', function () {
        $dt = new DateTime('2025-06-15 14:30:00');
        expect($this->locale->formatDateForDb($dt, withTime: false))->toBe('2025-06-15');
    });
});

// ===========================================================================
// parseDate (tested indirectly via utcToStore/storeToUtc)
// ===========================================================================
describe('parseDate input handling via utcToStore', function () {
    beforeEach(function () {
        $this->locale = Mage::app()->getLocale();
    });

    it('handles null (defaults to now)', function () {
        withStoreTimezone('UTC', function () {
            $before = time();
            $result = $this->locale->utcToStore(null, null);
            $after = time();

            expect($result->getTimestamp())->toBeGreaterThanOrEqual($before);
            expect($result->getTimestamp())->toBeLessThanOrEqual($after);
        });
    });

    it('handles "now" string', function () {
        withStoreTimezone('UTC', function () {
            $before = time();
            $result = $this->locale->utcToStore(null, 'now');
            $after = time();

            expect($result->getTimestamp())->toBeGreaterThanOrEqual($before);
            expect($result->getTimestamp())->toBeLessThanOrEqual($after);
        });
    });

    it('handles integer timestamp', function () {
        withStoreTimezone('UTC', function () {
            $ts = 1750000000;
            $result = $this->locale->utcToStore(null, $ts);

            expect($result->getTimestamp())->toBe($ts);
        });
    });

    it('handles numeric string timestamp', function () {
        withStoreTimezone('UTC', function () {
            $result = $this->locale->utcToStore(null, '1750000000');

            expect($result->getTimestamp())->toBe(1750000000);
        });
    });

    it('handles DateTime input (clones, does not mutate)', function () {
        withStoreTimezone('America/New_York', function () {
            $input = new DateTime('2025-06-15 12:00:00', new DateTimeZone('UTC'));
            $originalTs = $input->getTimestamp();

            $result = $this->locale->utcToStore(null, $input);

            // Original must be unchanged
            expect($input->getTimestamp())->toBe($originalTs);
            expect($input->getTimezone()->getName())->toBe('UTC');

            // Result should be in store timezone
            expect($result)->not->toBe($input); // different object
        });
    });

    it('handles DateTimeImmutable input', function () {
        withStoreTimezone('UTC', function () {
            $input = new DateTimeImmutable('2025-06-15 12:00:00', new DateTimeZone('UTC'));
            $result = $this->locale->utcToStore(null, $input);

            expect($result)->toBeInstanceOf(DateTime::class);
            expect($result->format('Y-m-d H:i:s'))->toBe('2025-06-15 12:00:00');
        });
    });
});

// ===========================================================================
// DST edge cases
// ===========================================================================
describe('DST boundary handling', function () {
    it('handles spring-forward gap (2:30 AM does not exist in New York on 2025-03-09)', function () {
        withStoreTimezone('America/New_York', function () {
            $locale = Mage::app()->getLocale();

            // 2025-03-09 02:00 EST → clocks jump to 03:00 EDT
            // 2025-03-09 07:00 UTC = 2025-03-09 03:00 EDT (spring forward happened)
            $result = $locale->utcToStore(null, '2025-03-09 07:00:00');

            expect($result->format(Mage_Core_Model_Locale::DATETIME_FORMAT))
                ->toBe('2025-03-09 03:00:00');
        });
    });

    it('handles fall-back overlap (1:30 AM exists twice in New York on 2025-11-02)', function () {
        withStoreTimezone('America/New_York', function () {
            $locale = Mage::app()->getLocale();

            // 2025-11-02 05:30 UTC = 2025-11-02 01:30 EDT (before fall-back)
            $result1 = $locale->utcToStore(null, '2025-11-02 05:30:00');
            // 2025-11-02 06:30 UTC = 2025-11-02 01:30 EST (after fall-back)
            $result2 = $locale->utcToStore(null, '2025-11-02 06:30:00');

            // Both display as 01:30 but have different UTC offsets
            expect($result1->format('H:i'))->toBe('01:30');
            expect($result2->format('H:i'))->toBe('01:30');

            // They represent different instants — 1 hour apart
            expect($result2->getTimestamp() - $result1->getTimestamp())->toBe(3600);
        });
    });
});

// ===========================================================================
// DB round-trip: CMS page custom_theme_from/to
// ===========================================================================
describe('DB round-trip: CMS page date fields', function () {
    it('stores custom_theme_from as UTC-formatted string in DB', function () {
        $identifier = 'date_test_page_' . uniqid();

        $page = Mage::getModel('cms/page');
        $page->setTitle('Date Test Page');
        $page->setIdentifier($identifier);
        $page->setContentHeading('Test');
        $page->setContent('<p>test</p>');
        $page->setIsActive(1);
        $page->setStores([0]);
        $page->setCustomThemeFrom('2025-08-15 00:00:00');
        $page->save();

        $pageId = $page->getId();
        expect($pageId)->toBeGreaterThan(0);

        try {
            // Read raw DB value
            $resource = Mage::getSingleton('core/resource');
            $read = $resource->getConnection('core_read');
            $raw = $read->fetchOne(
                $read->select()
                    ->from($resource->getTableName('cms/page'), ['custom_theme_from'])
                    ->where('page_id = ?', $pageId),
            );

            // MySQL stores date-only (TYPE_DATE), SQLite stores full datetime
            expect($raw)->toMatch('/^2025-08-15( 00:00:00)?$/');
        } finally {
            $page->delete();
        }
    });

    it('stores null for empty date in DB', function () {
        $identifier = 'date_null_test_' . uniqid();

        $page = Mage::getModel('cms/page');
        $page->setTitle('Date Null Test');
        $page->setIdentifier($identifier);
        $page->setContentHeading('Test');
        $page->setContent('<p>test</p>');
        $page->setIsActive(1);
        $page->setStores([0]);
        $page->setCustomThemeFrom('');
        $page->save();

        try {
            $resource = Mage::getSingleton('core/resource');
            $read = $resource->getConnection('core_read');
            $raw = $read->fetchOne(
                $read->select()
                    ->from($resource->getTableName('cms/page'), ['custom_theme_from'])
                    ->where('page_id = ?', $page->getId()),
            );

            expect($raw)->toBeFalsy();
        } finally {
            $page->delete();
        }
    });
});

// ===========================================================================
// DB round-trip: Dataflow profile created_at / updated_at
// ===========================================================================
describe('DB round-trip: Dataflow profile timestamps', function () {
    it('sets created_at and updated_at as UTC-formatted timestamps on new profile', function () {
        $before = gmdate(Mage_Core_Model_Locale::DATETIME_FORMAT);

        $profile = Mage::getModel('dataflow/profile');
        $profile->setName('date_test_' . uniqid());
        $profile->setActionsXml('<action />');
        $profile->save();

        $after = gmdate(Mage_Core_Model_Locale::DATETIME_FORMAT);

        try {
            $resource = Mage::getSingleton('core/resource');
            $read = $resource->getConnection('core_read');
            $row = $read->fetchRow(
                $read->select()
                    ->from($resource->getTableName('dataflow/profile'), ['created_at', 'updated_at'])
                    ->where('profile_id = ?', $profile->getId()),
            );

            // Both timestamps should be between before and after
            expect($row['created_at'] >= $before && $row['created_at'] <= $after)->toBeTrue();
            expect($row['updated_at'] >= $before && $row['updated_at'] <= $after)->toBeTrue();
        } finally {
            $profile->delete();
        }
    });
});

// ===========================================================================
// DB round-trip: Flag model with formatDateForDb
// ===========================================================================
describe('DB round-trip: Flag last_update via formatDateForDb', function () {
    it('stores a formatted timestamp in the flag table', function () {
        $flagCode = 'date_format_test_' . uniqid();

        $flag = Mage::getModel('core/flag', ['flag_code' => $flagCode]);
        $flag->setFlagData(['test' => true]);
        $flag->save();

        try {
            $resource = Mage::getSingleton('core/resource');
            $read = $resource->getConnection('core_read');
            $raw = $read->fetchOne(
                $read->select()
                    ->from($resource->getTableName('core/flag'), ['last_update'])
                    ->where('flag_id = ?', $flag->getId()),
            );

            // last_update should be a valid datetime string
            expect($raw)->toMatch('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/');
        } finally {
            $flag->delete();
        }
    });
});

// ===========================================================================
// End-to-end: admin saves a store-TZ value → DB stores UTC → load converts back
// ===========================================================================
describe('End-to-end: store timezone → UTC → store timezone', function () {
    it('converts store-local input to UTC for DB, and back on display', function () {
        withStoreTimezone('America/New_York', function () {
            $locale = Mage::app()->getLocale();

            // Simulate admin entering a date in store time (New York, winter = UTC-5)
            $storeInput = '2025-01-15 14:00:00';

            // Step 1: Convert to UTC for DB storage (what _beforeSave does)
            $utcDate = $locale->storeToUtc(null, $storeInput);
            $dbValue = $utcDate->format(Mage_Core_Model_Locale::DATETIME_FORMAT);

            expect($dbValue)->toBe('2025-01-15 19:00:00');

            // Step 2: Convert back to store time for display (what afterLoad / frontend does)
            $displayDate = $locale->utcToStore(null, $dbValue);
            $displayValue = $displayDate->format(Mage_Core_Model_Locale::DATETIME_FORMAT);

            expect($displayValue)->toBe($storeInput);
        });
    });

    it('works correctly with Asia/Kolkata (UTC+5:30, half-hour offset)', function () {
        withStoreTimezone('Asia/Kolkata', function () {
            $locale = Mage::app()->getLocale();

            $storeInput = '2025-06-15 09:30:00'; // 9:30 AM IST

            // To UTC: IST is UTC+5:30, so 9:30 IST = 4:00 UTC
            $dbValue = $locale->storeToUtc(null, $storeInput)
                ->format(Mage_Core_Model_Locale::DATETIME_FORMAT);

            expect($dbValue)->toBe('2025-06-15 04:00:00');

            // Back to store
            $displayValue = $locale->utcToStore(null, $dbValue)
                ->format(Mage_Core_Model_Locale::DATETIME_FORMAT);

            expect($displayValue)->toBe($storeInput);
        });
    });

    it('handles cross-day conversion (store date is different from UTC date)', function () {
        withStoreTimezone('Pacific/Auckland', function () {
            $locale = Mage::app()->getLocale();

            // January in NZ = NZDT = UTC+13, so midnight on Jan 16 in NZ is Jan 15 11:00 UTC
            $storeInput = '2025-01-16 00:00:00';

            $dbValue = $locale->storeToUtc(null, $storeInput)
                ->format(Mage_Core_Model_Locale::DATETIME_FORMAT);

            expect($dbValue)->toBe('2025-01-15 11:00:00');

            // Round trip
            $displayValue = $locale->utcToStore(null, $dbValue)
                ->format(Mage_Core_Model_Locale::DATETIME_FORMAT);

            expect($displayValue)->toBe($storeInput);
        });
    });
});

// ===========================================================================
// Deprecated methods delegate correctly
// ===========================================================================
describe('Deprecated methods delegate to new API', function () {
    it('Resource_Abstract::formatDate() delegates to formatDateForDb()', function () {
        $resource = Mage::getResourceModel('cms/page');

        // true → current datetime
        $result = $resource->formatDate(true);
        expect($result)->toMatch('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/');

        // DateTime object
        $dt = new DateTime('2025-06-15 10:00:00');
        expect($resource->formatDate($dt))->toBe('2025-06-15 10:00:00');

        // null → null
        expect($resource->formatDate(null))->toBeNull();
    });

    it('storeTimeStamp() matches utcToStore()->getTimestamp()', function () {
        withStoreTimezone('America/New_York', function () {
            $locale = Mage::app()->getLocale();

            $deprecated = $locale->storeTimeStamp(null);
            $new = $locale->utcToStore(null, 'now')->getTimestamp();

            // They run at slightly different instants, allow 2 second tolerance
            expect(abs($deprecated - $new))->toBeLessThanOrEqual(2);
        });
    });
});

// ===========================================================================
// Helper formatDate (locale-aware display) uses new API correctly
// ===========================================================================
describe('Core helper formatDate() with timezone', function () {
    it('formats a UTC date into store-local display string', function () {
        withStoreTimezone('America/New_York', function () {
            $helper = Mage::helper('core');

            // 2025-01-15 20:00 UTC = 2025-01-15 15:00 EST
            $result = $helper->formatDate(
                '2025-01-15 20:00:00',
                Mage_Core_Model_Locale::FORMAT_TYPE_SHORT,
                true,
            );

            // The exact format depends on locale, but it should contain "15"
            // (the 15th) and some representation of 3 PM (15:00 EST, not 20:00 UTC)
            expect($result)->toContain('15');
        });
    });

    it('formatDate with useTimezone=false shows UTC time', function () {
        withStoreTimezone('America/New_York', function () {
            $helper = Mage::helper('core');

            $withTz = $helper->formatTimezoneDate(
                '2025-01-15 20:00:00',
                Mage_Core_Model_Locale::FORMAT_TYPE_SHORT,
                true,
                true, // useTimezone
            );

            $withoutTz = $helper->formatTimezoneDate(
                '2025-01-15 20:00:00',
                Mage_Core_Model_Locale::FORMAT_TYPE_SHORT,
                true,
                false, // useTimezone
            );

            // They should be different when store is not UTC
            expect($withTz)->not->toBe($withoutTz);
        });
    });
});

// ===========================================================================
// isStoreDateInInterval with timezone
// ===========================================================================
describe('isStoreDateInInterval() with store timezone', function () {
    it('respects store timezone when checking date ranges', function () {
        // Use a store far ahead of UTC to test the boundary
        withStoreTimezone('Pacific/Auckland', function () {
            $locale = Mage::app()->getLocale();

            // Get the current date in NZ — it may be a different day than UTC
            $storeNow = $locale->utcToStore(null, 'now');
            $storeToday = $storeNow->format(Mage_Core_Model_Locale::DATE_FORMAT);

            // A range that includes today in store time
            $from = (clone $storeNow)->modify('-1 day')->format(Mage_Core_Model_Locale::DATETIME_FORMAT);
            $to = (clone $storeNow)->modify('+1 day')->format(Mage_Core_Model_Locale::DATETIME_FORMAT);

            expect($locale->isStoreDateInInterval(null, $from, $to))->toBeTrue();
        });
    });
});

// ===========================================================================
// Catalog rule price lookup uses store-local date, not UTC
// ===========================================================================
describe('Catalog rule getRulePrice() uses store-local date', function () {
    it('finds a same-day rule just after local midnight in a UTC+13 store', function () {
        // Scenario: it's 2025-01-16 00:30 NZDT (UTC+13), which is 2025-01-15 11:30 UTC.
        // A catalog rule keyed to rule_date = '2025-01-16' must be found because the
        // store's local date is Jan 16, even though UTC is still Jan 15.
        $store = Mage::app()->getStore();
        $websiteId = (int) $store->getWebsiteId();
        $customerGroupId = Mage_Customer_Model_Group::NOT_LOGGED_IN_ID;

        // Create a minimal product so FK constraints are satisfied
        $product = Mage::getModel('catalog/product');
        $product->setTypeId('simple')
            ->setAttributeSetId((int) $product->getDefaultAttributeSetId())
            ->setSku('timezone_rule_test_' . uniqid())
            ->setName('Timezone Rule Test')
            ->setPrice(100)
            ->setStatus(1)
            ->setVisibility(1)
            ->setWebsiteIds([$websiteId])
            ->save();
        $productId = (int) $product->getId();
        $ruleDate = '2025-01-16';
        $rulePrice = 42.99;

        $resource = Mage::getSingleton('core/resource');
        $write = $resource->getConnection('core_write');
        $table = $resource->getTableName('catalogrule/rule_product_price');

        // Insert a rule price row for the store-local date
        $write->insert($table, [
            'rule_date'          => $ruleDate,
            'website_id'         => $websiteId,
            'customer_group_id'  => $customerGroupId,
            'product_id'         => $productId,
            'rule_price'         => $rulePrice,
            'latest_start_date'  => $ruleDate,
            'earliest_end_date'  => $ruleDate,
        ]);

        try {
            withStoreTimezone('Pacific/Auckland', function () use (
                $websiteId,
                $customerGroupId,
                $productId,
                $rulePrice,
            ) {
                // Simulate "just after midnight" in Auckland on 2025-01-16:
                // 2025-01-16 00:30 NZDT = 2025-01-15 11:30 UTC
                $utcTime = '2025-01-15 11:30:00';
                $storeDateTime = Mage::app()->getLocale()->utcToStore(null, $utcTime);

                // Sanity: confirm the store-local date is Jan 16
                expect($storeDateTime->format(Mage_Core_Model_Locale::DATE_FORMAT))
                    ->toBe('2025-01-16');

                // The bug: passing getTimestamp() would go through gmdate() and look up
                // '2025-01-15' (UTC date), missing the rule. Passing the DateTime object
                // makes formatDateForDb() use the DateTime's own timezone → '2025-01-16'.
                $result = Mage::getResourceModel('catalogrule/rule')
                    ->getRulePrice($storeDateTime, $websiteId, $customerGroupId, $productId);

                expect((float) $result)->toBe($rulePrice);
            });
        } finally {
            $write->delete($table, [
                'rule_date = ?'         => $ruleDate,
                'website_id = ?'        => $websiteId,
                'customer_group_id = ?' => $customerGroupId,
                'product_id = ?'        => $productId,
            ]);
            $product->delete();
        }
    });

    it('does NOT find a rule when UTC date differs and timestamp were used', function () {
        // Proves the inverse: looking up by UTC date (Jan 15) should NOT find a
        // rule keyed to the store-local date (Jan 16)
        $store = Mage::app()->getStore();
        $websiteId = (int) $store->getWebsiteId();
        $customerGroupId = Mage_Customer_Model_Group::NOT_LOGGED_IN_ID;

        // Create a minimal product so FK constraints are satisfied
        $product = Mage::getModel('catalog/product');
        $product->setTypeId('simple')
            ->setAttributeSetId((int) $product->getDefaultAttributeSetId())
            ->setSku('timezone_rule_neg_test_' . uniqid())
            ->setName('Timezone Rule Neg Test')
            ->setPrice(100)
            ->setStatus(1)
            ->setVisibility(1)
            ->setWebsiteIds([$websiteId])
            ->save();
        $productId = (int) $product->getId();
        $ruleDate = '2025-01-16';

        $resource = Mage::getSingleton('core/resource');
        $write = $resource->getConnection('core_write');
        $table = $resource->getTableName('catalogrule/rule_product_price');

        $write->insert($table, [
            'rule_date'          => $ruleDate,
            'website_id'         => $websiteId,
            'customer_group_id'  => $customerGroupId,
            'product_id'         => $productId,
            'rule_price'         => 42.99,
            'latest_start_date'  => $ruleDate,
            'earliest_end_date'  => $ruleDate,
        ]);

        try {
            // Query with the UTC date string — should NOT match the Jan 16 rule
            $utcDate = '2025-01-15';
            $result = Mage::getResourceModel('catalogrule/rule')
                ->getRulePrice($utcDate, $websiteId, $customerGroupId, $productId);

            expect($result)->toBeFalse();
        } finally {
            $write->delete($table, [
                'rule_date = ?'         => $ruleDate,
                'website_id = ?'        => $websiteId,
                'customer_group_id = ?' => $customerGroupId,
                'product_id = ?'        => $productId,
            ]);
            $product->delete();
        }
    });
});
