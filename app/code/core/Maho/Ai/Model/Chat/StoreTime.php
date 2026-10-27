<?php

/**
 * Write the UTC times of an answer of the assistant in the timezone of the store.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Ai
 */

declare(strict_types=1);

/**
 * The model copies each time from a tool result with " UTC" after it, and never converts it:
 * a model often adds the wrong offset, mostly across a change to or from summer time. Only the
 * text that the administrator sees gets store times, so the model reads UTC in its history.
 */
final class Maho_Ai_Model_Chat_StoreTime
{
    private const PATTERN = '/\b(\d{4}-\d{2}-\d{2})[ T](\d{2}:\d{2})(?::\d{2})? ?UTC\b/';

    /** Write each UTC time of $text in store time: "2026-10-19 06:21 UTC" becomes "2026-10-19 07:21". */
    public static function toStore(string $text, ?int $storeId): string
    {
        $timezone = (string) Mage::getStoreConfig(Mage_Core_Model_Locale::XML_PATH_DEFAULT_TIMEZONE, $storeId);
        $zone = new DateTimeZone($timezone === '' ? 'UTC' : $timezone);

        return (string) preg_replace_callback(self::PATTERN, static function (array $match) use ($zone): string {
            $utc = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $match[1] . ' ' . $match[2], new DateTimeZone('UTC'));
            return $utc === false ? $match[0] : $utc->setTimezone($zone)->format('Y-m-d H:i');
        }, $text);
    }
}
