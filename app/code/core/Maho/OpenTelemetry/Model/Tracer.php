<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_OpenTelemetry
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Common\Time\Clock;
use OpenTelemetry\SDK\Trace\TracerProvider;
use OpenTelemetry\SDK\Trace\TracerProviderInterface;
use OpenTelemetry\SDK\Trace\SpanProcessor\BatchSpanProcessor;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Resource\ResourceInfoFactory;
use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\Contrib\Otlp\SpanExporter;
use OpenTelemetry\Contrib\Otlp\OtlpHttpTransportFactory;
use OpenTelemetry\SDK\Trace\Sampler\AlwaysOnSampler;
use OpenTelemetry\SDK\Trace\Sampler\TraceIdRatioBasedSampler;

/**
 * OpenTelemetry Tracer Implementation
 *
 * Integrates the OpenTelemetry SDK to send traces to OTLP endpoints
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
     * OpenTelemetry TracerProvider
     */
    private ?TracerProviderInterface $_tracerProvider = null;

    /**
     * OpenTelemetry Tracer instance
     */
    private ?TracerInterface $_tracer = null;

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

        // Check if SDK is available
        if (!class_exists(TracerProvider::class)) {
            Mage::log('OpenTelemetry SDK not installed. Run: composer require open-telemetry/sdk open-telemetry/exporter-otlp', Mage::LOG_WARNING);
            return false;
        }

        try {
            // Create resource with service information
            $resource = ResourceInfoFactory::emptyResource()->merge(ResourceInfo::create(Attributes::create([
                'service.name' => $helper->getServiceName(),
                'service.version' => Mage::getVersion(),
                'telemetry.sdk.name' => 'opentelemetry',
                'telemetry.sdk.language' => 'php',
                'telemetry.sdk.version' => \Composer\InstalledVersions::getVersion('open-telemetry/sdk') ?? 'unknown',
            ])));

            // Create OTLP exporter
            $transport = (new OtlpHttpTransportFactory())->create(
                $endpoint,
                'application/json',
                $helper->getHeaders(),
            );

            $exporter = new SpanExporter($transport);

            // Create span processor with batching
            $spanProcessor = new BatchSpanProcessor(
                $exporter,
                Clock::getDefault(),
            );

            // Create sampler based on sampling rate
            $samplingRate = $helper->getSamplingRate();
            $sampler = $samplingRate >= 1.0
                ? new AlwaysOnSampler()
                : new TraceIdRatioBasedSampler($samplingRate);

            // Create tracer provider
            $this->_tracerProvider = TracerProvider::builder()
                ->addSpanProcessor($spanProcessor)
                ->setResource($resource)
                ->setSampler($sampler)
                ->build();

            // Get tracer instance
            $this->_tracer = $this->_tracerProvider->getTracer(
                'maho',
                Mage::getVersion(),
            );

            $this->_enabled = true;

            Mage::log('OpenTelemetry tracer initialized successfully', Mage::LOG_INFO);

            return $this;
        } catch (\Throwable $e) {
            Mage::log('OpenTelemetry initialization failed: ' . $e->getMessage(), Mage::LOG_ERROR);
            Mage::logException($e);
            return false;
        }
    }

    /**
     * Start a root span (top-level span for a trace)
     */
    public function startRootSpan(string $name, array $attributes = []): Maho_OpenTelemetry_Model_Span
    {
        if (!$this->_enabled || !$this->_tracer) {
            return $this->_createNullSpan();
        }

        try {
            // Start a new root span (no parent)
            $spanBuilder = $this->_tracer->spanBuilder($name);

            // Add attributes
            foreach ($attributes as $key => $value) {
                $spanBuilder->setAttribute($key, $value);
            }

            $sdkSpan = $spanBuilder->startSpan();

            // Wrap in our Span model
            $span = $this->_createSpan($sdkSpan);
            $this->_spanStack[] = $span;

            return $span;
        } catch (\Throwable $e) {
            Mage::log('Failed to create root span: ' . $e->getMessage(), Mage::LOG_ERROR);
            return $this->_createNullSpan();
        }
    }

    /**
     * Start a child span (nested under current active span)
     */
    public function startSpan(string $name, array $attributes = []): Maho_OpenTelemetry_Model_Span
    {
        if (!$this->_enabled || !$this->_tracer) {
            return $this->_createNullSpan();
        }

        try {
            $spanBuilder = $this->_tracer->spanBuilder($name);

            // Parent span is automatically set from current context by OpenTelemetry SDK

            // Add attributes
            foreach ($attributes as $key => $value) {
                $spanBuilder->setAttribute($key, $value);
            }

            $sdkSpan = $spanBuilder->startSpan();

            // Wrap in our Span model
            $span = $this->_createSpan($sdkSpan);
            $this->_spanStack[] = $span;

            return $span;
        } catch (\Throwable $e) {
            Mage::log('Failed to create span: ' . $e->getMessage(), Mage::LOG_ERROR);
            return $this->_createNullSpan();
        }
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

        $activeSpan = $this->getActiveSpan();
        if (!$activeSpan || !$activeSpan->getSdkSpan()) {
            return [];
        }

        try {
            $context = $activeSpan->getSdkSpan()->getContext();
            if ($context->isValid()) {
                return [
                    'traceparent' => sprintf(
                        '00-%s-%s-%02x',
                        $context->getTraceId(),
                        $context->getSpanId(),
                        $context->getTraceFlags(),
                    ),
                ];
            }
        } catch (\Throwable $e) {
            Mage::log('Failed to generate trace headers: ' . $e->getMessage(), Mage::LOG_ERROR);
        }

        return [];
    }

    /**
     * Flush all pending spans to the exporter
     */
    public function flush(): void
    {
        if (!$this->_enabled || !$this->_tracerProvider) {
            return;
        }

        // End any remaining spans in reverse order (child spans first)
        $remainingSpans = array_reverse($this->_spanStack);
        $this->_spanStack = [];
        foreach ($remainingSpans as $span) {
            try {
                $span->end();
            } catch (\Throwable $e) {
                // Ignore errors ending orphaned spans
            }
        }

        try {
            $this->_tracerProvider->forceFlush();
        } catch (\Throwable $e) {
            Mage::log('Failed to flush telemetry: ' . $e->getMessage(), Mage::LOG_ERROR);
        }
    }

    /**
     * Check if tracing is enabled
     */
    public function isEnabled(): bool
    {
        return $this->_enabled;
    }

    /**
     * Pop a span from the stack when it ends
     */
    public function popSpan(Maho_OpenTelemetry_Model_Span $span): void
    {
        // Remove the span from the stack (search from end for efficiency)
        for ($i = count($this->_spanStack) - 1; $i >= 0; $i--) {
            if ($this->_spanStack[$i] === $span) {
                array_splice($this->_spanStack, $i, 1);
                break;
            }
        }
    }

    /**
     * Create a span wrapping an SDK span
     */
    private function _createSpan(SpanInterface $sdkSpan): Maho_OpenTelemetry_Model_Span
    {
        $span = Mage::getModel('opentelemetry/span');
        $span->setSdkSpan($sdkSpan);
        $span->setTracer($this);
        return $span;
    }

    /**
     * Create a null span (no-op implementation)
     */
    private function _createNullSpan(): Maho_OpenTelemetry_Model_Span
    {
        return Mage::getModel('opentelemetry/span');
    }

    /**
     * Shutdown tracer provider (called on destruct)
     */
    public function __destruct()
    {
        if ($this->_tracerProvider) {
            try {
                $this->_tracerProvider->shutdown();
            } catch (\Throwable $e) {
                // Ignore errors during shutdown
            }
        }
    }
}
