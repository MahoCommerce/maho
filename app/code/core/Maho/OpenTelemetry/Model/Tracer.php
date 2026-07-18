<?php

/**
 * OpenTelemetry tracer that integrates the OpenTelemetry SDK to send traces to OTLP endpoints.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_OpenTelemetry
 */

declare(strict_types=1);

use OpenTelemetry\API\Metrics\CounterInterface;
use OpenTelemetry\API\Metrics\HistogramInterface;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;
use OpenTelemetry\API\Common\Time\Clock;
use OpenTelemetry\Context\ContextInterface;
use OpenTelemetry\SDK\Logs\LoggerProvider;
use OpenTelemetry\SDK\Logs\Processor\BatchLogRecordProcessor;
use OpenTelemetry\SDK\Metrics\MeterProvider;
use OpenTelemetry\SDK\Metrics\MetricReader\ExportingReader;
use OpenTelemetry\SDK\Metrics\Data\Temporality;
use OpenTelemetry\SDK\Trace\TracerProvider;
use OpenTelemetry\SDK\Trace\TracerProviderInterface;
use OpenTelemetry\SDK\Trace\SpanProcessor\BatchSpanProcessor;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Resource\ResourceInfoFactory;
use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\Contrib\Otlp\LogsExporter;
use OpenTelemetry\Contrib\Otlp\MetricExporter;
use OpenTelemetry\Contrib\Otlp\SpanExporter;
use OpenTelemetry\Contrib\Otlp\OtlpHttpTransportFactory;
use OpenTelemetry\SDK\Trace\Sampler\AlwaysOnSampler;
use OpenTelemetry\SDK\Trace\Sampler\ParentBased;
use OpenTelemetry\SDK\Trace\Sampler\TraceIdRatioBasedSampler;

