# OpenTelemetry Usage Guide

OpenTelemetry is now fully integrated into Maho and automatically instruments your application through the unified \Maho\Profiler system.

## ✅ Automatic Instrumentation

Maho uses **\Maho\Profiler** as the single source of truth for performance profiling and OpenTelemetry tracing. Every `\Maho\Profiler::start()` call automatically creates an OpenTelemetry span, providing comprehensive tracing across your entire application.

### How It Works

When you call:
```php
\Maho\Profiler::start('operation.name');
// ... your code ...
\Maho\Profiler::stop('operation.name');
```

Two things happen:
1. **\Maho\Profiler** tracks timing and memory usage (as before)
2. **OpenTelemetry** automatically creates a span with the same name

This unified approach means:
- ✅ No duplicate instrumentation code
- ✅ Consistent profiling and tracing
- ✅ Automatic span hierarchy based on call stack
- ✅ 70+ instrumentation points throughout the codebase

### Automatically Traced Operations

The following operations are automatically traced via \Maho\Profiler:

#### HTTP & Routing
- `http.request` - HTTP requests (GET, POST, etc.)
- `app.init.config` - Application configuration loading
- `app.init.system_config` - System configuration initialization
- `app.init.stores` - Store initialization
- `app.init.front_controller` - Front controller setup
- `dispatch.routers_match` - Router matching
- `dispatch.controller.action` - Controller action dispatch
- `dispatch.db_url_rewrite` - Database URL rewrites
- `dispatch.config_url_rewrite` - Config URL rewrites

#### Controller & Layout
- `controller.action.predispatch` - Pre-dispatch hooks
- `controller.action.postdispatch` - Post-dispatch hooks
- `layout.load` - Layout loading
- `layout.generate_xml` - Layout XML generation
- `layout.generate_blocks` - Block hierarchy creation
- `layout.render` - Layout rendering
- `layout.package_update` - Layout package updates
- `layout.db_update` - Layout database updates

#### Blocks & Templates
- `block.generate` - Block generation (with `block.name` attribute)
- Template rendering - Each template file is tracked

#### Models & Collections
- `eav.model.load` - EAV model loading
- `eav.model.after_load` - Post-load processing
- `eav.model.load_attributes` - Attribute loading
- `eav.collection.before_load` - Pre-load collection operations
- `eav.collection.load_entities` - Entity loading
- `eav.collection.load_attributes` - Attribute loading for collections
- `eav.collection.original_data` - Original data setup
- `eav.collection.after_load` - Post-load collection operations
- `eav.attribute.load_by_code` - Loading attributes by code

#### Configuration & Cache
- `config.load_modules` - Module configuration loading
- `config.load_db` - Database configuration
- `config.load_env` - Environment configuration
- `config.load_modules_declaration` - Module declarations
- `cache.url` - URL caching
- `locale.currency` - Currency locale operations

#### Events & Observers
- `observer.execute` - Observer execution (with `observer.name` attribute)

#### Core Operations
- `core.create_object` - Object instantiation (with `class.name` attribute)
- `eav.config.*` - EAV configuration operations

#### Database Queries
- `db.query` - All SQL queries with operation type (SELECT, INSERT, UPDATE, DELETE)

**Location**: The \Maho\Profiler integration is in `lib/Maho/Profiler.php`. All 70+ profiler calls throughout `app/code/core/` automatically create OpenTelemetry spans.

## 📊 Adding Custom Instrumentation

### Method 1: Using \Maho\Profiler (Recommended)

The easiest way to add tracing is through \Maho\Profiler:

```php
// Basic usage
\Maho\Profiler::start('product.complex_calculation');
// ... your code ...
\Maho\Profiler::stop('product.complex_calculation');
```

**With OpenTelemetry attributes:**
```php
\Maho\Profiler::start('product.price_calculation', [
    'product.id' => $productId,
    'product.sku' => $product->getSku(),
    'calculation.type' => 'tiered',
]);
// ... your code ...
\Maho\Profiler::stop('product.price_calculation');
```

