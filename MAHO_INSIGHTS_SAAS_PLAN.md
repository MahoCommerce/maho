# Maho Insights - SaaS Business Plan

**Product:** Cloud monitoring & error tracking service for Maho stores
**Website:** insights.mahocommerce.com
**Business Model:** Freemium SaaS ($0-$299/month)

## Value Proposition

**For Maho store owners:**
- Know about errors before customers complain
- Understand why checkouts fail
- See revenue impact of technical issues
- Simpler than New Relic/Sentry (focused on e-commerce)
- No infrastructure to maintain

**For Maho project:**
- Recurring revenue stream
- Incentive to keep improving Maho core
- Data insights across all Maho stores
- Professional service offering

## Market Positioning

| Service | Target | Price | Focus |
|---------|--------|-------|-------|
| Sentry | Developers | $29-80/mo | Generic errors |
| New Relic | Enterprises | $100+/mo | Complex APM |
| **Maho Insights** | **Store Owners** | **$0-99/mo** | **E-commerce** |

**Key differentiation:**
- Built specifically for Maho (knows e-commerce context)
- Simpler interface (store owners, not developers)
- Shows business impact (lost revenue, not just error counts)
- Includes benchmarking across Maho ecosystem

## Revenue Model

### Pricing Tiers

**Free**
- 1 store
- 1,000 errors/month
- 7 day retention
- Basic dashboard
- Email alerts
→ Target: Testing, hobby stores, developer advocacy

**Starter - $29/month**
- 3 stores
- 50,000 errors/month
- 30 day retention
- Performance insights
- Slack/Discord alerts
- Business metrics
→ Target: Small-medium stores (<$50k/month)

**Professional - $99/month**
- 10 stores
- 500,000 errors/month
- 90 day retention
- Advanced analytics
- Custom dashboards
- API access
- Priority support
→ Target: Agencies, multi-store operations

**Enterprise - $299+/month**
- Unlimited stores
- Unlimited events
- Custom retention
- SLA guarantee
- Dedicated support
- On-premise option
- Custom integrations
→ Target: Large retailers, high-traffic stores

### Revenue Projections

**Conservative (Year 1):**
- 1,000 free users
- 50 paid users @ $50 avg/mo = $2,500/month = **$30k/year**

**Moderate (Year 2):**
- 5,000 free users
- 300 paid users @ $60 avg/mo = $18,000/month = **$216k/year**

**Optimistic (Year 3):**
- 15,000 free users
- 1,000 paid users @ $70 avg/mo = $70,000/month = **$840k/year**

**Costs:**
- Infrastructure: ~$500-2000/month (scales with usage)
- Development: 1-2 devs
- Support: Part-time → Full-time
- Marketing: $1000-5000/month

## Technical Architecture

### Stack Recommendation

**Backend (Data Collection & Processing):**
```
Language: Go (or Node.js/TypeScript)
Why: High performance, great concurrency, lower costs

Components:
├─ OTLP Collector (receives traces from stores)
├─ API Server (dashboard backend)
├─ Background Workers (data processing)
└─ Alert Engine (anomaly detection)

Infrastructure:
├─ Cloud: AWS/GCP/DigitalOcean
├─ Database: PostgreSQL + TimescaleDB
├─ Cache: Redis
├─ Queue: Redis or RabbitMQ
└─ Storage: S3-compatible (for long-term retention)
```

**Frontend (Dashboard):**
```
Framework: Next.js (React) + TypeScript
Why: SEO-friendly, fast, great developer experience

UI:
├─ TailwindCSS (styling)
├─ shadcn/ui (components)
├─ Recharts (simple charts)
└─ React Query (data fetching)

Auth: Clerk or NextAuth.js
Deployment: Vercel (frontend) + AWS (backend)
```

### Database Schema (Simplified)

```sql
-- Accounts & Billing
accounts (id, email, created_at, plan, stripe_customer_id)
stores (id, account_id, name, api_key, domain)
subscriptions (id, account_id, plan, status, current_period_end)

-- Telemetry Data
traces (
  id, store_id, trace_id, parent_id,
  name, start_time, duration_ms,
  status, attributes JSONB,
  created_at
)

-- Errors (grouped)
error_groups (
  id, store_id, error_hash,
  type, message, file, line,
  first_seen, last_seen,
  occurrence_count,
  status, impact_score
)

error_occurrences (
  id, error_group_id, trace_id,
  occurred_at, context JSONB
)

-- Metrics (aggregated)
metrics_hourly (
  store_id, metric_name, hour,
  value, count, min, max, avg
)

-- Business Events
events (
  id, store_id, event_type,
  occurred_at, data JSONB,
  revenue_impact DECIMAL
)
```

