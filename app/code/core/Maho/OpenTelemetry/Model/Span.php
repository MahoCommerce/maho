<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_OpenTelemetry
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\Context\ScopeInterface;

/**
 * OpenTelemetry Span Implementation
 *
 * Wraps the OpenTelemetry SDK span or provides no-op behavior when SDK is not available.
 */
class Maho_OpenTelemetry_Model_Span extends Mage_Core_Model_Abstract
{
    /**
     * The underlying OpenTelemetry SDK span
     */
    private ?SpanInterface $_sdkSpan = null;

    /**
     * The scope from activating this span in the OTel Context
     */
    private ?ScopeInterface $_scope = null;

    /**
     * Whether this span has already been ended
     */
    private bool $_ended = false;

    /**
     * Reference to the tracer that created this span
     */
    private ?Maho_OpenTelemetry_Model_Tracer $_tracer = null;

    /**
     * Set the underlying SDK span and activate it in the OpenTelemetry Context
     *
     * Activation is required so that child spans created later will automatically
     * be nested under this span. Without activation, all spans appear as root spans.
     *
     * @return $this
     */
    public function setSdkSpan(SpanInterface $span): self
    {
        $this->_sdkSpan = $span;

        // Activate the span so child spans are nested correctly
        try {
            $this->_scope = $span->activate();
        } catch (\Throwable $e) {
            // Activation failure should not break the application
            error_log('OpenTelemetry: Failed to activate span: ' . $e->getMessage());
        }

        return $this;
    }

    /**
     * Get the underlying SDK span
     */
    public function getSdkSpan(): ?SpanInterface
    {
        return $this->_sdkSpan;
    }

    /**
     * Set the tracer that created this span
     *
     * @return $this
     */
    public function setTracer(Maho_OpenTelemetry_Model_Tracer $tracer): self
    {
        $this->_tracer = $tracer;
        return $this;
    }

    /**
     * Set a single attribute on the span
     *
     * @return $this
     */
    public function setAttribute(string $key, mixed $value): self
    {
        if ($this->_sdkSpan) {
            try {
                $this->_sdkSpan->setAttribute($key, $value);
            } catch (\Throwable $e) {
                Mage::log('Failed to set span attribute: ' . $e->getMessage(), Mage::LOG_ERROR);
            }
        }
        return $this;
    }

    /**
     * Set multiple attributes on the span
     *
     * @return $this
     */
    public function setAttributes(array $attributes): self
    {
        foreach ($attributes as $key => $value) {
            $this->setAttribute($key, $value);
        }
        return $this;
    }

    /**
     * Record an exception event on the span
     *
     * @return $this
     */
    public function recordException(\Throwable $e): self
    {
        if ($this->_sdkSpan) {
            try {
                $this->_sdkSpan->recordException($e, [
                    'exception.escaped' => true,
                ]);
            } catch (\Throwable $ex) {
                Mage::log('Failed to record exception on span: ' . $ex->getMessage(), Mage::LOG_ERROR);
            }
        }
        return $this;
    }

    /**
     * Set the span status
     *
     * @return $this
     */
    public function setStatus(string $status, ?string $description = null): self
    {
        if ($this->_sdkSpan) {
            try {
                $statusCode = match (strtolower($status)) {
                    'ok' => StatusCode::STATUS_OK,
                    'error' => StatusCode::STATUS_ERROR,
                    default => StatusCode::STATUS_UNSET,
                };
                $this->_sdkSpan->setStatus($statusCode, $description);
            } catch (\Throwable $e) {
                Mage::log('Failed to set span status: ' . $e->getMessage(), Mage::LOG_ERROR);
            }
        }
        return $this;
    }

    /**
     * Add an event to the span
     *
     * @return $this
     */
    public function addEvent(string $name, array $attributes = []): self
    {
        if ($this->_sdkSpan) {
            try {
                $this->_sdkSpan->addEvent($name, $attributes);
            } catch (\Throwable $e) {
                Mage::log('Failed to add span event: ' . $e->getMessage(), Mage::LOG_ERROR);
            }
        }
        return $this;
    }

    /**
     * End the span and detach its scope from the OpenTelemetry Context
     */
    public function end(): void
    {
        // Guard against double-end calls (e.g. from flush() + normal end)
        if ($this->_ended) {
            return;
        }
        $this->_ended = true;

        // Detach the scope first to restore the parent context
        if ($this->_scope) {
            try {
                $this->_scope->detach();
            } catch (\Throwable $e) {
                error_log('OpenTelemetry: Failed to detach span scope: ' . $e->getMessage());
            }
            $this->_scope = null;
        }

        if ($this->_sdkSpan) {
            try {
                $this->_sdkSpan->end();
            } catch (\Throwable $e) {
                Mage::log('Failed to end span: ' . $e->getMessage(), Mage::LOG_ERROR);
            }
        }

        // Pop this span from the tracer's stack
        $this->_tracer?->popSpan($this);
    }

    /**
     * Get the trace ID
     */
    public function getTraceId(): string
    {
        if ($this->_sdkSpan) {
            try {
                return $this->_sdkSpan->getContext()->getTraceId();
            } catch (\Throwable $e) {
                Mage::log('Failed to get trace ID: ' . $e->getMessage(), Mage::LOG_ERROR);
            }
        }
        return '';
    }

    /**
     * Get the span ID
     */
    public function getSpanId(): string
    {
        if ($this->_sdkSpan) {
            try {
                return $this->_sdkSpan->getContext()->getSpanId();
            } catch (\Throwable $e) {
                Mage::log('Failed to get span ID: ' . $e->getMessage(), Mage::LOG_ERROR);
            }
        }
        return '';
    }

    /**
     * Check if the span is recording
     */
    public function isRecording(): bool
    {
        if ($this->_sdkSpan) {
            try {
                return $this->_sdkSpan->isRecording();
            } catch (\Throwable $e) {
                Mage::log('Failed to check if span is recording: ' . $e->getMessage(), Mage::LOG_ERROR);
            }
        }
        return false;
    }
}
