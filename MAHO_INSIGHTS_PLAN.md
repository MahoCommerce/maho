# Maho Insights - Self-Hosted Monitoring & Error Tracking

**Goal:** Build a self-hosted alternative to Sentry/New Relic specifically for Maho, with a simple, focused UI.

## Why This is Better Than Using External Services

1. **Self-hosted** - No data leaves your server
2. **Free** - No monthly fees
3. **Simpler** - Focused on what Maho users actually need
4. **E-commerce focused** - Track orders, carts, checkouts (not just generic errors)
5. **No configuration** - Works out of the box
6. **Privacy-friendly** - Customer data stays private

## Current Status

✅ **Phase 1: Data Collection** (COMPLETE)
- OpenTelemetry instrumentation in place
- Tracking: DB queries, HTTP requests, errors, request lifecycle
- Configurable via environment variables
- Zero overhead when disabled

## Roadmap

### Phase 2: Local Storage (Next)

**Goal:** Store telemetry data in Maho's database instead of sending to external services.

**Database Schema:**

```sql
-- Traces table
CREATE TABLE maho_insights_traces (
    trace_id VARCHAR(32) PRIMARY KEY,
    parent_trace_id VARCHAR(32),
    span_name VARCHAR(255),
    start_time TIMESTAMP,
    end_time TIMESTAMP,
    duration_ms INT,
    status ENUM('ok', 'error'),
    attributes JSON,
    store_id INT,
    customer_id INT,
    INDEX idx_start_time (start_time),
    INDEX idx_status (status),
    INDEX idx_customer (customer_id)
);

-- Errors table
CREATE TABLE maho_insights_errors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    error_hash VARCHAR(64) UNIQUE, -- Hash of file+line+type
    error_type VARCHAR(255),
    error_message TEXT,
    file_path VARCHAR(512),
    line_number INT,
    stack_trace JSON,
    first_seen TIMESTAMP,
    last_seen TIMESTAMP,
    occurrence_count INT DEFAULT 1,
    status ENUM('open', 'resolved', 'ignored') DEFAULT 'open',
    INDEX idx_status (status),
    INDEX idx_last_seen (last_seen)
);

-- Error occurrences (for detailed context)
CREATE TABLE maho_insights_error_occurrences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    error_hash VARCHAR(64),
    trace_id VARCHAR(32),
    occurred_at TIMESTAMP,
    request_url VARCHAR(512),
    request_method VARCHAR(10),
    user_agent TEXT,
    customer_id INT,
    store_id INT,
    context JSON, -- Request params, session data, etc.
    FOREIGN KEY (error_hash) REFERENCES maho_insights_errors(error_hash),
    INDEX idx_occurred_at (occurred_at)
);

-- Performance metrics (aggregated)
CREATE TABLE maho_insights_metrics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    metric_name VARCHAR(255),
    metric_value FLOAT,
    timestamp TIMESTAMP,
    dimensions JSON, -- {store_id: 1, route: "checkout/cart"}
    INDEX idx_metric_time (metric_name, timestamp)
);

-- Business events (e-commerce specific)
CREATE TABLE maho_insights_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_type VARCHAR(100), -- order_placed, cart_abandoned, checkout_started
    event_data JSON,
    occurred_at TIMESTAMP,
    customer_id INT,
    store_id INT,
    trace_id VARCHAR(32),
    INDEX idx_event_type (event_type, occurred_at)
);
```

**Implementation:**

1. Create `Maho_Insights` module (separate from OpenTelemetry)
2. Implement local storage exporter:
   ```php
   class Maho_Insights_Model_Exporter
   {
       public function exportTrace(Trace $trace): void
       {
           // Store trace in maho_insights_traces
       }

       public function exportError(Throwable $e, array $context): void
       {
           // Hash error by file+line
           // Update occurrence count
           // Store context
       }
   }
   ```

3. Update `Maho_OpenTelemetry_Model_Tracer` to use local exporter when configured:
   ```php
   public function flush(): void
   {
       if ($this->useLocalStorage()) {
           Mage::getModel('insights/exporter')->exportTraces($this->_spanStack);
       } else {
           // Send to OTLP endpoint (existing behavior)
       }
   }
   ```

