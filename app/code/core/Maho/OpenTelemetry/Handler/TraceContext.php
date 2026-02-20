<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_OpenTelemetry
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;

/**
 * Monolog handler that adds OpenTelemetry trace context to log records
 *
 * This handler enriches log records with trace_id and span_id from the
 * active span, enabling correlation between logs and traces.
 *
 * It does NOT export logs itself - it just adds context. Use in combination
 * with other handlers (file, syslog, etc.) to actually write the logs.
 */
class Maho_OpenTelemetry_Handler_TraceContext extends AbstractProcessingHandler
{
    /**
     * Tracer instance
     */
    private ?Maho_OpenTelemetry_Model_Tracer $_tracer = null;

    /**
     * Constructor
     *
     * @param Level|int $level Minimum log level
     * @param bool $bubble Whether to bubble the record to other handlers
     */
    public function __construct(
        ?Maho_OpenTelemetry_Model_Tracer $tracer = null,
        Level|int $level = Level::Debug,
        bool $bubble = true,
    ) {
        parent::__construct($level, $bubble);
        $this->_tracer = $tracer ?? Mage::getTracer();
    }

    /**
     * Process the log record by adding trace context
     */
    #[\Override]
    protected function write(LogRecord $record): void
    {
        // This handler only adds context, it doesn't write anything
        // The actual writing is done by other handlers in the stack
    }

    /**
     * Add trace context to the record
     */
    #[\Override]
    public function handle(LogRecord $record): bool
    {
        if (!$this->isHandling($record)) {
            return false;
        }

        // Add trace context if tracer is available
        if ($this->_tracer && $this->_tracer->isEnabled()) {
            $activeSpan = $this->_tracer->getActiveSpan();
            if ($activeSpan && $activeSpan->isRecording()) {
                $record = $record->with(
                    extra: array_merge($record->extra, [
                        'trace_id' => $activeSpan->getTraceId(),
                        'span_id' => $activeSpan->getSpanId(),
                    ]),
                );
            }
        }

        // Process the record through any processors and write
        $record = $this->processRecord($record);
        $this->write($record);

        return !$this->bubble;
    }
}
