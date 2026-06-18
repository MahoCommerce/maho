<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_FeedManager
 */

declare(strict_types=1);

/**
 * Emit a Google-Shopping-style ISO 8601 date range derived from offsets
 * evaluated at generation time.
 *
 * Google's `sale_price_effective_date` (and a few similar fields) wants
 * two ISO 8601 datetimes joined with a slash. Most catalogs run perpetual
 * promotions and have no per-product sale-end date, so the practical
 * pattern is to synthesise a rolling window: "now / now + N units". The
 * incoming value is ignored — the transformer emits the calculated range
 * regardless of the source it is chained on.
 *
 * Options are structured (direction + amount + unit) per end of the
 * range, so the admin UI shows sign / number / unit selectors instead of
 * raw strtotime expressions. Internally the offsets are stitched into
 * "+N units" / "-N units" strings and parsed by DateTime.
 *
 * Defaults produce "now / +90 days" — leaving the option chain blank in
 * the admin UI emits a 3-month rolling window with no extra config.
 */
class Maho_FeedManager_Model_Transformer_RelativeDateRange extends Maho_FeedManager_Model_Transformer_AbstractTransformer
{
    protected string $_code = 'relative_date_range';
    protected string $_name = 'Relative Date Range';
    protected string $_description = 'Output an ISO 8601 date range relative to the time of feed generation';

    protected const DEFAULT_OUTPUT_FORMAT = 'c';
    protected const DEFAULT_START_AMOUNT = '0';
    protected const DEFAULT_END_AMOUNT = '90';
    protected const DEFAULT_UNIT = 'days';
    protected const DEFAULT_DIRECTION = '+';

    protected array $_optionDefinitions = [
        'start_direction' => [
            'label' => 'Start Direction',
            'type' => 'select',
            'required' => false,
            'options' => ['+' => 'After Now (+)', '-' => 'Before Now (-)'],
            'note' => 'Default: + (after now).',
        ],
        'start_amount' => [
            'label' => 'Start Amount',
            'type' => 'text',
            'required' => false,
            'note' => 'Numeric amount. Default: 0 (i.e., now).',
        ],
        'start_unit' => [
            'label' => 'Start Unit',
            'type' => 'select',
            'required' => false,
            'options' => [
                'minutes' => 'Minutes',
                'hours' => 'Hours',
                'days' => 'Days',
                'weeks' => 'Weeks',
                'months' => 'Months',
                'years' => 'Years',
            ],
            'note' => 'Default: days.',
        ],
        'end_direction' => [
            'label' => 'End Direction',
            'type' => 'select',
            'required' => false,
            'options' => ['+' => 'After Now (+)', '-' => 'Before Now (-)'],
            'note' => 'Default: + (after now).',
        ],
        'end_amount' => [
            'label' => 'End Amount',
            'type' => 'text',
            'required' => false,
            'note' => 'Numeric amount. Default: 90.',
        ],
        'end_unit' => [
            'label' => 'End Unit',
            'type' => 'select',
            'required' => false,
            'options' => [
                'minutes' => 'Minutes',
                'hours' => 'Hours',
                'days' => 'Days',
                'weeks' => 'Weeks',
                'months' => 'Months',
                'years' => 'Years',
            ],
            'note' => 'Default: days.',
        ],
        'output_format' => [
            'label' => 'Output Format',
            'type' => 'text',
            'required' => false,
            'note' => 'PHP date() format. Default: "c" (ISO 8601 with timezone).',
        ],
        'timezone' => [
            'label' => 'Timezone',
            'type' => 'text',
            'required' => false,
            'note' => 'Timezone name (e.g., Australia/Sydney). Default: PHP\'s configured timezone.',
        ],
    ];

    #[\Override]
    public function transform(mixed $value, array $options = [], array $productData = []): mixed
    {
        $startExpr = $this->_buildOffsetExpression(
            (string) $this->_getOption($options, 'start_direction', self::DEFAULT_DIRECTION),
            (string) $this->_getOption($options, 'start_amount', self::DEFAULT_START_AMOUNT),
            (string) $this->_getOption($options, 'start_unit', self::DEFAULT_UNIT),
        );

        $endExpr = $this->_buildOffsetExpression(
            (string) $this->_getOption($options, 'end_direction', self::DEFAULT_DIRECTION),
            (string) $this->_getOption($options, 'end_amount', self::DEFAULT_END_AMOUNT),
            (string) $this->_getOption($options, 'end_unit', self::DEFAULT_UNIT),
        );

        $outputFormat = (string) $this->_getOption($options, 'output_format', self::DEFAULT_OUTPUT_FORMAT);
        $timezone = (string) $this->_getOption($options, 'timezone', '');

        try {
            $tz = $timezone !== '' ? new DateTimeZone($timezone) : null;
            $start = new DateTime($startExpr, $tz);
            $end = new DateTime($endExpr, $tz);
        } catch (Exception $e) {
            // Bad offset or timezone — fall back to the original value rather
            // than emitting a malformed range that would break a feed parser.
            return $value;
        }

        return $start->format($outputFormat) . '/' . $end->format($outputFormat);
    }

    /**
     * Stitch a strtotime-compatible expression from the structured pieces.
     * "+ 0 days" parses to "now" so the start defaults work without any
     * additional special-casing.
     */
    protected function _buildOffsetExpression(string $direction, string $amount, string $unit): string
    {
        $sign = $direction === '-' ? '-' : '+';
        $amount = ltrim($amount, '+-');
        if ($amount === '') {
            $amount = '0';
        }
        if ($unit === '') {
            $unit = self::DEFAULT_UNIT;
        }
        return $sign . $amount . ' ' . $unit;
    }
}
