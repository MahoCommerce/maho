# USPS REST API Migration - Test Plan

This document provides comprehensive testing instructions for the USPS REST API migration, covering rate calculation, shipping label generation, and package tracking.

## Table of Contents

1. [Prerequisites](#prerequisites)
2. [Configuration Setup](#configuration-setup)
3. [Rate Calculation Testing](#rate-calculation-testing)
4. [Shipping Label Generation Testing](#shipping-label-generation-testing)
5. [Tracking Testing](#tracking-testing)
6. [Edge Cases & Error Handling](#edge-cases--error-handling)
7. [Debug & Logging](#debug--logging)
8. [Integration Testing](#integration-testing)
9. [Performance Testing](#performance-testing)

---

## Prerequisites

### USPS Developer Account Setup

1. **Register for USPS API Access:**
   - Visit https://developer.usps.com
   - Create a developer account
   - Register a new application
   - Note down your Client ID and Client Secret

2. **Payment Account Information:**
   - Obtain your USPS account details:
     - Account Type (EPS or PERMIT)
     - Account Number
     - CRID (Customer Registration ID)
     - MID (Mailer ID)
     - Permit ZIP (if using PERMIT account type)

### Test Environment

- Maho installation with USPS module enabled
- Access to both production and test USPS API environments
- Test products with various weights and dimensions
- Test customer accounts with various addresses

---

## Configuration Setup

### System Configuration

Navigate to: **System → Configuration → Shipping Methods → USPS**

#### API Credentials
```
Client ID: [paste your USPS client ID]
Client Secret: [paste your USPS client secret]
API Environment:
  - Production (for live testing)
  - Test (for sandbox testing)
```

#### Payment Account (Required for Label Generation)
```
Account Type: EPS or PERMIT
Account Number: [your USPS account number]
CRID: [Customer Registration ID]
MID: [Mailer ID]
Permit ZIP: [required if Account Type = PERMIT]
```

#### General Settings
```
Enabled: Yes
Title: USPS (or custom name)
Commercial Pricing: Yes/No (select based on your account)
Allowed Methods: (select services to offer)
  ☑ Priority Mail
  ☑ Priority Mail Express
  ☑ First-Class Package Service
  ☑ USPS Ground Advantage
  ☑ Priority Mail Flat Rate Envelope
  ☑ Priority Mail Flat Rate Boxes
  ☑ Priority Mail International
  ☑ Priority Mail Express International
Debug: Yes (for testing)
Show Method if Not Applicable: Yes (for testing)
```

#### Clear Cache
```bash
./maho cache:flush
```

---

## Rate Calculation Testing

### Frontend - Customer Checkout

#### Domestic Shipping Test

**Test Case 1: Standard Domestic Shipping**
```
1. Add product to cart (weight: 5 lbs)
2. Proceed to checkout
3. Enter shipping address:
   - From: Los Angeles, CA 90001
   - To: New York, NY 10001
4. Verify USPS methods appear with prices:
   ☐ Priority Mail (~$X.XX)
   ☐ Priority Mail Express (~$X.XX)
   ☐ First-Class Package Service (~$X.XX)
   ☐ USPS Ground Advantage (~$X.XX)
5. Prices should be reasonable and different per service
```

**Test Case 2: Flat Rate Options**
```
1. Configure product dimensions to fit flat rate envelope (12"x9"x0.5")
2. Add to cart
3. Checkout with shipping address
4. Verify flat rate options appear:
   ☐ Priority Mail Flat Rate Envelope
   ☐ Priority Mail Express Flat Rate Envelope
```

**Test Case 3: Flat Rate Boxes**
```
1. Configure product to fit small flat rate box (8.625"x5.375"x1.625")
2. Add to cart
3. Checkout
4. Verify box options:
   ☐ Priority Mail Small Flat Rate Box
   ☐ Priority Mail Medium Flat Rate Box
   ☐ Priority Mail Large Flat Rate Box
```

#### International Shipping Test

**Test Case 4: Canada**
```
1. Add product to cart
2. Enter international address:
   - To: Toronto, ON M5H 2N2, Canada
3. Verify international methods:
   ☐ Priority Mail International
   ☐ Priority Mail Express International
   ☐ First-Class Package International Service
```

**Test Case 5: Europe**
```
1. Add product to cart
2. Enter address:
   - To: London, UK SW1A 1AA
3. Verify rates calculate correctly
4. Rates should be higher than Canada
```

**Test Case 6: Asia/Pacific**
```
1. Add product to cart
2. Enter address:
   - To: Sydney, NSW 2000, Australia
3. Verify rates calculate correctly
4. Rates should be higher than Europe
```

### Admin - Manual Rate Check

**Test Case 7: Create Manual Order**
```
1. Sales → Orders → Create New Order
2. Select or create customer
3. Add products (varying weights)
4. Account Information → Select shipping address
5. Click "Get shipping methods and rates"
6. Verify:
   ☐ USPS rates appear
   ☐ Multiple service options available
   ☐ Prices match frontend rates
7. Select USPS method
8. Complete order creation
```

### Weight & Dimension Variations

**Test Case 8: Very Light Package (<1 lb)**
```
Product weight: 0.5 lbs
Expected: First-Class Package Service should be cheapest
```

**Test Case 9: Medium Package (5-20 lbs)**
```
Product weight: 10 lbs
Expected: Priority Mail, Ground Advantage available
```

**Test Case 10: Heavy Package (>20 lbs)**
```
Product weight: 50 lbs
Expected: Priority Mail, Ground Advantage (not First-Class)
```

**Test Case 11: Oversized Package**
```
Dimensions: 36" x 24" x 24" (Large size)
Weight: 30 lbs
Expected: Limited service options, higher pricing
```

### Pricing Options Test

**Test Case 12: Commercial vs Retail Pricing**
```
1. Configure Commercial Pricing: No
2. Get rates, note prices
3. Configure Commercial Pricing: Yes
4. Get rates, note prices
5. Verify: Commercial prices are lower
```

### Free Shipping Test

**Test Case 13: Free Shipping Threshold**
```
1. Configure free shipping over $100
2. Add $50 product → USPS charges apply
3. Add $100 product → USPS should be free
```

---

## Shipping Label Generation Testing

### Domestic Label Generation

**Test Case 14: Priority Mail Domestic Label**
```
1. Complete order with USPS Priority Mail
2. Sales → Orders → View Order
3. Click "Ship" button
4. Create Shipment form:
   Package 1:
   - Items: Select item(s)
   - Weight: 5 lbs
   - Length: 12 inches
   - Width: 8 inches
   - Height: 6 inches
5. Click "Submit Shipment"
6. Verify:
   ☐ Shipment created successfully
   ☐ Tracking number assigned (format: 9XXXXXXXXXXXXXXXXXXXXX)
   ☐ Tracking number saved to shipment
7. Click "Print Shipping Label"
8. Verify:
   ☐ PDF downloads (4x6 label)
   ☐ Label shows correct addresses
   ☐ Label shows tracking barcode
   ☐ Label shows postage amount
   ☐ Label shows service type
```

**Test Case 15: Priority Mail Express Label**
```
1. Complete order with Priority Mail Express
2. Create shipment with package info
3. Generate label
4. Verify:
   ☐ Label clearly shows "Express" service
   ☐ Higher postage amount than Priority Mail
   ☐ Different tracking number format
```

**Test Case 16: Flat Rate Envelope Label**
```
1. Order with Priority Mail Flat Rate Envelope shipping
2. Create shipment
3. Package info:
   - Container: Flat Rate Envelope
   - Weight: 2 lbs
4. Generate label
5. Verify:
   ☐ Label shows flat rate pricing
   ☐ Regardless of weight, same price
```

**Test Case 17: Flat Rate Box Label**
```
1. Order with Priority Mail Medium Flat Rate Box
2. Create shipment
3. Test different weights (5 lbs, 10 lbs, 15 lbs)
4. Verify:
   ☐ Same label price for all weights
   ☐ Label shows "Medium Flat Rate Box"
```

**Test Case 18: First-Class Package Service Label**
```
1. Order with First-Class Package Service (<1 lb)
2. Create shipment
3. Package weight: 0.8 lbs
4. Verify label generates successfully
```

**Test Case 19: USPS Ground Advantage Label**
```
1. Order with USPS Ground Advantage
2. Create shipment
3. Generate label
4. Verify service appears correctly on label
```

### International Label Generation

**Test Case 20: International Priority Mail - Canada**
```
1. Order to Toronto, Canada
2. Create shipment
3. Additional fields required:
   - Customs Content Type: Merchandise
   - Content Explanation: "Electronics"
   - Item descriptions
   - Item values (per item)
   - Country of manufacture
   - HS/Tariff codes (optional)
4. Generate label
5. Verify:
   ☐ Shipping label generated (4x6)
   ☐ Customs form included (CN22 or CN23)
   ☐ Both forms in PDF
   ☐ Tracking number assigned
   ☐ Forms show correct item details
   ☐ Total value displayed
```

**Test Case 21: International Priority Mail Express - UK**
```
1. Order to London, UK
2. Create shipment with customs info
3. Generate label
4. Verify:
   ☐ Express service shown
   ☐ Higher value threshold (CN23 form)
   ☐ All customs details included
```

**Test Case 22: International - High Value**
```
1. Order value: $2,500+
2. Create international shipment
3. Verify:
   ☐ CN23 form generated (not CN22)
   ☐ All item details listed
   ☐ Total value shown correctly
```

### Multi-Package Shipments

**Test Case 23: Multiple Packages - One Order**
```
1. Order with 3 different items
2. Create shipment
3. Add 2 packages:
   Package 1: Item A (3 lbs)
   Package 2: Items B+C (7 lbs)
4. Generate labels
5. Verify:
   ☐ 2 separate labels generated
   ☐ 2 different tracking numbers
   ☐ Both saved to shipment
   ☐ Correct weights on each label
```

### Special Services

**Test Case 24: Signature Confirmation**
```
1. Create shipment
2. Select "Signature Confirmation"
3. Generate label
4. Verify:
   ☐ Extra service charge applied
   ☐ Label indicates signature required
```

### Error Scenarios

**Test Case 25: Missing Payment Configuration**
```
1. Remove CRID from configuration
2. Attempt to create label
3. Verify:
   ☐ Clear error message: "Payment account configuration incomplete"
   ☐ Specifies which fields are missing
```

**Test Case 26: Invalid Address**
```
1. Create shipment with invalid ZIP code (99999)
2. Attempt label generation
3. Verify:
   ☐ USPS API validation error displayed
   ☐ Helpful error message
```

**Test Case 27: Overweight Package**
```
1. Create shipment with 80 lbs weight
2. Attempt First-Class label
3. Verify:
   ☐ Error: Weight exceeds service limit
```

---

## Tracking Testing

### Basic Tracking

**Test Case 28: View Tracking - In Transit**
```
1. Generate label, obtain tracking number
2. Sales → Orders → Shipments → View Shipment
3. Click "Track this shipment"
4. Verify display shows:
   ☐ Current status summary (e.g., "In Transit")
   ☐ Detailed event history:
     - Event description
     - Date and time
     - Location (city, state, ZIP)
   ☐ Most recent event at top
   ☐ Events in reverse chronological order
```

**Test Case 29: View Tracking - Delivered**
```
1. Use delivered tracking number
2. View tracking
3. Verify:
   ☐ Status: "Delivered"
   ☐ Delivery date and time shown
   ☐ Delivery location shown
   ☐ Full event history visible
```

**Test Case 30: Frontend Customer Tracking**
```
1. Customer logs in
2. My Account → My Orders
3. View order with tracking
4. Click tracking number/link
5. Verify:
   ☐ Tracking information displays
   ☐ Same data as admin view
   ☐ Customer-friendly presentation
```

### Tracking Errors

**Test Case 31: Invalid Tracking Number**
```
1. Enter fake tracking number (1234567890)
2. View tracking
3. Verify:
   ☐ Error message: "Unable to retrieve tracking"
   ☐ No system crash
   ☐ Graceful error handling
```

**Test Case 32: Tracking Not Yet Active**
```
1. Use newly created tracking number (<1 hour old)
2. View tracking
3. Verify:
   ☐ Appropriate message or minimal data
   ☐ "Pre-shipment" status if available
```

### Multiple Tracking Numbers

**Test Case 33: Multi-Package Tracking**
```
1. Shipment with 2+ packages (2+ tracking numbers)
2. View shipment
3. Verify:
   ☐ All tracking numbers listed
   ☐ Can click each individually
   ☐ Each shows separate tracking data
```

---

## Edge Cases & Error Handling

### Address Validation

**Test Case 34: PO Box Address**
```
Recipient: PO Box 123, Anywhere, USA
Expected: Some services unavailable, others work
```

**Test Case 35: APO/FPO Address**
```
Recipient: APO AE 09001 (military address)
Expected: Specific service options for military mail
```

**Test Case 36: Puerto Rico / US Territory**
```
Recipient: San Juan, PR 00901
Expected: Domestic rates and services
```

### API Failures

**Test Case 37: API Timeout**
```
1. Simulate network delay/timeout
2. Attempt rate calculation
3. Verify:
   ☐ Graceful timeout handling
   ☐ Error message to user
   ☐ No system crash
```

**Test Case 38: Invalid API Credentials**
```
1. Enter wrong Client ID
2. Attempt to get rates
3. Verify:
   ☐ 401 Unauthorized logged
   ☐ Clear error message
   ☐ Prompt to check credentials
```

**Test Case 39: Rate Limit Exceeded**
```
1. Make many rapid requests
2. If rate limited by USPS
3. Verify:
   ☐ Appropriate error handling
   ☐ Retry logic or cache usage
```

### Payment Authorization

**Test Case 40: Payment Auth Token Expiry**
```
1. Wait for token to expire
2. Generate new label
3. Verify:
   ☐ New token automatically requested
   ☐ Label generation continues
   ☐ No manual intervention needed
```

---

## Debug & Logging

### Enable Debug Mode

**Configuration:**
```
System → Configuration → USPS → Debug: Yes
```

**Watch Logs:**
```bash
# Real-time log monitoring
tail -f var/log/system.log | grep -i usps
tail -f var/log/exception.log

# Search logs
grep "USPS" var/log/system.log
```

### What to Look For

#### Rate Calculation Logs

**OAuth Token Request:**
```
POST https://apis.usps.com/oauth2/v3/token
Response: {"access_token": "...", "expires_in": 3600}
```

**Shipping Options Request:**
```
POST https://apis.usps.com/prices/v3/base-rates/search
Request payload:
{
  "originZIPCode": "90001",
  "destinationZIPCode": "10001",
  "packageDescription": {
    "weight": 5.0,
    "length": 12,
    "width": 8,
    "height": 6,
    "mailClass": "ALL"
  },
  "pricingOptions": [{"priceType": "COMMERCIAL"}]
}

Response:
{
  "pricingOptions": [...],
  "shippingOptions": [...],
  "rates": [...]
}
```

#### Label Generation Logs

**Payment Authorization:**
```
POST https://apis.usps.com/payment-authorization/v3/payment-authorization
Request: {"roles": [{"roleName": "PAYER", ...}, ...]}
Response: {"paymentAuthorizationToken": "..."}
```

**Domestic Label:**
```
POST https://apis.usps.com/labels/v3/label
Request: {
  "imageInfo": {...},
  "fromAddress": {...},
  "toAddress": {...},
  "packageDescription": {...}
}
Response: {
  "labelImage": "base64...",
  "trackingNumber": "92001..."
}
```

**International Label:**
```
POST https://apis.usps.com/labels/v3/international-label
Request includes customsForm: {...}
```

#### Tracking Logs

**Tracking Request:**
```
GET https://apis.usps.com/tracking/v3/tracking/92001...
Response: {
  "trackingEvents": [
    {
      "eventType": "DELIVERED",
      "eventDescription": "Delivered...",
      "eventDate": "2025-01-15",
      "eventTime": "14:30:00",
      "eventCity": "NEW YORK",
      "eventState": "NY",
      "eventZIP": "10001"
    },
    ...
  ]
}
```

---

## Integration Testing

### Complete Order Flow

**Test Case 41: End-to-End Customer Order**
```
1. Customer perspective:
   - Browse products
   - Add to cart (5 lbs item)
   - Proceed to checkout
   - Enter shipping address (NY)
   - See USPS rates
   - Select Priority Mail
   - Complete payment
   - Receive order confirmation

2. Admin perspective:
   - View new order
   - Create invoice
   - Create shipment:
     * Generate USPS label
     * Label prints successfully
     * Tracking number assigned
   - Shipment confirmation sent to customer

3. Customer receives email:
   - Contains tracking number
   - Tracking link works
   - Shows current status

4. Package tracking:
   - Customer clicks tracking
   - Views delivery progress
   - Sees delivery confirmation

Verify all steps complete without errors.
```

### Return Label Flow

**Test Case 42: Return Shipment**
```
1. Customer requests return (RMA)
2. Admin generates return label:
   - Reverse addresses (customer is now shipper)
   - Generate label
3. Verify:
   ☐ Return label generates correctly
   ☐ Addresses reversed properly
   ☐ Return tracking number assigned
4. Email return label to customer
```

### Inventory Management

**Test Case 43: Stock Updates**
```
1. Product stock: 10 units
2. Order placed: 3 units
3. Shipment created with USPS label
4. Verify:
   ☐ Stock decremented correctly
   ☐ Label generation doesn't affect stock twice
```

---

## Performance Testing

### Response Time Benchmarks

**Test Case 44: Rate Calculation Performance**
```
Measure time for rate calculation:
- Target: <3 seconds
- First request: ~2-3 seconds (API call)
- Cached request: <0.5 seconds

Test:
1. Clear cache
2. Request rates, measure time
3. Request same rates again, measure time
4. Verify caching improves performance
```

**Test Case 45: Label Generation Performance**
```
Measure time for label generation:
- Target: <5 seconds total
- Payment auth: ~1 second
- Label creation: ~2-3 seconds

Test with:
- Single label
- Multiple labels (2-5 packages)
```

**Test Case 46: Tracking Lookup Performance**
```
Measure tracking lookup:
- Target: <2 seconds
- REST API call should be fast
```

### Load Testing

**Test Case 47: Concurrent Rate Requests**
```
Simulate multiple users:
1. 5-10 simultaneous checkout sessions
2. All requesting USPS rates
3. Verify:
   ☐ All requests complete successfully
   ☐ No timeout errors
   ☐ OAuth token reused efficiently
```

**Test Case 48: Batch Label Generation**
```
Process multiple orders:
1. Create 10 orders with USPS shipping
2. Generate labels for all
3. Verify:
   ☐ All labels generate successfully
   ☐ No duplicate tracking numbers
   ☐ Reasonable total time (<60 seconds)
```

### OAuth Token Management

**Test Case 49: Token Reuse**
```
1. Make rate request (token created)
2. Make another rate request within 1 hour
3. Verify:
   ☐ Same token reused
   ☐ No duplicate token requests
4. Wait for token expiry
5. Make new request
6. Verify:
   ☐ New token automatically requested
```

---

## Test Results Template

Use this template to record your test results:

```markdown
## Test Execution Results

**Date:** YYYY-MM-DD
**Tester:** Name
**Environment:** Production / Test
**Maho Version:** X.X.X

### Rate Calculation
- [ ] Test Case 1: ✅ PASS / ❌ FAIL - Notes: ___
- [ ] Test Case 2: ✅ PASS / ❌ FAIL - Notes: ___
...

### Label Generation
- [ ] Test Case 14: ✅ PASS / ❌ FAIL - Notes: ___
...

### Tracking
- [ ] Test Case 28: ✅ PASS / ❌ FAIL - Notes: ___
...

### Issues Found
1. Issue description
   - Severity: Critical / High / Medium / Low
   - Steps to reproduce
   - Expected vs Actual

### Overall Assessment
- Total Tests: XX
- Passed: XX
- Failed: XX
- Pass Rate: XX%
```

---

## Troubleshooting Common Issues

### Issue: No Rates Returned

**Possible Causes:**
- Invalid API credentials
- Origin address not in USA
- Destination address invalid
- API environment mismatch

**Debug Steps:**
```bash
# Check logs
tail -f var/log/system.log | grep USPS

# Verify API credentials in config
# Check origin address in System > Configuration > Shipping Origin
# Test with known-good addresses
```

### Issue: Label Generation Fails

**Possible Causes:**
- Missing payment account configuration
- Invalid payment credentials
- Insufficient account balance
- Package exceeds service limits

**Debug Steps:**
```
1. Verify all payment fields configured
2. Test with smaller/lighter package
3. Check USPS account status
4. Review API response in logs
```

### Issue: Tracking Not Working

**Possible Causes:**
- Tracking number not yet active
- Invalid tracking number format
- API credentials issue

**Debug Steps:**
```
1. Wait 1-2 hours after label creation
2. Verify tracking number format
3. Test tracking on USPS.com directly
4. Check API response in logs
```

---

## Sign-Off

After completing all tests, sign off:

```
✅ Rate calculation fully tested and working
✅ Label generation fully tested and working
✅ Tracking fully tested and working
✅ Edge cases handled appropriately
✅ Performance meets requirements
✅ Ready for production deployment

Approved by: _______________
Date: _______________
```
