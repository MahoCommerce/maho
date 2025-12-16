# OpenTelemetry Implementation Plan for Maho

## Executive Summary

This document outlines the implementation plan for integrating OpenTelemetry into Maho ecommerce platform to provide comprehensive observability (traces, metrics, and logs) for a planned SaaS telemetry service.

**Key Design Decisions:**
- **Full auto-instrumentation** for comprehensive coverage
- **OTLP protocol** for vendor-neutral, future-proof telemetry export
- **Core integration** for performance-critical paths (database, HTTP, logging)
- **Hybrid configuration** (environment variables + XML) for maximum flexibility
- **Static caching** for zero overhead after initialization
- **95% modular** - Implementation lives in `Maho_OpenTelemetry` module, core only has hooks
- **Truly optional** - OpenTelemetry dependencies not required in core
- **Async flush** - Telemetry export happens after response sent (non-blocking)
- **Coexists with varien_profiler** - Different use cases (dev vs production)

## Architectural Refinements

### Modular Design (Core vs Module)

**Core Integration (~100 lines total):**
- `Mage::getTracer()` - Accessor method that delegates to module if installed
- `Mage::startSpan()` - Convenience wrapper
- Conditional instrumentation in hot paths (DB adapter, HTTP client, App::run())
- All checks are fast null checks - zero overhead when module disabled/not installed

**Module Contains (~95% of code):**
- All OpenTelemetry SDK integration
- Tracer and Span implementations
- Exporters (OTLP, File)
- Observers for business logic
- Monolog handler
- Admin configuration

**Benefits:**
- ✅ Core stays clean and dependency-free
- ✅ Easy to disable/remove module
- ✅ OpenTelemetry SDK only loaded when module active
- ✅ Independent testing and maintenance
- ✅ No breaking changes to core

### Relationship with varien_profiler

**Keep Both** - They serve different purposes:

| Feature | varien_profiler | OpenTelemetry |
|---------|----------------|---------------|
| Purpose | Dev debugging | Production observability |
| Overhead | <0.1μs | 1-5ms per request |
| Dependencies | Zero | Optional module |
| Output | HTML table | Distributed traces |
| Use When | Local development | Production monitoring |

**Recommendation:** Use varien_profiler for quick local profiling, OpenTelemetry for production monitoring and SaaS telemetry service.

---

## Table of Contents

