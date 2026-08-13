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
use OpenTelemetry\API\Trace\Span as ApiSpan;
use OpenTelemetry\API\Baggage\Baggage;
use OpenTelemetry\API\Common\Time\Clock;
use OpenTelemetry\Context\Context;
use OpenTelemetry\Context\ContextInterface;
use OpenTelemetry\Context\Propagation\TextMapPropagatorInterface;
use OpenTelemetry\SDK\Common\Configuration\Configuration;
use OpenTelemetry\SDK\Common\Configuration\Variables;
use OpenTelemetry\SDK\Common\Export\TransportInterface;
use OpenTelemetry\SDK\Propagation\PropagatorFactory;
use OpenTelemetry\SDK\Trace\SamplerFactory;
use OpenTelemetry\Contrib\Otlp\Protocols;
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
     * Whether cache. profiler timers should create spans
     */
    private bool $_traceCache = false;

    /**
     * Whether query spans carry the statement text
     */
    private bool $_queryText = true;

    /**
     * Whether a root span exists. Before it, a span would become the root of
     * its own single-span trace: the tracer initializes during bootstrap.
     */
    private bool $_rootStarted = false;

    /**
     * Hosts that may receive the W3C baggage header, lowercased
     * @var list<string>
     */
    private array $_baggageHosts = [];

    /**
     * Context propagators named by OTEL_PROPAGATORS
     */
    private ?TextMapPropagatorInterface $_propagator = null;

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
            // The default resource runs the standard detectors: telemetry.sdk.*,
            // host.*, process.* and OTEL_RESOURCE_ATTRIBUTES
            $resourceAttributes = [
                'service.name' => $helper->getServiceName(),
                'service.version' => Mage::getVersion(),
            ];
            $environment = $helper->getDeploymentEnvironment();
            if ($environment !== '') {
                $resourceAttributes['deployment.environment.name'] = $environment;
            }
            $resource = ResourceInfoFactory::defaultResource()
                ->merge(ResourceInfo::create(Attributes::create($resourceAttributes)));

            $exporter = new SpanExporter($this->_createTransport($endpoint, 'traces'));

            // Batch processor: queue spans in memory, export all at once on forceFlush().
            // Queue/batch sizing honors the standard OTEL_BSP_* environment variables.
            $spanProcessor = new BatchSpanProcessor(
                $exporter,
                Clock::getDefault(),
                Configuration::getInt(Variables::OTEL_BSP_MAX_QUEUE_SIZE, BatchSpanProcessor::DEFAULT_MAX_QUEUE_SIZE),
                Configuration::getInt(Variables::OTEL_BSP_SCHEDULE_DELAY, BatchSpanProcessor::DEFAULT_SCHEDULE_DELAY),
                Configuration::getInt(Variables::OTEL_BSP_EXPORT_TIMEOUT, BatchSpanProcessor::DEFAULT_EXPORT_TIMEOUT),
                Configuration::getInt(Variables::OTEL_BSP_MAX_EXPORT_BATCH_SIZE, BatchSpanProcessor::DEFAULT_MAX_EXPORT_BATCH_SIZE),
            );

            // OTEL_TRACES_SAMPLER wins, resolved by the SDK; else the admin ratio
            $sampler = null;
            if (Configuration::has(Variables::OTEL_TRACES_SAMPLER)) {
                try {
                    $sampler = (new SamplerFactory())->create();
                } catch (\Throwable $e) {
                    Mage::log('OpenTelemetry: ' . $e->getMessage() . ', using the configured sampling rate', Mage::LOG_WARNING);
                }
            }
            if ($sampler === null) {
                $samplingRate = $helper->getSamplingRate();
                $sampler = new ParentBased($samplingRate >= 1.0
                    ? new AlwaysOnSampler()
                    : new TraceIdRatioBasedSampler($samplingRate));
            }

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
                $logsTransport = $this->_createTransport($helper->getLogsEndpoint(), 'logs');
                $this->_loggerProvider = LoggerProvider::builder()
                    ->addLogRecordProcessor(new BatchLogRecordProcessor(new LogsExporter($logsTransport), Clock::getDefault()))
                    ->setResource($resource)
                    ->build();
            }

            // Optional OTLP metric export. Delta temporality: PHP processes are
            // short-lived, so deltas are aggregated on the receiving side.
            if ($helper->isMetricsExportEnabled() && class_exists(MeterProvider::class)) {
                $metricsTransport = $this->_createTransport($helper->getMetricsEndpoint(), 'metrics');
                $this->_meterProvider = MeterProvider::builder()
                    ->setResource($resource)
                    ->addReader(new ExportingReader(new MetricExporter($metricsTransport, Temporality::DELTA)))
                    ->build();
            }

            $this->_enabled = true;
            $this->_traceBlocks = $helper->isBlockTracingEnabled();
            $this->_traceCache = $helper->isCacheTracingEnabled();
            $this->_queryText = $helper->isQueryTextEnabled();
            $this->_baggageHosts = $helper->getBaggageHosts();
            $this->_propagator = (new PropagatorFactory())->create();

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
     * @param array<string, string> $parentCarrier Trace context this install wrote itself, e.g. onto a
     *        queue message. Trusted without the incoming-header check, which governs remote callers.
     */
    public function startRootSpan(string $name, array $attributes = [], ?string $kind = 'server', array $parentCarrier = []): Maho_OpenTelemetry_Model_Span
    {
        if (!$this->_enabled || !$this->_tracer) {
            return $this->_createNullSpan();
        }

        try {
            $spanBuilder = $this->_tracer->spanBuilder($name);
            $spanBuilder->setSpanKind($this->_mapSpanKind($kind));

            // Continue the caller's trace when incoming W3C context is trusted;
            // otherwise this starts a brand new trace
            $remoteContext = $parentCarrier !== []
                ? $this->_contextFromCarrier($parentCarrier)
                : $this->_extractRemoteContext();
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
            $this->_rootStarted = true;

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
        if (!$this->_enabled || !$this->_tracer || !$this->_rootStarted) {
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
     * Whether cache. profiler timers should create spans (checked by \Maho\Profiler)
     */
    public function isCacheTracingEnabled(): bool
    {
        return $this->_traceCache;
    }

    /**
     * Whether query spans carry the statement text
     */
    public function isQueryTextEnabled(): bool
    {
        return $this->_queryText;
    }

    /**
     * Whether a span started right now would be recorded. Hot paths check this
     * before building anything.
     */
    public function isRecording(): bool
    {
        if (!$this->_enabled || !$this->_rootStarted) {
            return false;
        }
        return $this->getActiveSpan()?->isRecording() ?? false;
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
     * Extract a trusted remote trace context, using the OTEL_PROPAGATORS propagators
     */
    private function _extractRemoteContext(): ?ContextInterface
    {
        try {
            if (!Mage::helper('opentelemetry')->isTrustIncomingTracesEnabled()) {
                return null;
            }
        } catch (\Throwable) {
            return null;
        }

        // Every header is offered; each propagator takes what it knows
        $carrier = [];
        foreach ($_SERVER as $key => $value) {
            if (is_string($value) && str_starts_with($key, 'HTTP_')) {
                $carrier[strtolower(strtr(substr($key, 5), '_', '-'))] = $value;
            }
        }

        return $this->_contextFromCarrier($carrier);
    }

    /**
     * Read a parent context out of propagation headers, null when they carry no valid span context
     *
     * @param array<string, string> $carrier
     */
    private function _contextFromCarrier(array $carrier): ?ContextInterface
    {
        try {
            if (!$this->_propagator) {
                return null;
            }

            $context = $this->_propagator->extract($carrier);

            return ApiSpan::fromContext($context)->getContext()->isValid() ? $context : null;
        } catch (\Throwable) {
            // Malformed context, start a fresh trace instead
            return null;
        }
    }

    /**
     * Build an OTLP transport for one signal, honoring OTEL_EXPORTER_OTLP_PROTOCOL.
     * maxRetries 1: exports are request-scoped, so a retry only holds the worker.
     */
    private function _createTransport(string $endpoint, string $signal): TransportInterface
    {
        $helper = Mage::helper('opentelemetry');
        $protocol = $helper->getProtocol($signal);

        if ($protocol === Protocols::GRPC) {
            Mage::log('OpenTelemetry: OTLP over gRPC is not supported, falling back to http/protobuf', Mage::LOG_WARNING);
            $protocol = Protocols::HTTP_PROTOBUF;
        }

        try {
            $contentType = Protocols::contentType($protocol);
        } catch (\UnexpectedValueException) {
            Mage::log("OpenTelemetry: unknown OTLP protocol \"$protocol\", falling back to http/protobuf", Mage::LOG_WARNING);
            $contentType = Protocols::contentType(Protocols::HTTP_PROTOBUF);
        }

        return (new OtlpHttpTransportFactory())->create(
            $endpoint,
            $contentType,
            $this->_resolveHeaders($helper),
            maxRetries: 1,
        );
    }

    /**
     * Admin headers with OTEL_EXPORTER_OTLP_HEADERS merged over them per key.
     * The SDK map parser does not percent-decode values, so that step stays here.
     */
    private function _resolveHeaders(Maho_OpenTelemetry_Helper_Data $helper): array
    {
        $headers = $helper->getHeaders();
        foreach (Configuration::getMap(Variables::OTEL_EXPORTER_OTLP_HEADERS, []) as $key => $value) {
            $headers[$key] = rawurldecode($value);
        }

        return $headers;
    }

    /**
     * Whether a destination host is allowed to receive the baggage header.
     * An entry matches the host itself or any subdomain of it.
     */
    private function _isBaggageHost(string $host): bool
    {
        $host = strtolower($host);
        return array_any($this->_baggageHosts, fn($allowed) => $host === $allowed || str_ends_with($host, '.' . $allowed));
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
     *
     * @param string $host Destination host; baggage only goes to listed ones
     */
    public function getTracePropagationHeaders(string $host = ''): array
    {
        if (!$this->_enabled) {
            return [];
        }

        $sdkSpan = $this->getActiveSpan()?->getSdkSpan();
        if (!$sdkSpan || !$this->_propagator || !$sdkSpan->getContext()->isValid()) {
            return [];
        }

        try {
            $context = $sdkSpan->storeInContext(Context::getCurrent());

            if ($host !== '' && $this->_isBaggageHost($host)) {
                try {
                    $store = Mage::app()->getStore();
                    $context = Baggage::getBuilder()
                        ->set('maho.store', (string) $store->getCode())
                        ->set('maho.currency', (string) $store->getCurrentCurrencyCode())
                        ->build()
                        ->storeInContext($context);
                } catch (\Throwable) {
                    // Store not initialized — propagate trace context only
                }
            }

            $carrier = [];
            $this->_propagator->inject($carrier, null, $context);

            return $carrier;
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
            } catch (\Throwable) {
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
     * End every span started after the given one, most recent first.
     * A Context scope may only be detached while it is the current one, and
     * profiler timers are not guaranteed to nest.
     */
    public function endSpansAfter(Maho_OpenTelemetry_Model_Span $span): void
    {
        $index = array_search($span, $this->_spanStack, true);
        if ($index === false) {
            return;
        }
        // Popped here rather than in end(), so the loop always terminates
        while (count($this->_spanStack) - 1 > $index) {
            array_pop($this->_spanStack)->end();
        }
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

        // Root closed: shutdown functions must not open a new orphan trace
        if ($this->_spanStack === []) {
            $this->_rootStarted = false;
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
        } catch (\Throwable) {
            // Ignore errors during shutdown
        }
    }
}