### Phase 3: Admin Panel UI

**Menu Structure:**

```
Maho Admin
  └─ System
      └─ Insights 🔍
          ├─ Dashboard (overview)
          ├─ Errors (like Sentry)
          ├─ Performance (slow requests)
          ├─ Traces (request flow)
          └─ Business Events (orders, carts, etc.)
```

**Admin Controllers:**

```
app/code/core/Maho/Insights/
├─ controllers/
│   └─ Adminhtml/
│       ├─ DashboardController.php
│       ├─ ErrorsController.php
│       ├─ PerformanceController.php
│       ├─ TracesController.php
│       └─ EventsController.php
├─ Block/
│   └─ Adminhtml/
│       ├─ Dashboard.php
│       ├─ Errors/
│       │   ├─ Grid.php
│       │   └─ Detail.php
│       └─ ...
```

**Dashboard View (Simple!):**

```html
<!-- No complex charts, just useful info -->
<div class="insights-dashboard">
    <div class="metric-cards">
        <div class="card error">
            <h3>🔴 Errors (24h)</h3>
            <div class="number">23</div>
            <div class="trend">↑ 5 from yesterday</div>
        </div>

        <div class="card performance">
            <h3>⚡ Avg Response Time</h3>
            <div class="number">234ms</div>
            <div class="trend">↓ 12% faster</div>
        </div>

        <div class="card business">
            <h3>🛒 Orders (24h)</h3>
            <div class="number">156</div>
            <div class="trend">↑ 12% from yesterday</div>
        </div>

        <div class="card warnings">
            <h3>⚠️ Slow Queries</h3>
            <div class="number">8</div>
            <div class="trend">Queries over 500ms</div>
        </div>
    </div>

    <div class="recent-errors">
        <h3>Recent Errors</h3>
        <table>
            <tr>
                <td>Call to undefined method</td>
                <td>ProductController.php:45</td>
                <td>5 min ago</td>
                <td><a href="...">View</a></td>
            </tr>
            <!-- ... -->
        </table>
    </div>

    <div class="slow-requests">
        <h3>Slowest Requests (last hour)</h3>
        <table>
            <tr>
                <td>/checkout/cart</td>
                <td>1.2s</td>
                <td>15 queries</td>
                <td><a href="...">Trace</a></td>
            </tr>
            <!-- ... -->
        </table>
    </div>
</div>
```

**Error Detail View (Like Sentry but Simpler):**

```html
<div class="error-detail">
    <h2>Call to undefined method Product::getNonExistentMethod()</h2>

    <div class="error-meta">
        <span>First seen: 2 hours ago</span>
        <span>Last seen: 5 minutes ago</span>
        <span>Occurrences: 23</span>
        <span>Status: <select><option>Open</option><option>Resolved</option></select></span>
    </div>

    <div class="stack-trace">
        <h3>Stack Trace</h3>
        <pre>
app/code/core/Mage/Catalog/controllers/ProductController.php:45
app/code/core/Mage/Catalog/Model/Product.php:234
app/code/core/Mage/Catalog/Model/Resource/Product/Collection.php:89
        </pre>
    </div>

    <div class="context">
        <h3>Request Context (Latest Occurrence)</h3>
        <table>
            <tr><th>URL</th><td>/catalog/product/view/id/123</td></tr>
            <tr><th>Customer</th><td>customer@example.com (ID: 456)</td></tr>
            <tr><th>Store</th><td>Default Store View</td></tr>
            <tr><th>Time</th><td>2025-01-04 14:32:15</td></tr>
        </table>
    </div>

    <div class="recent-occurrences">
        <h3>Recent Occurrences (last 10)</h3>
        <table>
            <tr>
                <td>5 min ago</td>
                <td>customer@example.com</td>
                <td><a href="...">View Trace</a></td>
            </tr>
            <!-- ... -->
        </table>
    </div>
</div>
```

### Phase 4: E-Commerce Specific Features

**Business Event Tracking:**

```php
// Automatically track important e-commerce events
Mage::dispatchEvent('checkout_onepage_controller_success_action', [...]);
// → Records in maho_insights_events

// Track:
- Order placed ✅
- Order failed ❌
- Cart abandoned (no activity for 1h)
- Checkout started but not completed
- Product added to cart
- Product removed from cart
- Payment gateway errors
- Shipping calculation errors
```