### API Endpoints

**Public API (for Maho stores):**
```
POST /v1/traces
- Receives OTLP data
- Validates API key
- Rate limited per plan

POST /v1/events
- Custom business events
- Optional: manual error reporting
```

**Dashboard API (for web app):**
```
GET  /api/stores
GET  /api/stores/:id/errors
GET  /api/stores/:id/performance
GET  /api/stores/:id/metrics
POST /api/errors/:id/resolve
GET  /api/alerts
POST /api/alerts/configure
```

## Customer Integration (Maho Side)

### What Changes in Maho OpenTelemetry Module

**Current** (what we built):
```bash
export OTEL_EXPORTER_OTLP_ENDPOINT=http://localhost:4318
```

**For SaaS:**
```bash
export OTEL_EXPORTER_OTLP_ENDPOINT=https://collector.mahoinsights.com
export OTEL_EXPORTER_OTLP_HEADERS="authorization=Bearer maho_live_abc123xyz"
```

That's it! The OpenTelemetry implementation we built already supports this.

### Enhanced: Add business context

Small enhancement to send e-commerce specific data:

```php
// app/code/core/Maho/OpenTelemetry/Model/Tracer.php

public function addBusinessContext(array $context): void
{
    // Add to all future spans
    $this->_globalAttributes = array_merge(
        $this->_globalAttributes,
        $context
    );
}

// Usage in checkout controller:
Mage::getTracer()?->addBusinessContext([
    'maho.order_id' => $order->getId(),
    'maho.order_total' => $order->getGrandTotal(),
    'maho.customer_id' => $order->getCustomerId(),
]);
```

### Admin Panel Integration

Add simple config in Maho admin:

```
System > Configuration > Developer > Maho Insights

┌──────────────────────────────────────────┐
│ Maho Insights Configuration              │
├──────────────────────────────────────────┤
│                                          │
│ Enable Maho Insights:  [✓] Yes          │
│                                          │
│ API Key: [maho_live_abc123...]           │
│   → Get your API key at                  │
│     insights.mahocommerce.com            │
│                                          │
│ Store Name: [My Production Store    ]   │
│                                          │
│ Environment: [○ Production               │
│              [○ Staging                  │
│              [○ Development              │
│                                          │
│ [Test Connection]  [Save Config]         │
│                                          │
│ Status: ✅ Connected                     │
│ Last Event: 2 minutes ago                │
│                                          │
│ → View Dashboard                         │
└──────────────────────────────────────────┘
```

This writes to `app/etc/local.xml` (gitignored):
```xml
<dev>
    <opentelemetry>
        <enabled>1</enabled>
        <endpoint>https://collector.mahoinsights.com</endpoint>
        <api_key>maho_live_abc123xyz</api_key>
        <service_name>my-production-store</service_name>
    </opentelemetry>
</dev>
```

## Go-to-Market Strategy

### Phase 1: Private Beta (Month 1-2)
- Invite 10-20 early Maho users
- Free access in exchange for feedback
- Iterate on UX based on feedback
- Goal: Prove value, refine features

### Phase 2: Public Launch (Month 3)
- Launch at insights.mahocommerce.com
- Announce on Maho blog, Twitter, forums
- Free tier for everyone
- Content: "How to monitor your Maho store"
- Goal: 100 signups, 10 paid

### Phase 3: Growth (Month 4-12)
- Content marketing (SEO)
  - "Maho performance monitoring"
  - "E-commerce error tracking"
  - "Magento to Maho migration monitoring"
- Integrations:
  - Slack notifications
  - PagerDuty
  - Discord webhooks
- Partnerships with Maho agencies
- Goal: 1,000 free users, 50 paid

### Phase 4: Scale (Year 2+)
- Enterprise features
- White-label for agencies
- Marketplace (community dashboards/alerts)
- API for custom integrations
- Goal: 10,000 users, 500 paid