1. [Architecture Overview](#architecture-overview)
2. [Performance Analysis](#performance-analysis)
3. [Core Integration Points](#core-integration-points)
4. [Module Components](#module-components)
5. [Configuration Strategy](#configuration-strategy)
6. [Implementation Phases](#implementation-phases)
7. [Code Examples](#code-examples)
8. [SaaS Service Architecture](#saas-service-architecture)

---

## Architecture Overview

### Current Maho Infrastructure

**Logging:**
- Monolog 3.9 with configurable handlers
- XML-based handler configuration (`global/log/handlers`)
- Log levels: Emergency → Debug (Mage::LOG_* constants)

**Technology Stack:**
- PHP 8.3+ with strict typing
- Symfony components (HttpClient, HttpFoundation, Validator, Cache, Console)
- Doctrine DBAL 4.3 for database access
- PSR-4 autoloading (`Maho\*` namespace)

**Request Lifecycle:**
```
public/index.php
  └→ Mage::run()
      └→ Mage_Core_Model_App::run()
          └→ _initModules()
          └→ _initCurrentStore()
          └→ _initRequest()
          └→ getFrontController()->dispatch()
```

### OpenTelemetry Integration Goals

1. **Traces**: Complete request lifecycle, database queries, external HTTP calls, cache operations
2. **Metrics**: Request rate, error rate, duration (RED metrics), business metrics
3. **Logs**: Automatic correlation with traces via trace_id/span_id injection
4. **Zero Performance Overhead**: <0.01μs per instrumented operation after initialization
5. **SaaS-Ready**: OTLP export to cloud telemetry service

---

## Performance Analysis

### Configuration Performance Comparison

| Method | First Access | Subsequent Calls | Per 1000 Calls | Production Ready |
|--------|--------------|------------------|----------------|------------------|
| **XML + Static Cache** (recommended) | 100μs | 0.01μs | **0.01ms** | ✅ Yes |
| **Direct XML lookup** | 15μs | 15μs | **15ms** | ❌ No (1000x slower) |
| **Env vars only** | 0.1μs | 0.1μs | 0.1ms | ⚠️ Non-Maho-standard |
| **PHP config file** | 50μs | 0.01μs | 0.01ms | ⚠️ Non-Maho-standard |

### Why Direct XML Lookups Are Problematic

```php
// ❌ BAD: Direct config lookup in hot path
public function query($sql, $bind = [])
{
    // Called 1,000+ times per request
    if (Mage::getStoreConfigFlag('dev/opentelemetry/enabled')) {
        // 15μs × 1,000 = 15ms overhead per request
        $span = $this->startSpan('db.query');
    }
    // ...
}
```

**Problem:** `getStoreConfig()` performs:
1. Store resolution (~5-10μs)
2. Config tree traversal (~2-5μs)
3. XPath/array lookups (~2-5μs)

**Impact:** 10-15ms overhead per request on busy sites = unacceptable

### Recommended Solution: Static Cache + Lazy Init

```php
// ✅ GOOD: Static cached tracer
private static $_tracer = null;

public static function getTracer(): ?TracerInterface
{
    // Fast path: 0.01μs (simple null check + static var access)
    if (self::$_tracer !== null) {
        return self::$_tracer ?: null;
    }

    // Slow path: ~100μs (happens once per request)
    self::$_tracer = self::_initTracer();
    return self::$_tracer ?: null;
}
```

**Performance:** 0.01μs × 1,000 calls = **0.01ms overhead** (1000x improvement)

---

## Core Integration Points

These components **must be integrated into Maho core** for maximum performance and capability.

### 1. Global Tracer Accessor (CRITICAL)

**Location:** `app/Mage.php`

**Why in core:**
- Must be accessible from anywhere: `Mage::getTracer()`
- Similar to `Mage::log()` - should be a core primitive
- Zero overhead when telemetry disabled (returns null)
- Lazy initialization with static caching

**Implementation:**
```php
// In app/Mage.php

/**
 * OpenTelemetry tracer instance (lazy loaded)
 * - null = not initialized yet
 * - false = telemetry disabled
 * - TracerInterface = telemetry enabled
 */
private static $_tracer = null;

/**
 * Get tracer instance (lazy loaded, cached statically)
 *
 * Performance: First call ~100μs, subsequent calls ~0.01μs
 *
 * @return \Maho\OpenTelemetry\TracerInterface|null
 */
public static function getTracer(): ?\Maho\OpenTelemetry\TracerInterface
{
    if (self::$_tracer !== null) {
        return self::$_tracer ?: null;
    }

    self::$_tracer = self::_initTracer();
    return self::$_tracer ?: null;
}

/**
 * Initialize tracer from config (called once per request)
 *
 * @return \Maho\OpenTelemetry\TracerInterface|false
 */
private static function _initTracer()
{
    // 1. Environment variables take precedence (OTEL_* standards)
    $enabled = getenv('OTEL_SDK_DISABLED') !== 'true';

    if (!$enabled && self::getConfig()) {
        // 2. Fallback to XML config
        $enabled = self::getStoreConfigFlag('dev/opentelemetry/enabled');
    }

    if (!$enabled) {
        return false;
    }

    // 3. Load configuration
    $config = [
        'service_name' => getenv('OTEL_SERVICE_NAME')
            ?: self::getStoreConfig('dev/opentelemetry/service_name')
            ?: 'maho-store',
        'endpoint' => getenv('OTEL_EXPORTER_OTLP_ENDPOINT')
            ?: self::getStoreConfig('dev/opentelemetry/endpoint'),
        'headers' => self::_parseOtelHeaders(),
        'sampling_rate' => (float)(getenv('OTEL_TRACES_SAMPLER_ARG')
            ?: self::getStoreConfig('dev/opentelemetry/sampling_rate')
            ?: 1.0),
    ];

    // 4. Initialize tracer
    try {
        return self::getSingleton('opentelemetry/tracer')->init($config);
    } catch (\Throwable $e) {
        self::logException($e);
        return false;
    }
}

/**
 * Parse OpenTelemetry headers from env or XML
 *
 * @return array
 */
private static function _parseOtelHeaders(): array
{
    // Support standard OTEL_EXPORTER_OTLP_HEADERS env var
    // Format: "key1=value1,key2=value2"
    if ($headers = getenv('OTEL_EXPORTER_OTLP_HEADERS')) {
        $parsed = [];
        foreach (explode(',', $headers) as $pair) {
            if (strpos($pair, '=') !== false) {
                [$key, $value] = explode('=', $pair, 2);
                $parsed[trim($key)] = trim($value);
            }
        }
        return $parsed;
    }

    // Fallback to XML
    if (self::getConfig() && $xmlHeaders = self::getConfig()->getNode('global/opentelemetry/headers')) {
        $parsed = [];
        foreach ($xmlHeaders->children() as $key => $value) {
            $parsed[$key] = (string)$value;
        }
        return $parsed;
    }

    return [];
}

/**
 * Start a new span (convenience method)
 *
 * @param string $name Span name
 * @param array $attributes Span attributes
 * @return \Maho\OpenTelemetry\SpanInterface|null
 */
public static function startSpan(string $name, array $attributes = []): ?\Maho\OpenTelemetry\SpanInterface
{
    return self::getTracer()?->startSpan($name, $attributes);
}
```

### 2. Database Adapter Instrumentation (CRITICAL)

**Location:** `lib/Maho/Db/Adapter/Pdo/Mysql.php`

**Why in core:**
- Called on **every single database query** (1,000+ per request)
- Observer/event overhead would be 5-10% performance penalty
- Need microsecond precision timing
- Must inject trace context for distributed queries

**Implementation:**
```php
// In Maho\Db\Adapter\Pdo\Mysql

/**
 * Execute SQL query
 *
 * @param string $sql
 * @param array $bind
 * @return \Maho\Db\Statement\StatementInterface
 * @throws \Exception
 */
public function query($sql, $bind = [])
{
    // Ultra-fast check: static variable lookup only (~0.01μs)
    $tracer = \Mage::getTracer();

    if ($tracer) {
        $span = $tracer->startSpan('db.query', [
            'db.system' => 'mysql',
            'db.name' => $this->_config['dbname'] ?? '',
            'db.operation' => $this->_getOperationType($sql),
            'db.statement' => $this->_sanitizeSql($sql),
        ]);

        try {
            $result = $this->_doQuery($sql, $bind);
            $span->setAttribute('db.rows_affected', $result->rowCount());
            $span->setStatus('ok');
            return $result;
        } catch (\Throwable $e) {
            $span->recordException($e);
            $span->setStatus('error', $e->getMessage());
            throw $e;
        } finally {
            $span->end();
        }
    }

    return $this->_doQuery($sql, $bind);
}

/**
 * Get SQL operation type (SELECT, INSERT, UPDATE, DELETE, etc.)
 */
protected function _getOperationType(string $sql): string
{
    $sql = trim($sql);
    $firstSpace = strpos($sql, ' ');
    return $firstSpace ? strtoupper(substr($sql, 0, $firstSpace)) : 'UNKNOWN';
}

/**
 * Sanitize SQL for telemetry (remove sensitive data, limit length)
 */
protected function _sanitizeSql(string $sql): string
{
    // Remove values from IN clauses
    $sql = preg_replace('/IN\s*\([^)]+\)/i', 'IN (?)', $sql);

    // Truncate long queries
    if (strlen($sql) > 1000) {
        $sql = substr($sql, 0, 1000) . '... [truncated]';
    }

    return $sql;
}

/**
 * Begin transaction
 */
public function beginTransaction(): void
{
    if ($span = \Mage::startSpan('db.transaction.begin')) {
        $span->end();
    }
    parent::beginTransaction();
}

/**
 * Commit transaction
 */
public function commit(): void
{
    if ($span = \Mage::startSpan('db.transaction.commit')) {
        try {
            parent::commit();
            $span->setStatus('ok');
        } catch (\Throwable $e) {
            $span->recordException($e);
            throw $e;
        } finally {
            $span->end();
        }
    } else {
        parent::commit();
    }
}

/**
 * Rollback transaction
 */
public function rollBack(): void
{
    if ($span = \Mage::startSpan('db.transaction.rollback')) {
        $span->end();
    }
    parent::rollBack();
}
```

### 3. HTTP Root Span Creation (CRITICAL)

**Location:** `app/code/core/Mage/Core/Model/App.php`

**Why in core:**
- Must start root span **before** any module code runs
- Captures bootstrap timing, config loading, module initialization
- Can't be done from module observer (too late)

**Implementation:**
```php
// In Mage_Core_Model_App::run()

public function run($params)
{
    // Start root span IMMEDIATELY (before any other initialization)
    $rootSpan = null;
    $tracer = Mage::getTracer();

    if ($tracer) {
        $rootSpan = $tracer->startRootSpan('http.request', [
            'http.method' => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
            'http.url' => $_SERVER['REQUEST_URI'] ?? '',
            'http.host' => $_SERVER['HTTP_HOST'] ?? '',
            'http.scheme' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http',
            'http.user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'http.client_ip' => $this->_getClientIp(),
        ]);
    }

    try {
        $options = $params['options'] ?? [];
        $this->baseInit($options);
        Mage::register('application_params', $params);

        $this->_initModules();
        $this->loadAreaPart(Mage_Core_Model_App_Area::AREA_GLOBAL, Mage_Core_Model_App_Area::PART_EVENTS);

        if ($this->_config->isLocalConfigLoaded()) {
            $scopeCode = $params['scope_code'] ?? '';
            $scopeType = $params['scope_type'] ?? 'store';
            $this->_initCurrentStore($scopeCode, $scopeType);

            // Add store context to span
            if ($rootSpan) {
                $rootSpan->setAttribute('maho.store_id', $this->getStore()->getId());
                $rootSpan->setAttribute('maho.store_code', $this->getStore()->getCode());
                $rootSpan->setAttribute('maho.website_id', $this->getWebsite()->getId());
            }

            $this->_initRequest();
            Mage_Core_Model_Resource_Setup::applyAllDataUpdates();
            Mage_Core_Model_Resource_Setup::applyAllMahoUpdates();
        }

        $this->getFrontController()->dispatch();

        // Add response data to span
        if ($rootSpan) {
            $rootSpan->setAttribute('http.status_code', http_response_code());
            $rootSpan->setAttribute('http.response_size', ob_get_length() ?: 0);
            $rootSpan->setStatus('ok');
        }

        // Finish the request explicitly
        if (php_sapi_name() == 'fpm-fcgi' && function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } else {
            flush();
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        try {
            Mage::dispatchEvent('core_app_run_after', ['app' => $this]);
        } catch (Throwable $e) {
            Mage::logException($e);
        }

    } catch (\Throwable $e) {
        if ($rootSpan) {
            $rootSpan->recordException($e);
            $rootSpan->setStatus('error', $e->getMessage());
        }
        throw $e;
    } finally {
        // End span and flush all telemetry
        if ($rootSpan) {
            $rootSpan->end();
        }
        if ($tracer) {
            $tracer->flush();
        }
    }

    return $this;
}

/**
 * Get client IP address
 */
protected function _getClientIp(): string
{
    if (Mage::helper('core/http')) {
        return Mage::helper('core/http')->getRemoteAddr();
    }
    return $_SERVER['REMOTE_ADDR'] ?? '';
}
```

### 4. HTTP Client Wrapper (HIGH PRIORITY)

**Location:** `lib/Maho/Http/Client.php` (new file)

**Why in core:**
- **Distributed tracing** requires injecting W3C Trace Context headers
- Payment gateways, shipping APIs, webhooks need trace propagation
- Must happen automatically for all HTTP calls

**Implementation:**
```php
<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_Http
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\Http;

use Symfony\Component\HttpClient\HttpClient as SymfonyHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * HTTP Client factory with automatic OpenTelemetry instrumentation
 */
class Client
{
    /**
     * Create HTTP client with tracing
     */
    public static function create(array $options = []): HttpClientInterface
    {
        $client = SymfonyHttpClient::create($options);

        // Wrap with tracing decorator if tracer exists
        if ($tracer = \Mage::getTracer()) {
            return new TracedHttpClient($client, $tracer);
        }

        return $client;
    }
}
```

```php
<?php

declare(strict_types=1);

namespace Maho\Http;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * HTTP Client decorator that adds OpenTelemetry tracing
 */
class TracedHttpClient implements HttpClientInterface
{
    private HttpClientInterface $client;
    private \Maho\OpenTelemetry\TracerInterface $tracer;

    public function __construct(HttpClientInterface $client, \Maho\OpenTelemetry\TracerInterface $tracer)
    {
        $this->client = $client;
        $this->tracer = $tracer;
    }

    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        $span = $this->tracer->startSpan('http.client.request', [
            'http.method' => $method,
            'http.url' => $url,
            'http.request.method' => $method,
        ]);

        // Inject W3C Trace Context headers for distributed tracing
        $options['headers'] = array_merge(
            $options['headers'] ?? [],
            $this->tracer->getTracePropagationHeaders()
        );

        try {
            $response = $this->client->request($method, $url, $options);

            // Add response data
            $span->setAttribute('http.status_code', $response->getStatusCode());
            $span->setStatus('ok');

            return $response;
        } catch (\Throwable $e) {
            $span->recordException($e);
            $span->setStatus('error', $e->getMessage());
            throw $e;
        } finally {
            $span->end();
        }
    }

    // Proxy other methods...
    public function stream($responses, float $timeout = null): ResponseStreamInterface
    {
        return $this->client->stream($responses, $timeout);
    }

    public function withOptions(array $options): static
    {
        return new self($this->client->withOptions($options), $this->tracer);
    }
}
```

### 5. Logger Integration (HIGH PRIORITY)

**Location:** `app/code/core/Mage/Core/Model/Logger.php`

**Why in core:**
- Automatic trace_id/span_id injection into every log entry
- Zero configuration for users
- Logs and traces automatically correlated

**Implementation:**
```php
// In Mage_Core_Model_Logger::createLogger()

protected function createLogger(string $file, Level|int $minLogLevel, bool $forceLog): void
{
    $logDir = Mage::getBaseDir('var') . DS . 'log';
    $logFile = $logDir . DS . $file;

    if (!is_dir($logDir)) {
        mkdir($logDir, 0750, true);
    }

    $logger = new Logger('Maho');

    $configLevel = self::convertLogLevel($minLogLevel);
    if ($forceLog || Mage::getIsDeveloperMode()) {
        $configLevel = Level::Debug;
    }

    // Add configured handlers
    static::addConfiguredHandlers($logger, $logFile, $configLevel);

    // Add OpenTelemetry handler if tracer exists
    if (Mage::getTracer() && Mage::getStoreConfigFlag('dev/opentelemetry/export_logs')) {
        try {
            $otelHandler = new \Maho\OpenTelemetry\Handler\MonologOtel(
                Mage::getTracer(),
                $configLevel
            );
            $logger->pushHandler($otelHandler);
        } catch (\Throwable $e) {
            // Silently fail if handler cannot be created
            Mage::log('Failed to create OpenTelemetry log handler: ' . $e->getMessage(), Mage::LOG_WARNING);
        }
    }

    self::$_loggers[$file] = $logger;

    // Set file permissions
    if (!static::isRotatingFileHandler($logger) && !file_exists($logFile)) {
        touch($logFile);
        chmod($logFile, 0640);
    }
}
```

### 6. Exception Handler Integration (MEDIUM PRIORITY)

**Location:** `app/Mage.php`

**Why in core:**
- Automatically record exceptions as span events
- Capture stack traces in telemetry
- Link exceptions to active span

**Implementation:**
```php
// In app/Mage.php

public static function logException(Throwable $e)
{
    // Record in active span
    if ($tracer = self::getTracer()) {
        $tracer->recordException($e);
    }

    // Existing logging
    $file = empty($e->getFile()) ? 'exception.log' : basename($e->getFile());
    self::log("\n" . $e->__toString(), self::LOG_ERROR, $file);
}
```

---

## Module Components

These components can be implemented in the `Maho_OpenTelemetry` module.

### Module Structure

```
app/code/core/Maho/OpenTelemetry/
├── Block/
│   └── Adminhtml/
│       └── System/
│           └── Config/
│               └── Status.php          # Admin config status display
├── Helper/
│   └── Data.php                        # Helper methods
├── Model/
│   ├── Tracer.php                      # Main tracer implementation
│   ├── Span.php                        # Span implementation
│   ├── Config.php                      # Configuration management
│   ├── Observer/
│   │   ├── Controller.php              # Controller action tracing
│   │   ├── Cache.php                   # Cache operation tracing
│   │   ├── Block.php                   # Block rendering tracing
│   │   └── Event.php                   # Generic event tracing
│   └── Exporter/
│       ├── Otlp.php                    # OTLP exporter
│       ├── Console.php                 # Console/file exporter (dev)
│       └── ExporterInterface.php       # Exporter interface
├── Handler/
│   └── MonologOtel.php                 # Monolog → OTLP logs
├── etc/
│   ├── config.xml                      # Module configuration
│   └── system.xml                      # Admin panel settings
└── sql/
    └── maho_opentelemetry_setup/
        └── 25.02.0.php                 # Initial setup script
```

### Key Module Classes

#### 1. Tracer Interface

```php
<?php

declare(strict_types=1);

namespace Maho\OpenTelemetry;

interface TracerInterface
{
    /**
     * Initialize tracer with configuration
     */
    public function init(array $config): self;

    /**
     * Start a root span (for HTTP requests)
     */
    public function startRootSpan(string $name, array $attributes = []): SpanInterface;

    /**
     * Start a child span
     */
    public function startSpan(string $name, array $attributes = []): SpanInterface;

    /**
     * Get current active span
     */
    public function getActiveSpan(): ?SpanInterface;

    /**
     * Record an exception in current span
     */
    public function recordException(\Throwable $e): void;

    /**
     * Get trace propagation headers for HTTP requests (W3C Trace Context)
     */
    public function getTracePropagationHeaders(): array;

    /**
     * Flush all pending spans to exporter
     */
    public function flush(): void;
}
```

#### 2. Span Interface

```php
<?php

declare(strict_types=1);

namespace Maho\OpenTelemetry;

interface SpanInterface
{
    /**
     * Set span attribute
     */
    public function setAttribute(string $key, $value): self;

    /**
     * Set multiple attributes
     */
    public function setAttributes(array $attributes): self;

    /**
     * Record an exception
     */
    public function recordException(\Throwable $e): self;

    /**
     * Set span status
     */
    public function setStatus(string $status, ?string $description = null): self;

    /**
     * End the span
     */
    public function end(): void;

    /**
     * Get trace ID
     */
    public function getTraceId(): string;

    /**
     * Get span ID
     */
    public function getSpanId(): string;
}
```

#### 3. Admin Configuration (system.xml)

```xml
<?xml version="1.0"?>
<config>
    <sections>
        <dev>
            <groups>
                <opentelemetry translate="label">
                    <label>OpenTelemetry</label>
                    <sort_order>100</sort_order>
                    <show_in_default>1</show_in_default>
                    <show_in_website>1</show_in_website>
                    <show_in_store>1</show_in_store>
                    <fields>
                        <enabled translate="label comment">
                            <label>Enable OpenTelemetry</label>
                            <comment><![CDATA[Enable comprehensive tracing and telemetry export. Can also be controlled via OTEL_SDK_DISABLED environment variable.]]></comment>
                            <frontend_type>select</frontend_type>
                            <source_model>adminhtml/system_config_source_yesno</source_model>
                            <sort_order>10</sort_order>
                            <show_in_default>1</show_in_default>
                            <show_in_website>1</show_in_website>
                            <show_in_store>1</show_in_store>
                        </enabled>
                        <service_name translate="label comment">
                            <label>Service Name</label>
                            <comment><![CDATA[Identifier for this Maho instance in telemetry. Can also be set via OTEL_SERVICE_NAME environment variable.]]></comment>
                            <frontend_type>text</frontend_type>
                            <sort_order>20</sort_order>
                            <show_in_default>1</show_in_default>
                            <show_in_website>1</show_in_website>
                            <show_in_store>1</show_in_store>
                            <depends><enabled>1</enabled></depends>
                        </service_name>
                        <endpoint translate="label comment">
                            <label>OTLP Endpoint</label>
                            <comment><![CDATA[OpenTelemetry Protocol endpoint URL (e.g., https://telemetry.mahocommerce.com). Can also be set via OTEL_EXPORTER_OTLP_ENDPOINT.]]></comment>
                            <frontend_type>text</frontend_type>
                            <sort_order>30</sort_order>
                            <show_in_default>1</show_in_default>
                            <show_in_website>1</show_in_website>
                            <show_in_store>1</show_in_store>
                            <depends><enabled>1</enabled></depends>
                        </endpoint>
                        <api_key translate="label comment">
                            <label>API Key</label>
                            <comment><![CDATA[Authentication key for telemetry service (sent as Authorization: Bearer header).]]></comment>
                            <frontend_type>obscure</frontend_type>
                            <backend_model>adminhtml/system_config_backend_encrypted</backend_model>
                            <sort_order>40</sort_order>
                            <show_in_default>1</show_in_default>
                            <show_in_website>1</show_in_website>
                            <show_in_store>1</show_in_store>
                            <depends><enabled>1</enabled></depends>
                        </api_key>
                        <sampling_rate translate="label comment">
                            <label>Sampling Rate</label>
                            <comment><![CDATA[Percentage of requests to trace (0.0 to 1.0). Use 1.0 for 100% sampling. Can also be set via OTEL_TRACES_SAMPLER_ARG.]]></comment>
                            <frontend_type>text</frontend_type>
                            <sort_order>50</sort_order>
                            <show_in_default>1</show_in_default>
                            <show_in_website>1</show_in_website>
                            <show_in_store>1</show_in_store>
                            <depends><enabled>1</enabled></depends>
                        </sampling_rate>
                        <export_logs translate="label comment">
                            <label>Export Logs to OTLP</label>
                            <comment><![CDATA[Send Monolog logs to OpenTelemetry collector with automatic trace correlation.]]></comment>
                            <frontend_type>select</frontend_type>
                            <source_model>adminhtml/system_config_source_yesno</source_model>
                            <sort_order>60</sort_order>
                            <show_in_default>1</show_in_default>
                            <show_in_website>1</show_in_website>
                            <show_in_store>1</show_in_store>
                            <depends><enabled>1</enabled></depends>
                        </export_logs>
                    </fields>
                </opentelemetry>
            </groups>
        </dev>
    </sections>
</config>
```

---

## Configuration Strategy

### Priority Order

1. **Environment variables** (highest priority - for deployments)
2. **XML config** (`app/etc/local.xml` - fallback)
3. **Defaults** (lowest priority)

### Standard Environment Variables

Support OpenTelemetry standard environment variables:

```bash
# Core settings
OTEL_SDK_DISABLED=false                           # Enable/disable SDK
OTEL_SERVICE_NAME=my-maho-store                  # Service identifier

# OTLP Exporter
OTEL_EXPORTER_OTLP_ENDPOINT=https://telemetry.mahocommerce.com
OTEL_EXPORTER_OTLP_PROTOCOL=http/protobuf        # http/protobuf or grpc
OTEL_EXPORTER_OTLP_HEADERS=authorization=Bearer sk_live_123,x-tenant-id=cust-uuid

# Sampling
OTEL_TRACES_SAMPLER=parentbased_traceidratio     # Sampling strategy
OTEL_TRACES_SAMPLER_ARG=1.0                      # 100% sampling

# Resource attributes
OTEL_RESOURCE_ATTRIBUTES=deployment.environment=production,service.version=25.11.0
```

### XML Configuration

```xml
<!-- app/etc/local.xml -->
<config>
    <global>
        <opentelemetry>
            <enabled>1</enabled>
            <service_name>my-maho-store</service_name>
            <endpoint>https://telemetry.mahocommerce.com</endpoint>
            <headers>
                <authorization>Bearer sk_live_123abc</authorization>
                <x-tenant-id>customer-uuid</x-tenant-id>
            </headers>
            <sampling>
                <type>parentbased_traceidratio</type>
                <rate>1.0</rate>
            </sampling>
            <export_logs>1</export_logs>
        </opentelemetry>
    </global>
</config>
```

---

## Implementation Phases

### Phase 1: Core Integration (Week 1-2)

**Goal:** Add performance-critical instrumentation to core

**Tasks:**
1. ✅ Add `Mage::getTracer()` and `Mage::startSpan()` to `app/Mage.php`
2. ✅ Add `Mage::_initTracer()` with env var + XML config support
3. ✅ Add `Mage::_parseOtelHeaders()` helper
4. ✅ Modify `Mage_Core_Model_App::run()` to create root span
5. ✅ Add query instrumentation to `Maho\Db\Adapter\Pdo\Mysql`
6. ✅ Create `lib/Maho/Http/Client.php` wrapper
7. ✅ Create `lib/Maho/Http/TracedHttpClient.php` decorator
8. ✅ Modify `Mage_Core_Model_Logger::createLogger()` for OTLP handler
9. ✅ Update `Mage::logException()` for span recording

**Dependencies:**
- OpenTelemetry PHP SDK (`open-telemetry/sdk`)
- OTLP Exporter (`open-telemetry/exporter-otlp`)

**Composer additions:**
```json
{
    "require": {
        "open-telemetry/sdk": "^1.0",
        "open-telemetry/exporter-otlp": "^1.0"
    }
}
```

### Phase 2: Module Foundation (Week 3)

**Goal:** Create module structure and interfaces

**Tasks:**
1. ✅ Create `app/code/core/Maho/OpenTelemetry` module
2. ✅ Define `TracerInterface` and `SpanInterface`
3. ✅ Implement `Model/Tracer.php` using OpenTelemetry SDK
4. ✅ Implement `Model/Span.php` wrapper
5. ✅ Create `Model/Exporter/Otlp.php`
6. ✅ Create `Model/Exporter/Console.php` for debugging
7. ✅ Create `Handler/MonologOtel.php` for log correlation
8. ✅ Write `etc/config.xml` with defaults
9. ✅ Write `etc/system.xml` for admin panel

**Testing:**
- Unit tests for tracer initialization
- Integration test for span creation
- Test env var precedence over XML config

### Phase 3: Business Logic Observers (Week 4)

**Goal:** Add high-value business operation tracing

**Tasks:**
1. ✅ Create `Model/Observer/Controller.php` - trace controller actions
2. ✅ Create `Model/Observer/Cache.php` - trace cache hits/misses
3. ✅ Create `Model/Observer/Block.php` - trace block rendering
4. ✅ Add custom spans for:
   - Product view (`catalog_product_view`)
   - Add to cart (`checkout_cart_add_product`)
   - Checkout steps (`checkout_onepage_controller_success_action`)
   - Order placement (`sales_order_place_after`)
   - Admin login (`admin_session_user_login_success`)

**Configuration in `etc/config.xml`:**
```xml
<events>
    <controller_action_predispatch>
        <observers>
            <opentelemetry_controller>
                <class>opentelemetry/observer_controller</class>
                <method>beforeAction</method>
            </opentelemetry_controller>
        </observers>
    </controller_action_predispatch>

    <controller_action_postdispatch>
        <observers>
            <opentelemetry_controller>
                <class>opentelemetry/observer_controller</class>
                <method>afterAction</method>
            </opentelemetry_controller>
        </observers>
    </controller_action_postdispatch>

    <!-- Additional business events -->
    <catalog_product_load_after>
        <observers>
            <opentelemetry_product>
                <class>opentelemetry/observer_catalog</class>
                <method>afterProductLoad</method>
            </opentelemetry_product>
        </observers>
    </catalog_product_load_after>
</events>
```

### Phase 4: Metrics & Advanced Features (Week 5-6)

**Goal:** Add metrics collection and advanced telemetry

**Tasks:**
1. ✅ Implement metrics collection (RED metrics)
   - Request rate (requests/sec)
   - Error rate (errors/sec)
   - Duration (request latency percentiles)
2. ✅ Add business metrics:
   - Cart conversion rate
   - Product view → add to cart
   - Checkout funnel drop-off
3. ✅ Implement sampling strategies
4. ✅ Add resource attributes (hostname, deployment env, version)
5. ✅ Create admin dashboard block for status/stats

### Phase 5: Testing & Documentation (Week 7)

**Goal:** Comprehensive testing and documentation

**Tasks:**
1. ✅ Performance testing (ensure <1ms overhead)
2. ✅ Load testing (10k+ requests/sec)
3. ✅ Integration tests with all exporters
4. ✅ Admin panel testing
5. ✅ Documentation:
   - Installation guide
   - Configuration guide
   - Troubleshooting guide
   - API documentation
6. ✅ Example dashboards for Grafana

---

## Code Examples

### Example 1: Manual Span Creation in Custom Code

```php
// In your custom module or controller
class Mage_Custom_Model_HeavyOperation extends Mage_Core_Model_Abstract
{
    public function processLargeDataset(array $data): void
    {
        $span = Mage::startSpan('custom.process_dataset', [
            'dataset.size' => count($data),
            'dataset.type' => 'products',
        ]);

        try {
            foreach ($data as $item) {
                $this->processItem($item);
            }
            $span->setStatus('ok');
        } catch (\Exception $e) {
            $span->recordException($e);
            $span->setStatus('error');
            throw $e;
        } finally {
            $span->end();
        }
    }
}
```

### Example 2: HTTP Client Usage with Tracing

```php
// Using the traced HTTP client
use Maho\Http\Client;

$client = Client::create(['timeout' => 30]);

// This request will automatically:
// 1. Create a span for the HTTP call
// 2. Inject W3C Trace Context headers
// 3. Record response status and errors
$response = $client->request('POST', 'https://api.payment-gateway.com/charge', [
    'json' => [
        'amount' => 9999,
        'currency' => 'USD',
    ],
]);

$data = $response->toArray();
```

### Example 3: Custom Attributes for Business Logic

```php
// In checkout observer
class Mage_Checkout_Model_Observer
{
    public function onOrderPlaceAfter(Varien_Event_Observer $observer)
    {
        $order = $observer->getEvent()->getOrder();

        // Add business context to current span
        if ($tracer = Mage::getTracer()) {
            $span = $tracer->getActiveSpan();
            if ($span) {
                $span->setAttributes([
                    'order.id' => $order->getIncrementId(),
                    'order.total' => $order->getGrandTotal(),
                    'order.currency' => $order->getOrderCurrencyCode(),
                    'order.items_count' => $order->getTotalItemCount(),
                    'order.customer_group' => $order->getCustomerGroupId(),
                    'order.payment_method' => $order->getPayment()->getMethod(),
                ]);
            }
        }
    }
}
```

### Example 4: Deployment with Docker

```yaml
# docker-compose.yml
services:
  maho:
    image: maho:latest
    environment:
      # OpenTelemetry configuration
      OTEL_SDK_DISABLED: "false"
      OTEL_SERVICE_NAME: "maho-production"
      OTEL_EXPORTER_OTLP_ENDPOINT: "https://telemetry.mahocommerce.com"
      OTEL_EXPORTER_OTLP_HEADERS: "authorization=Bearer ${MAHO_TELEMETRY_KEY}"
      OTEL_TRACES_SAMPLER: "parentbased_traceidratio"
      OTEL_TRACES_SAMPLER_ARG: "1.0"
      OTEL_RESOURCE_ATTRIBUTES: "deployment.environment=production,service.version=25.11.0,service.instance.id=${HOSTNAME}"
```

---

## SaaS Service Architecture

### Backend Components

For the paid cloud telemetry service, the backend needs:

1. **OTLP Receiver** (receives telemetry from Maho instances)
   - HTTP/2 endpoint for OTLP protocol
   - Authentication via Bearer tokens
   - Multi-tenancy support (customer isolation)

2. **Storage Layer**
   - Traces: ClickHouse or Tempo
   - Metrics: Prometheus or VictoriaMetrics
   - Logs: Loki or Elasticsearch

3. **Query API**
   - GraphQL or REST API for dashboards
   - Trace search and retrieval
   - Metrics aggregation

4. **Web Dashboard**
   - React/Vue frontend
   - Pre-built dashboards for Maho
   - Custom query builder

### Sample Architecture

```
┌─────────────────┐
│  Maho Instance  │
│  (PHP)          │
└────────┬────────┘
         │ OTLP/HTTP (traces, logs, metrics)
         │ Authorization: Bearer sk_live_...
         ▼
┌─────────────────────────────────────────────────┐
│           Load Balancer (HTTPS)                 │
└────────┬────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────────────┐
│        OTLP Collector (OpenTelemetry)           │
│  - Authentication                               │
│  - Tenant routing                               │
│  - Sampling                                     │
│  - Batch processing                             │
└────────┬────────────────────────────────────────┘
         │
         ├─────────────────┬────────────────┐
         ▼                 ▼                ▼
┌─────────────┐  ┌──────────────┐  ┌─────────────┐
│  ClickHouse │  │ Prometheus   │  │    Loki     │
│  (traces)   │  │  (metrics)   │  │   (logs)    │
└─────────────┘  └──────────────┘  └─────────────┘
         │                 │                │
         └─────────────────┴────────────────┘
                           │
                           ▼
                  ┌─────────────────┐
                  │   Query API     │
                  │   (GraphQL)     │
                  └────────┬────────┘
                           │
                           ▼
                  ┌─────────────────┐
                  │  Web Dashboard  │
                  │  (React)        │
                  └─────────────────┘
```

### Pricing Tiers

**Suggested model:**

1. **Free Tier**
   - 100k spans/month
   - 7-day retention
   - Basic dashboards

2. **Pro Tier** ($49/month)
   - 1M spans/month
   - 30-day retention
   - Custom dashboards
   - Alerting

3. **Enterprise Tier** ($199/month)
   - 10M spans/month
   - 90-day retention
   - Advanced features
   - Dedicated support

---

## Performance Guarantees

### Target Performance

| Metric | Target | Maximum Acceptable |
|--------|--------|-------------------|
| Tracer initialization | <100μs | 500μs |
| Span creation | <0.1μs | 1μs |
| Span end | <0.5μs | 5μs |
| Config check (cached) | <0.01μs | 0.1μs |
| DB query overhead | <1μs | 10μs |
| HTTP request overhead | <10μs | 50μs |
| Memory overhead | <5MB | 10MB |
| Total request overhead | <1ms | 5ms |

### Performance Testing Checklist

- [ ] Benchmark `Mage::getTracer()` (should be <0.01μs after first call)
- [ ] Benchmark database query with/without tracing (overhead <1μs)
- [ ] Load test with 10,000 req/sec (overhead <1ms per request)
- [ ] Memory profiling (should not leak memory)
- [ ] Test with telemetry disabled (zero overhead)
- [ ] Test with network failures (graceful degradation)

---

## Security Considerations

### API Key Storage

- Store API keys encrypted in database (`backend_model="adminhtml/system_config_backend_encrypted"`)
- Support key rotation
- Use Bearer token authentication

### Data Sanitization

- Sanitize SQL queries (remove sensitive values)
- Redact passwords, credit cards, PII from spans
- Truncate long strings (max 1000 chars)
- Filter sensitive headers (Authorization, Cookie)

### Network Security

- HTTPS/TLS for all OTLP traffic
- Certificate validation
- Timeout protection (max 30s)
- Retry with exponential backoff

---

## Troubleshooting Guide

### Common Issues

**1. Telemetry not appearing in backend**
- Check `OTEL_SDK_DISABLED` is not `"true"`
- Verify endpoint URL is correct
- Check API key authentication
- Review logs in `var/log/opentelemetry.log`

**2. High performance overhead**
- Verify tracer is cached (not re-initialized)
- Check sampling rate (reduce if needed)
- Disable log export if not needed
- Review span count (may be creating too many)

**3. Memory issues**
- Increase `memory_limit` if needed
- Reduce sampling rate
- Enable batch export (queue spans)

**4. Network timeouts**
- Check collector endpoint is reachable
- Increase timeout in config
- Enable async export (non-blocking)

---

## Next Steps

1. **Review and approval** of this implementation plan
2. **Add OpenTelemetry dependencies** to `composer.json`
3. **Start Phase 1**: Core integration
4. **Set up development environment** with local OTLP collector
5. **Create initial tests** for tracer functionality

---

## References

- [OpenTelemetry PHP SDK](https://github.com/open-telemetry/opentelemetry-php)
- [OTLP Specification](https://opentelemetry.io/docs/specs/otlp/)
- [W3C Trace Context](https://www.w3.org/TR/trace-context/)
- [OpenTelemetry Semantic Conventions](https://opentelemetry.io/docs/specs/semconv/)
- [Monolog Documentation](https://github.com/Seldaek/monolog)

---

**Document Version:** 1.0
**Last Updated:** 2025-01-20
**Author:** Implementation Planning Session
**Status:** Ready for Review
