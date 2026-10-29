---
description: Carriers publish the public tracking page of a shipment number through getTrackingUrl(), without calling the carrier API, so the same link can be shown in emails, the customer account and the admin.
---

# Shipping Tracking URLs <span class="version-badge">v26.11+</span>

A shipment number is only useful to a customer when it links to the page where the carrier shows its status. Until now Maho could only ask the carrier for that status: `Mage_Sales_Model_Order_Shipment_Track::getNumberDetail()` calls `getTrackingInfo()`, which reaches the carrier API and needs credentials, a network round trip and a timeout budget.

That is fine for a popup opened on demand, but it cannot be used while rendering a message: an email must not depend on a third party answering. The tracking URL is therefore a separate, network-free contract.

## The contract

```php
abstract class Mage_Shipping_Model_Carrier_Abstract
{
    /**
     * Return the public tracking page for a shipment number, or null when the carrier has none.
     */
    public function getTrackingUrl(string $number): ?string
    {
        return null;
    }
}
```

Two rules make it safe to call anywhere:

- **No network calls.** The URL is composed from configuration only. Never call `getTracking()` from this method.
- **`null` means "no page".** A carrier that has no shareable tracking page returns `null`, and callers fall back to their previous behaviour.

The method lives on the carrier abstract and not on `Mage_Shipping_Model_Carrier_Interface`, so existing third-party carriers keep working: they inherit `null` without changes.

`Mage_Sales_Model_Order_Shipment_Track::getTrackingUrl()` wraps the contract for a shipment track, resolving the carrier from `carriers/<code>/model` and the store from the shipment:

```php
$track = Mage::getModel('sales/order_shipment_track')
    ->setCarrierCode('ups')
    ->setNumber('1Z999AA10123456784');

$track->getTrackingUrl();   // https://www.ups.com/track?tracknum=1Z999AA10123456784
$track->getNumberDetail(); // asks the carrier API - do not use in messages
```

## Configuration

Each carrier that extends `Mage_Usa_Model_Shipping_Carrier_Abstract` (UPS, FedEx, USPS and DHL) reads `carriers/<code>/tracking_url`, a URL with a `{{number}}` placeholder. The number is URL-encoded before it replaces the placeholder.

```xml
<config>
    <default>
        <carriers>
            <ups>
                <tracking_url>https://www.ups.com/track?tracknum={{number}}</tracking_url>
            </ups>
        </carriers>
    </default>
</config>
```

The core ships defaults for UPS, FedEx and USPS. DHL Express is deliberately left empty: its public portal addresses a product (express, parcel, ecommerce) and the stable layout differs per business unit, so a wrong default would send customers to a page that cannot find the shipment. Set it per store when you know the right URL:

```sh
maho config:set carriers/dhlint/tracking_url 'https://www.dhl.com/global-en/home/tracking.html?tracking-id={{number}}' --scope stores --scope-id 1
```

Carrier portals change their query strings without notice, so treat the shipped defaults as starting points and verify them against the portal you are sending customers to.

## Implementing a carrier

Override the method when the tracking page is not a simple configuration value, for example when the URL depends on the service:

```php
class Vendor_Module_Model_Carrier_Custom extends Mage_Shipping_Model_Carrier_Abstract
{
    public function getTrackingUrl(string $number): ?string
    {
        $base = $this->getConfigData('tracking_url');
        if (!$base || $number === '') {
            return null;
        }

        return str_replace('{{number}}', rawurlencode($number), $base);
    }
}
```

Carriers that extend `Mage_Usa_Model_Shipping_Carrier_Abstract` get this implementation already; they only need the configuration value.

## Carriers with several services

A carrier like MatrixRate registers one carrier code (`matrixrate`) and defines many services in a CSV (`DPD`, `GLS`, `TNT`, …). The tracking page is per service, not per carrier code, so a single `carriers/matrixrate/tracking_url` cannot describe it.

The carrier method receives only the tracking number, by design: it has no shipment and no order, so it cannot tell which service was used. A module in this situation rewrites the track model and resolves the service from the order:

```php
class Vendor_MatrixRate_Model_Shipment_Track extends Mage_Sales_Model_Order_Shipment_Track
{
    public function getTrackingUrl(): ?string
    {
        $number = (string) $this->getNumber();
        $method = (string) $this->getShipment()->getOrder()->getShippingMethod();
        if ($number === '' || !str_starts_with($method, 'matrixrate_')) {
            return parent::getTrackingUrl();
        }

        $service = substr($method, strlen('matrixrate_'));
        $template = (string) Mage::getStoreConfig(
            'carriers/matrixrate/tracking_urls/' . $service,
            $this->getStore(),
        );

        return $template === '' ? null : str_replace('{{number}}', rawurlencode($number), $template);
    }
}
```

Keep the per-service templates in the module configuration (`carriers/<code>/tracking_urls/<service>`), not in the CSV: existing CSV files have no such column, and adding one breaks every file already uploaded.

Every consumer listed below reads the track accessor, so a rewrite reaches the popup, the customer account, the admin and any feature built on top of the contract.

## Where the URL is used

| Location | Behaviour |
|----------|-----------|
| Tracking popup (`shipping/tracking/popup`) | The tracking number is a link to the carrier page when the carrier provides one, plain text otherwise. |
| Customer account, shipment of an order | The tracking number links to the carrier page when available, otherwise to the tracking popup as before. |
| Admin, shipment view | Same rule as the customer account. |
| Order shipment API | `getNumberDetail()` adds the URL to its payload when the carrier result has none. |
| Transactional emails | Not used: the legacy templates render `getNumberDetail()`, which would call the carrier API while sending. |

Features that need a link in a message should call `Mage_Sales_Model_Order_Shipment_Track::getTrackingUrl()` and degrade gracefully when it returns `null`.