## Competitive Advantages

**vs Sentry:**
- ✅ E-commerce context (orders, revenue, carts)
- ✅ Simpler (no overwhelming dev tools)
- ✅ Cheaper for small stores
- ❌ Less mature
- ❌ Only works with Maho

**vs New Relic:**
- ✅ MUCH simpler
- ✅ MUCH cheaper
- ✅ E-commerce specific
- ❌ Less powerful
- ❌ Fewer integrations

**vs Self-hosted (Grafana/Jaeger):**
- ✅ Zero maintenance
- ✅ Works immediately
- ✅ Professional support
- ❌ Monthly cost
- ❌ Data leaves customer server

**Unique value:**
- **Benchmarking**: "Your checkout conversion: 67% (Maho avg: 58%)"
- **E-commerce alerts**: "8 failed payments in last hour ⚠️"
- **Revenue impact**: "This error cost you $450 in lost sales"
- **Maho-specific**: Understands Maho architecture

## Success Metrics

### Product Metrics:
- Time to first value: <5 minutes (signup → seeing first error)
- DAU/MAU ratio: >30% (users checking daily)
- Retention: >70% after 3 months
- NPS: >50

### Business Metrics:
- Free → Paid conversion: 5-10%
- Churn: <5% monthly
- LTV:CAC ratio: >3:1
- ARR growth: 100%+ year-over-year

## Risks & Mitigations

**Risk 1: Not enough Maho users**
- Mitigation: Start free, grow with Maho adoption
- Mitigation: Offer during migration (Magento → Maho)

**Risk 2: Customers prefer self-hosted**
- Mitigation: Offer on-premise for enterprise
- Mitigation: Emphasize ease-of-use vs cost

**Risk 3: Complex to build**
- Mitigation: MVP first (errors only)
- Mitigation: Use open source components (OpenTelemetry Collector)

**Risk 4: Infrastructure costs**
- Mitigation: Start small (DigitalOcean)
- Mitigation: Usage-based pricing
- Mitigation: Aggressive data retention limits on free tier

## MVP Feature Set (First Version)

**Must Have:**
- ✅ Error tracking (grouping, stack traces)
- ✅ Basic dashboard (error list, counts)
- ✅ Email alerts
- ✅ Multi-store support
- ✅ API key authentication

**Should Have:**
- ⚡ Performance monitoring (slow requests)
- 📊 Simple charts (errors over time)
- 🔔 Slack notifications
- 💳 Stripe billing integration

**Nice to Have:**
- 📈 Business metrics (orders, revenue)
- 🎯 Custom alerts
- 🔍 Request traces
- 📊 Benchmarking

**Later:**
- 🤖 AI-powered insights
- 📱 Mobile app
- 🔌 API for custom integrations
- 🏢 On-premise deployment

## Timeline to Launch

**Week 1-2: Backend MVP**
- Set up OTLP collector
- Database schema
- API key validation
- Store traces/errors

**Week 3-4: Dashboard MVP**
- User auth (sign up/login)
- Error list view
- Error detail view
- Basic charts

**Week 5-6: Billing & Polish**
- Stripe integration
- Plan limits enforcement
- Email alerts
- Landing page

**Week 7-8: Beta Testing**
- Invite 10-20 users
- Fix bugs
- Iterate on UX

**Week 9-10: Launch**
- Public announcement
- Documentation
- Support setup

**Total: ~2.5 months to launch**

## Next Steps

1. ✅ **Keep OpenTelemetry instrumentation** (perfect for SaaS)
2. **Validate market**: Survey Maho users (would they pay?)
3. **Build landing page**: Gauge interest before coding
4. **MVP backend**: OTLP collector + PostgreSQL
5. **MVP frontend**: Simple dashboard (errors only)
6. **Private beta**: 10 users
7. **Launch**: insights.mahocommerce.com

## Questions to Answer

Before building:
- **Will Maho users pay for this?** (survey)
- **What price points make sense?** ($19? $29? $49?)
- **What features matter most?** (errors? performance? business metrics?)
- **Self-hosted vs cloud preference?** (might need both)
- **Who's the buyer?** (developers, store owners, agencies?)

Want me to help with any of these next steps?