Benefits:
- ✅ Works with both profiler and OpenTelemetry
- ✅ Automatically nested based on call stack
- ✅ Simple API
- ✅ Consistent with core instrumentation

### Method 2: Using Mage::startSpan() Directly

For operations that don't need profiling, use OpenTelemetry directly:

```php
$span = Mage::startSpan('operation.name', [
    'attribute.key' => 'value',
]);

try {
    // Your code here
    $span->setStatus('ok');
} catch (Exception $e) {
    $span->recordException($e);
    $span->setStatus('error', $e->getMessage());
} finally {
    $span->end();
}
```

### Common Patterns

#### Product Operations
```php
\Maho\Profiler::start('product.load', [
    'product.id' => $productId,
]);
$product = Mage::getModel('catalog/product')->load($productId);
\Maho\Profiler::stop('product.load');
```

#### Customer Login
```php
\Maho\Profiler::start('customer.login', [
    'customer.email' => $email,
]);

try {
    $customer = Mage::getModel('customer/customer')
        ->setWebsiteId(Mage::app()->getWebsite()->getId())
        ->loadByEmail($email);

    if ($customer->validatePassword($password)) {
        // Login successful
    }
} finally {
    \Maho\Profiler::stop('customer.login');
}
```

#### Order Processing
```php
\Maho\Profiler::start('order.process', [
    'order.id' => $order->getId(),
    'order.total' => $order->getGrandTotal(),
]);

// Process order...

\Maho\Profiler::stop('order.process');
```

#### External API Calls
```php
$span = Mage::startSpan('api.payment_gateway', [
    'api.endpoint' => $url,
    'api.method' => 'POST',
    'order.id' => $orderId,
]);

$client = \Symfony\Component\HttpClient\HttpClient::create();
try {
    $response = $client->request('POST', $url, ['json' => $data]);
    $span->setAttribute('api.status_code', $response->getStatusCode());
    $span->setStatus('ok');
} catch (\Throwable $e) {
    $span->recordException($e);
    $span->setStatus('error', 'Payment gateway failed');
    throw $e;
} finally {
    $span->end();
}
```

## 🔧 Configuration

OpenTelemetry can be configured in two ways:

### Option 1: Admin Panel (Recommended)

Go to **System** → **Configuration** → **Developer** → **OpenTelemetry**

Configure the following settings:
- **Enable Tracing**: Yes/No toggle
- **Service Name**: Identifier for your service (e.g., `maho-production`)
- **OTLP Endpoint**: Full URL ending with `/v1/traces`
- **Sampling Rate**: Float between 0.0 and 1.0 (1.0 = 100%, 0.1 = 10%)
- **Authorization Header**: Auth header value (e.g., `Basic [base64-credentials]`)
- **Custom Headers**: Additional headers, one per line (format: `Key: Value`)

Benefits:
- Easy to configure without editing files
- Changes take effect immediately (no file uploads needed)
- Authorization header is encrypted in database
- Visual validation and helpful descriptions

### Option 2: XML Configuration File

Add to `app/etc/local.xml` under `default/dev/opentelemetry`:

```xml
<default>
    <dev>
        <opentelemetry>
            <enabled>1</enabled>
            <service_name>maho-local</service_name>
            <endpoint>https://otlp-gateway-prod-eu-west-2.grafana.net/otlp/v1/traces</endpoint>
            <sampling_rate>1.0</sampling_rate><!-- 100% = trace everything, 0.1 = trace 10% -->
            <auth_header>Basic [your-base64-encoded-credentials]</auth_header>
            <!-- Optional custom headers -->
            <custom_headers><![CDATA[X-Custom-Header: value1
X-Another-Header: value2]]></custom_headers>
        </opentelemetry>
    </dev>
</default>
```

**Note:** The XML path (`default/dev/opentelemetry`) matches the admin panel path, so settings are consistent between both methods.

Use this method for:
- Version-controlled configuration
- Multi-environment deployments
- When you can't access admin panel

### Configuration Priority

Settings are read in this order (highest priority first):

1. **Admin Panel Settings** (System → Configuration → Developer → OpenTelemetry) - Stored in database
2. **XML Configuration** (`app/etc/local.xml` → `default/dev/opentelemetry`) - File-based defaults

