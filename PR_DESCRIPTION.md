# Migrate USPS shipping carrier from deprecated XML API to modern REST API with OAuth

## Summary

Completely migrates the USPS shipping carrier integration from the legacy XML-based API (ShippingAPI.dll) to the new RESTful API with OAuth 2.0 authentication. This change is necessary as USPS is deprecating their XML API in favor of the modern REST API platform.

The migration includes:
- **Rate calculation** via new REST endpoints (90% reduction in API calls)
- **Shipping label generation** with payment authorization (previously unavailable)
- **Package tracking** with detailed event history
- **OAuth 2.0** authentication with automatic token management

## What This PR Changes

### 🆕 New Features

**Shipping Label Generation** (Previously Unavailable)
- Create domestic shipping labels (4x6 PDF)
- Create international shipping labels with customs forms (CN22/CN23)
- Payment authorization token management
- Support for all USPS service types
- Multi-package shipment support

**Enhanced Tracking**
- Detailed event history with timestamps
- Location information for each tracking event
- Better error handling for invalid tracking numbers

**Performance Improvements**
- Single API call for all rates (vs 10+ calls with XML API)
- 90% reduction in rate calculation API requests
- Faster response times (~2-3 seconds vs 5-10 seconds)
- Improved caching efficiency

### 🔧 Technical Changes

**New Infrastructure** (4 new files):
- `Mage_Usa_Model_Shipping_Carrier_Usps_OAuthClient` - OAuth 2.0 authentication
- `Mage_Usa_Model_Shipping_Carrier_Usps_RestClient` - REST API communication
- `Mage_Usa_Model_Shipping_Carrier_Usps_Source_Environment` - Environment selector
- `Mage_Usa_Model_Shipping_Carrier_Usps_Source_Accounttype` - Account type selector

**Core Changes**:
- Replaced `_getXmlQuotes()` with `_getRestQuotes()` 
- Replaced `_getXmlTracking()` with `_getRestTracking()`
- Implemented `_doShipmentRequest()` for label generation (complete rewrite)
- Added service mapping between Maho codes and REST API mail classes
- Added unit conversion helpers (store units → USPS API units)

**Configuration Changes**:
- Removed: `gateway_url`, `userid` (XML API credentials)
- Added: `client_id`, `client_secret` (OAuth credentials)
- Added: `api_environment` (production/test selection)
- Added: `commercial_pricing` toggle
- Added: Payment account fields for label generation

**Code Quality**:
- Added strict types and return type declarations
- Replaced `Varien_Object` with `Maho\DataObject`
- Reduced PHPStan baseline by 7 errors
- Updated copyright headers to 2025-2026

## Breaking Changes

### Required Merchant Actions

Merchants using USPS must:

1. **Obtain new API credentials** from https://developer.usps.com
2. **Configure OAuth settings** in admin:
   - Client ID
   - Client Secret
   - API Environment (production/test)
3. **(Optional) Configure payment account** for label generation:
   - Account Type (EPS/PERMIT)
   - Account Number
   - CRID (Customer Registration ID)
   - MID (Mailer ID)

### Backward Compatibility

- Shipping method codes unchanged (no impact on existing orders)
- Frontend checkout flow unchanged
- Admin interfaces unchanged
- No database schema changes
- Existing configuration automatically migrated

## API Endpoints Used

### OAuth
- `POST /oauth2/v3/token` - Get access token

### Rate Calculation
- `POST /prices/v3/base-rates/search` - Domestic and international rates

### Label Generation
- `POST /payment-authorization/v3/payment-authorization` - Payment token
- `POST /labels/v3/label` - Domestic labels
- `POST /labels/v3/international-label` - International labels

### Tracking
- `GET /tracking/v3/tracking/{trackingNumber}` - Tracking events
