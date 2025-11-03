<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_OpenTelemetry
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * OpenTelemetry Span Implementation
 *
 * This is a null/no-op implementation that performs no actual tracing.
 * This allows the code to run without the OpenTelemetry SDK installed.
 *
 * When the OpenTelemetry SDK is installed, this class will be enhanced
 * to wrap the actual SDK span implementation.
 */
class Maho_OpenTelemetry_Model_Span extends Mage_Core_Model_Abstract
{
    /**
     * Set a single attribute on the span
     *
     * @return $this
     */
    public function setAttribute(string $key, mixed $value): self
    {
        // No-op
        return $this;
    }

    /**
     * Set multiple attributes on the span
     *
     * @return $this
     */
    public function setAttributes(array $attributes): self
    {
        // No-op
        return $this;
    }

    /**
     * Record an exception event on the span
     *
     * @return $this
     */
    public function recordException(\Throwable $e): self
    {
        // No-op
        return $this;
    }

    /**
     * Set the span status
     *
     * @return $this
     */
    public function setStatus(string $status, ?string $description = null): self
    {
        // No-op
        return $this;
    }

    /**
     * Add an event to the span
     *
     * @return $this
     */
    public function addEvent(string $name, array $attributes = []): self
    {
        // No-op
        return $this;
    }

    /**
     * End the span
     */
    public function end(): void
    {
        // No-op
    }

    /**
     * Get the trace ID
     */
    public function getTraceId(): string
    {
        return '';
    }

    /**
     * Get the span ID
     */
    public function getSpanId(): string
    {
        return '';
    }

    /**
     * Check if the span is recording
     */
    public function isRecording(): bool
    {
        return false;
    }
}
