<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_FeedManager
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_FeedManager_Helper_Date extends Mage_Core_Helper_Abstract
{
    protected $_moduleName = 'Maho_FeedManager';

    /**
     * Format a UTC datetime string as ISO 8601 with timezone offset
     *
     * Input is the raw `Y-m-d H:i:s` form stored in DB (UTC).
     * Output is `Y-m-d\TH:iO` (e.g. `2026-04-01T00:00+0000`), the format
     * required by Google/Facebook/Bing/Pinterest shopping feeds.
     */
    public function toIso8601(?string $utcDateTime): ?string
    {
        if (!$utcDateTime) {
            return null;
        }
        $format = Mage_Core_Model_Locale::HTML5_DATETIME_FORMAT . 'O';
        return (new \DateTimeImmutable($utcDateTime, new \DateTimeZone('UTC')))->format($format);
    }

    /**
     * Format a `{from}/{to}` ISO 8601 range from two UTC datetime strings
     *
     * Returns empty string when either bound is missing. Google/Facebook/Bing/
     * Pinterest all require both bounds: `(YYYY-MM-DD)T(HH:MM±HHMM)/(YYYY-MM-DD)T(HH:MM±HHMM)`,
     * there is no half-open form. When the field is omitted, the sale_price
     * is treated as active for as long as it's submitted, which is the right
     * fallback when only one bound is known.
     */
    public function toIso8601Range(?string $from, ?string $to): string
    {
        if (!$from || !$to) {
            return '';
        }
        return $this->toIso8601($from) . '/' . $this->toIso8601($to);
    }
}
