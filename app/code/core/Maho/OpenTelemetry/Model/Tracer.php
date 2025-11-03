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
 * OpenTelemetry Tracer Implementation
 *
 * This is a stub implementation that will be enhanced when OpenTelemetry SDK is installed.
 * Currently returns a NullTracer that does nothing (no-op spans).
 */
class Maho_OpenTelemetry_Model_Tracer extends Mage_Core_Model_Abstract
{
    /**
     * Active span stack
     */
    private array $_spanStack = [];

    /**
     * Is tracer enabled and initialized
     */
    private bool $_enabled = false;

    /**
     * Initialize tracer
     *
     * This method is called by Mage::getTracer() on first access.
     * It checks if OpenTelemetry is enabled and configured.
     *
     * @return self|false Returns self if initialized successfully, false otherwise
     */
    public function initialize(): self|false
    {
        try {
            $helper = Mage::helper('opentelemetry');
        } catch (\Throwable $e) {
            error_log('OpenTelemetry: Failed to get helper: ' . $e->getMessage());
            return false;
        }

        // Check if enabled
        if (!$helper->isEnabled()) {
            return false;
        }

        // Check if endpoint is configured
        $endpoint = $helper->getEndpoint();
        if (empty($endpoint)) {
            Mage::log('OpenTelemetry enabled but no endpoint configured', Mage::LOG_WARNING);
            return false;
        }

        // TODO: When OpenTelemetry SDK is installed, initialize it here
        // For now, we just mark as enabled
        $this->_enabled = true;

        Mage::log('OpenTelemetry tracer initialized (stub mode)', Mage::LOG_INFO);

        return $this;
    }

    /**
     * Initialize tracer with configuration
     *
     * @return $this
     */
    public function init(array $config): self
    {
        // Legacy init method - not used in current implementation
        return $this;
    }

    /**
     * Start a root span (top-level span for a trace)
     */
    public function startRootSpan(string $name, array $attributes = []): Maho_OpenTelemetry_Model_Span
    {
        if (!$this->_enabled) {
            return $this->_createNullSpan();
        }

        // TODO: Create actual root span when SDK is available
        $span = $this->_createNullSpan();
        $this->_spanStack[] = $span;

        return $span;
    }

    /**
     * Start a child span (nested under current active span)
     */
    public function startSpan(string $name, array $attributes = []): Maho_OpenTelemetry_Model_Span
    {
        if (!$this->_enabled) {
            return $this->_createNullSpan();
        }

        // TODO: Create actual child span when SDK is available
        $span = $this->_createNullSpan();
        $this->_spanStack[] = $span;

        return $span;
    }

    /**
     * Get the currently active span
     */
    public function getActiveSpan(): ?Maho_OpenTelemetry_Model_Span
    {
        if (empty($this->_spanStack)) {
            return null;
        }

        return end($this->_spanStack);
    }

    /**
     * Record an exception in the active span
     */
    public function recordException(\Throwable $e): void
    {
        if (!$this->_enabled) {
            return;
        }

        $activeSpan = $this->getActiveSpan();
        if ($activeSpan) {
            $activeSpan->recordException($e);
        }
    }

    /**
     * Get W3C Trace Context propagation headers
     */
    public function getTracePropagationHeaders(): array
    {
        if (!$this->_enabled) {
            return [];
        }

        // TODO: Generate W3C Trace Context headers when SDK is available
        return [];
    }

    /**
     * Flush all pending spans to the exporter
     */
    public function flush(): void
    {
        if (!$this->_enabled) {
            return;
        }

        // TODO: Flush spans to OTLP exporter when SDK is available
        $this->_spanStack = [];
    }

    /**
     * Check if tracing is enabled
     */
    public function isEnabled(): bool
    {
        return $this->_enabled;
    }

    /**
     * Create a null span (no-op implementation)
     */
    private function _createNullSpan(): Maho_OpenTelemetry_Model_Span
    {
        return Mage::getModel('opentelemetry/span');
    }
}