**E-Commerce Dashboard:**

```html
<div class="business-metrics">
    <h3>Conversion Funnel (Last 24h)</h3>
    <div class="funnel">
        <div class="step">Product Views: 1,234</div>
        <div class="step">Add to Cart: 456 (37%)</div>
        <div class="step">Checkout Started: 234 (51%)</div>
        <div class="step">Orders Completed: 156 (67%)</div>
    </div>

    <div class="alerts">
        ⚠️ Cart abandonment rate increased 15% (investigate!)
        ⚠️ PayPal errors: 8 failed payments in last hour
    </div>
</div>
```

### Phase 5: Alerting (Optional)

**Simple alerts via email/Slack:**

```php
// app/code/core/Maho/Insights/Model/Alert.php
class Maho_Insights_Model_Alert
{
    public function checkRules(): void
    {
        // Error rate spike
        if ($this->getErrorRate() > $threshold) {
            $this->notify('Error rate spike: 50 errors in last 5 minutes');
        }

        // Performance degradation
        if ($this->getAvgResponseTime() > 1000) {
            $this->notify('Performance alert: Avg response time > 1s');
        }

        // Business metrics
        if ($this->getFailedCheckouts() > 10) {
            $this->notify('⚠️ 10+ failed checkouts in last hour');
        }
    }
}
```

## Why This Approach is Better Than External APM

### Sentry ($29-$80/month):
- ❌ Monthly costs
- ❌ Data sent to external servers
- ❌ Generic (not e-commerce focused)
- ✅ But: Great UI, battle-tested

### Maho Insights (Free, Self-Hosted):
- ✅ FREE
- ✅ Self-hosted (privacy)
- ✅ E-commerce specific
- ✅ Simpler UI (focused on what you need)
- ✅ No configuration needed
- ❌ But: Need to build and maintain

## Configuration

**Simple toggle in admin:**

```
System > Configuration > Developer > Maho Insights
├─ Enable Insights: Yes/No
├─ Storage Mode:
│   ○ Local Database (recommended)
│   ○ External OTLP (for advanced users)
├─ Data Retention: 30 days
├─ Enable Error Tracking: Yes
├─ Enable Performance Tracking: Yes
├─ Enable Business Events: Yes
└─ Alert Email: admin@example.com
```

## Performance Impact

- **When disabled:** 0 overhead (same as now)
- **When enabled:**
  - Data collection: <1ms per request (already implemented)
  - Database writes: Async (after response sent)
  - Storage: ~1MB per 1000 requests
  - Admin panel: No impact on frontend

## Timeline Estimate

- Phase 2 (Storage): 2-3 days
- Phase 3 (Admin UI): 3-5 days
- Phase 4 (E-commerce features): 2-3 days
- Phase 5 (Alerting): 1-2 days

**Total: ~2 weeks for full implementation**

## Next Steps

1. ✅ Keep existing OpenTelemetry instrumentation (we have this!)
2. Create database schema for Maho Insights
3. Build local storage exporter
4. Create basic admin panel with error list
5. Add performance tracking UI
6. Add e-commerce specific features

## Comparison: What You're Building vs Commercial Tools

| Feature | Sentry | New Relic | Maho Insights |
|---------|--------|-----------|---------------|
| Error tracking | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐☆ |
| Performance | ⭐⭐⭐☆☆ | ⭐⭐⭐⭐⭐ | ⭐⭐⭐☆☆ |
| E-commerce metrics | ⭐☆☆☆☆ | ⭐⭐☆☆☆ | ⭐⭐⭐⭐⭐ |
| UI simplicity | ⭐⭐⭐☆☆ | ⭐⭐☆☆☆ | ⭐⭐⭐⭐⭐ |
| Self-hosted | ❌ | ❌ | ✅ |
| Cost | $29-80/mo | $100+/mo | FREE |
| Privacy | ❌ | ❌ | ✅ |

**Bottom line:** Maho Insights won't be as powerful as commercial tools, but it will be:
- Free
- Private
- Simpler
- E-commerce focused
- Good enough for 90% of Maho users
