<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_OpenTelemetry
 */

declare(strict_types=1);

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Monolog processor that adds OpenTelemetry trace context to log records
 *
 * Enriches log records with trace_id and span_id from the active span,
 * enabling correlation between logs and traces.
 */
class Maho_OpenTelemetry_Handler_TraceContext implements ProcessorInterface
{
    private ?Maho_OpenTelemetry_Model_Tracer $_tracer = null;

    public function __construct(?Maho_OpenTelemetry_Model_Tracer $tracer = null)
    {
        $this->_tracer = $tracer ?? Mage::getTracer();
    }

    #[\Override]
    public function __invoke(LogRecord $record): LogRecord
    {
        if ($this->_tracer && $this->_tracer->isEnabled()) {
            $activeSpan = $this->_tracer->getActiveSpan();
            if ($activeSpan && $activeSpan->isRecording()) {
                return $record->with(
                    extra: array_merge($record->extra, [
                        'trace_id' => $activeSpan->getTraceId(),
                        'span_id' => $activeSpan->getSpanId(),
                    ]),
                );
            }
        }

        return $record;
    }
}