class Maho_OpenTelemetry_Model_Tracer
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
     * Whether BLOCK: profiler timers should create spans
     */
    private bool $_traceBlocks = true;

    /**
     * OpenTelemetry TracerProvider
     */
    private ?TracerProviderInterface $_tracerProvider = null;

    /**
     * OpenTelemetry Tracer instance
     */
    private ?TracerInterface $_tracer = null;

    /**
     * OpenTelemetry LoggerProvider for OTLP log export (null unless enabled)
     */
    private ?LoggerProvider $_loggerProvider = null;

    /**
     * OpenTelemetry MeterProvider for OTLP metric export (null unless enabled)
     */
    private ?MeterProvider $_meterProvider = null;

    /**
     * Histogram for http.server.request.duration (lazily created)
     */
    private ?HistogramInterface $_requestDurationHistogram = null;

    /**
     * Counters by metric name (lazily created)
     * @var array<string, CounterInterface>
     */
    private array $_counters = [];

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
            // Use error_log() because Mage::log() may not be available during early bootstrap
            error_log('Maho OpenTelemetry: Failed to get helper: ' . $e->getMessage());
            return false;
        }

        // Check if enabled
        if (!$helper->isEnabled()) {
            return false;
        }

        // Excluded paths: the tracer initializes lazily on the first span attempt,
        // which can happen during bootstrap (DB queries) before dispatch — so the
        // exclusion must be decided here, not at dispatch time. Mage::getTracer()
        // caches the false result for the request once store config is readable,
        // and Mage::reset() clears it between requests. CLI has no REQUEST_URI.
        $requestPath = strtok($_SERVER['REQUEST_URI'] ?? '', '?') ?: '';
        if ($requestPath !== '' && $helper->isPathExcluded($requestPath)) {
            return false;
        }

        // Check if endpoint is configured
        $endpoint = $helper->getEndpoint();
        if (empty($endpoint)) {
            Mage::log('OpenTelemetry enabled but no endpoint configured', Mage::LOG_WARNING);
            return false;
        }

        // Check if SDK is available (nyholm/psr7 provides the PSR-17 factories
        // the OTLP HTTP transport discovers at runtime)
        if (!class_exists(TracerProvider::class)) {
            Mage::log('OpenTelemetry SDK not installed. Run: composer require open-telemetry/sdk open-telemetry/exporter-otlp nyholm/psr7', Mage::LOG_WARNING);
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

            // Create OTLP exporter. maxRetries 1 (SDK default is 3): exports are
            // request-scoped, so retrying a struggling collector only holds the
            // PHP worker longer without improving delivery odds.
            $transport = (new OtlpHttpTransportFactory())->create(
                $endpoint,
                'application/x-protobuf',
                $helper->getHeaders(),
                maxRetries: 1,
            );

            $exporter = new SpanExporter($transport);

            // Batch processor: queue spans in memory, export all at once on forceFlush().
            // Queue/batch sizing honors the standard OTEL_BSP_* environment variables.
            $spanProcessor = new BatchSpanProcessor(
                $exporter,
                Clock::getDefault(),
                (int) ($helper->getEnv('OTEL_BSP_MAX_QUEUE_SIZE') ?: BatchSpanProcessor::DEFAULT_MAX_QUEUE_SIZE),
                (int) ($helper->getEnv('OTEL_BSP_SCHEDULE_DELAY') ?: BatchSpanProcessor::DEFAULT_SCHEDULE_DELAY),
                (int) ($helper->getEnv('OTEL_BSP_EXPORT_TIMEOUT') ?: BatchSpanProcessor::DEFAULT_EXPORT_TIMEOUT),
                (int) ($helper->getEnv('OTEL_BSP_MAX_EXPORT_BATCH_SIZE') ?: BatchSpanProcessor::DEFAULT_MAX_EXPORT_BATCH_SIZE),
            );

            // Create sampler based on sampling rate. ParentBased wrapping means a
            // trusted remote parent's sampling decision is respected (the spec's
            // parentbased_traceidratio default); local root spans still sample by ratio.
            $samplingRate = $helper->getSamplingRate();
            $sampler = new ParentBased($samplingRate >= 1.0
                ? new AlwaysOnSampler()
                : new TraceIdRatioBasedSampler($samplingRate));

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

            // Optional OTLP log export: Mage_Core_Model_Logger bridges Monolog to
            // this provider via the official contrib handler when available
            if ($helper->isLogExportEnabled() && class_exists(LoggerProvider::class)) {
                $logsTransport = (new OtlpHttpTransportFactory())->create(
                    $helper->getLogsEndpoint(),
                    'application/x-protobuf',
                    $helper->getHeaders(),
                    maxRetries: 1,
                );
                $this->_loggerProvider = LoggerProvider::builder()
                    ->addLogRecordProcessor(new BatchLogRecordProcessor(new LogsExporter($logsTransport), Clock::getDefault()))
                    ->setResource($resource)
                    ->build();
            }

            // Optional OTLP metric export. Delta temporality: PHP processes are
            // short-lived, so deltas are aggregated on the receiving side.
            if ($helper->isMetricsExportEnabled() && class_exists(MeterProvider::class)) {
                $metricsTransport = (new OtlpHttpTransportFactory())->create(
                    $helper->getMetricsEndpoint(),
                    'application/x-protobuf',
                    $helper->getHeaders(),
                    maxRetries: 1,
                );
                $this->_meterProvider = MeterProvider::builder()
                    ->setResource($resource)
                    ->addReader(new ExportingReader(new MetricExporter($metricsTransport, Temporality::DELTA)))
                    ->build();
            }

            $this->_enabled = true;
            $this->_traceBlocks = $helper->isBlockTracingEnabled();

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
     *
     * @param string|null $kind Span kind: 'server', 'client', 'producer', 'consumer' or null for internal
     */
    public function startRootSpan(string $name, array $attributes = [], ?string $kind = 'server'): Maho_OpenTelemetry_Model_Span
    {
        if (!$this->_enabled || !$this->_tracer) {
            return $this->_createNullSpan();
        }

        try {
            $spanBuilder = $this->_tracer->spanBuilder($name);
            $spanBuilder->setSpanKind($this->_mapSpanKind($kind));

            // Continue the caller's trace when incoming W3C context is trusted;
            // otherwise this starts a brand new trace
            $remoteContext = $this->_extractRemoteContext();
            if ($remoteContext) {
                $spanBuilder->setParent($remoteContext);
            }

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
     *
     * @param string|null $kind Span kind: 'server', 'client', 'producer', 'consumer' or null for internal
     */
    public function startSpan(string $name, array $attributes = [], ?string $kind = null): Maho_OpenTelemetry_Model_Span
    {
        if (!$this->_enabled || !$this->_tracer) {
            return $this->_createNullSpan();
        }

        try {
            $spanBuilder = $this->_tracer->spanBuilder($name);
            $spanBuilder->setSpanKind($this->_mapSpanKind($kind));

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
     * Get the root span of the current trace (bottom of the stack)
     */
    public function getRootSpan(): ?Maho_OpenTelemetry_Model_Span
    {
        return $this->_spanStack[0] ?? null;
    }

    /**
     * Whether BLOCK: profiler timers should create spans (checked by \Maho\Profiler)
     */
    public function isBlockTracingEnabled(): bool
    {
        return $this->_traceBlocks;
    }

    /**
     * Get the LoggerProvider for OTLP log export (null unless log export is enabled)
     */
    public function getLoggerProvider(): ?LoggerProvider
    {
        return $this->_loggerProvider;
    }

    /**
     * Record the http.server.request.duration histogram (no-op unless metric
     * export is enabled)
     */
    public function recordRequestDuration(float $seconds, array $attributes = []): void
    {
        if (!$this->_meterProvider) {
            return;
        }
        try {
            $this->_requestDurationHistogram ??= $this->_meterProvider
                ->getMeter('maho')
                ->createHistogram('http.server.request.duration', 's', 'Duration of HTTP server requests');
            $this->_requestDurationHistogram->record($seconds, $attributes);
        } catch (\Throwable $e) {
            Mage::log('Failed to record request duration metric: ' . $e->getMessage(), Mage::LOG_ERROR);
        }
    }

    /**
     * Increment a counter metric (no-op unless metric export is enabled)
     */
    public function addCounter(string $name, int|float $amount = 1, array $attributes = [], string $unit = '{count}'): void
    {
        if (!$this->_meterProvider) {
            return;
        }
        try {
            $this->_counters[$name] ??= $this->_meterProvider
                ->getMeter('maho')
                ->createCounter($name, $unit);
            $this->_counters[$name]->add($amount, $attributes);
        } catch (\Throwable $e) {
            Mage::log('Failed to record counter metric: ' . $e->getMessage(), Mage::LOG_ERROR);
        }
    }

    /**
     * Extract a trusted remote W3C trace context from the incoming request
     */
    private function _extractRemoteContext(): ?ContextInterface
    {
        try {
            if (empty($_SERVER['HTTP_TRACEPARENT'])
                || !Mage::helper('opentelemetry')->isTrustIncomingTracesEnabled()
            ) {
                return null;
            }
            $carrier = ['traceparent' => (string) $_SERVER['HTTP_TRACEPARENT']];
            if (!empty($_SERVER['HTTP_TRACESTATE'])) {
                $carrier['tracestate'] = (string) $_SERVER['HTTP_TRACESTATE'];
            }
            return TraceContextPropagator::getInstance()->extract($carrier);
        } catch (\Throwable $e) {
            // Malformed incoming context — start a fresh trace instead
            return null;
        }
    }

    /**
     * Map a span kind string to the SDK SpanKind constant
     */
    private function _mapSpanKind(?string $kind): int
    {
        return match ($kind) {
            'server' => SpanKind::KIND_SERVER,
            'client' => SpanKind::KIND_CLIENT,
            'producer' => SpanKind::KIND_PRODUCER,
            'consumer' => SpanKind::KIND_CONSUMER,
            default => SpanKind::KIND_INTERNAL,
        };
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
                $headers = [
                    'traceparent' => sprintf(
                        '00-%s-%s-%02x',
                        $context->getTraceId(),
                        $context->getSpanId(),
                        $context->getTraceFlags(),
                    ),
                ];

                // W3C Baggage: propagate store context to downstream services
                try {
                    $store = Mage::app()->getStore();
                    $headers['baggage'] = sprintf(
                        'maho.store=%s,maho.currency=%s',
                        rawurlencode((string) $store->getCode()),
                        rawurlencode((string) $store->getCurrentCurrencyCode()),
                    );
                } catch (\Throwable $e) {
                    // Store not initialized — propagate trace context only
                }

                return $headers;
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
            // Suppress E_DEPRECATED during flush because google/protobuf
            // triggers PHP 8.5 deprecation notices that Maho's developer mode
            // error handler would convert to exceptions, breaking the export.
            $prevReporting = error_reporting(error_reporting() & ~E_DEPRECATED);
            try {
                $this->_tracerProvider->forceFlush();
                $this->_loggerProvider?->forceFlush();
                $this->_meterProvider?->forceFlush();
            } finally {
                error_reporting($prevReporting);
            }
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
        // Instantiate directly to avoid Profiler::start() → startSpan() recursion
        $span = new Maho_OpenTelemetry_Model_Span();
        $span->setSdkSpan($sdkSpan);
        $span->setTracer($this);
        return $span;
    }

    /**
     * Create a null span (no-op implementation)
     */
    private function _createNullSpan(): Maho_OpenTelemetry_Model_Span
    {
        return new Maho_OpenTelemetry_Model_Span();
    }

    /**
     * Shutdown telemetry providers (called on destruct)
     */
    public function __destruct()
    {
        try {
            $prevReporting = error_reporting(error_reporting() & ~E_DEPRECATED);
            try {
                $this->_tracerProvider?->shutdown();
                $this->_loggerProvider?->shutdown();
                $this->_meterProvider?->shutdown();
            } finally {
                error_reporting($prevReporting);
            }
        } catch (\Throwable $e) {
            // Ignore errors during shutdown
        }
    }
}