Both methods use the same config path (`dev/opentelemetry`), so they're interchangeable. Settings saved in the admin panel are stored in the database (`core_config_data` table) and take precedence over XML defaults.

For multi-environment deployments, use separate `local.xml` files per environment, or configure via admin panel per environment.

## 📈 Viewing Traces in Grafana Cloud

1. Go to **Drilldown** → **Traces**
2. Select time range (e.g., "Last 30 minutes")
3. Click the **"Traces"** tab to see all traces
4. Click on any trace to see the waterfall view

### TraceQL Queries

Search for specific traces using TraceQL:

```traceql
# All traces for your service
{ resource.service.name="maho-local" }

# Traces with errors
{ resource.service.name="maho-local" && status=error }

# Traces for specific operations
{ resource.service.name="maho-local" && name="eav.collection.load_entities" }

# Slow traces (> 1 second)
{ resource.service.name="maho-local" && duration > 1s }

# Traces with specific attributes
{ resource.service.name="maho-local" && span.http.method="POST" }

# Observer execution traces
{ resource.service.name="maho-local" && name="observer.execute" }

# Block generation traces
{ resource.service.name="maho-local" && name="block.generate" }
```

## 🎯 Best Practices

1. **Use \Maho\Profiler for most instrumentation**: It provides both profiling and tracing with a simple API

2. **Follow naming conventions**: Use lowercase with dots (e.g., `product.load`, `customer.login`, `payment.authorize`)

3. **Add meaningful attributes**: Include IDs, SKUs, types - but be mindful of PII (Personally Identifiable Information)

4. **Don't over-instrument**: The core already traces 70+ operations. Focus on business-specific operations (custom calculations, integrations, complex workflows)

5. **Use sampling in production**: Set `sampling_rate` to 0.1 (10%) or lower to reduce data volume and costs

6. **Name spans by operation, not by identifier**:
   - ✅ Good: `product.load` with `product.id` attribute
   - ❌ Bad: `product.load.123` (creates too many unique span names)

7. **Always stop profiler timers**: Unmatched start/stop calls can cause issues

## 🚀 Production Deployment

1. Change `service_name` to identify your environment:
   ```xml
   <service_name>maho-production</service_name>
   ```

2. Adjust sampling rate:
   ```xml
   <sampling_rate>0.1</sampling_rate><!-- 10% sampling -->
   ```

3. Ensure credentials are secure (use environment variables if possible)

4. Monitor Grafana Cloud for performance bottlenecks and errors

## 🐛 Troubleshooting

### No traces appearing?

1. Check configuration: `Mage::helper('opentelemetry')->isEnabled()` should return `true`
2. Check endpoint URL includes `/v1/traces`
3. Verify authorization header is correct
4. Check `var/log/system.log` for errors
5. Verify sampling rate isn't too low (start with 1.0 for testing)

### Too much data?

- Reduce `sampling_rate` to 0.1 (10%) or lower
- Remove custom instrumentation for high-frequency operations
- Focus tracing on specific request types using sampling strategies

### Performance impact?

- OpenTelemetry has minimal overhead when disabled
- When enabled with sampling, impact is negligible (<1% CPU)
- Spans are batched and sent asynchronously
- \Maho\Profiler itself has minimal overhead (< 0.5% CPU)

### Spans not properly nested?

- Ensure you're using matching profiler names for start/stop
- Check that you're calling stop() in the same execution path as start()
- Use try/finally blocks to ensure stop() is always called

## 🔍 Architecture

**Unified Instrumentation:**
- `\Maho\Profiler::start()` creates both a profiler timer AND an OpenTelemetry span
- `\Maho\Profiler::stop()` stops the timer AND ends the span
- Single source of truth: `lib/Maho/Profiler.php`

**Span Hierarchy:**
- Spans are automatically nested based on the call stack
- Parent-child relationships are preserved
- Root span is typically the HTTP request

**Benefits:**
- No code duplication
- Consistent naming across profiling and tracing
- Easy to add new instrumentation points
- Existing profiler calls automatically become traced
