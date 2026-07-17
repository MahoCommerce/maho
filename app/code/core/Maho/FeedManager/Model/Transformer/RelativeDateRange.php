<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_FeedManager
 */

declare(strict_types=1);

/**
 * Output an ISO 8601 date range (e.g. now / +90 days) for fields like
 * sale_price_effective_date. The incoming value is ignored.
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
            return $value;
        }

        return $start->format($outputFormat) . '/' . $end->format($outputFormat);
    }

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
