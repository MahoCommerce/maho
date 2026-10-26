---
description: Retrieve and update product tier prices over the Maho SOAP API.
---

# Catalog Product Tier Price

!!! warning "Legacy API"
    This is Maho's legacy SOAP/XML-RPC API, inherited from Magento 1 and kept for backward compatibility. New integrations should use the modern [REST & GraphQL API (v2)](../v2/index.md) instead.

## Introduction

Allows you to retrieve and update product tier prices.

### Resource Name

- `catalog_product_attribute_tier_price`

### Aliases

- `product_attribute_tier_price`
- `product_tier_price`

### Methods

- `catalog_product_attribute_tier_price.info` - Retrieve information about product tier prices.
- `catalog_product_attribute_tier_price.update` - Update the product tier prices.

### Faults

| Fault Code | Fault Message                                      |
|------------|----------------------------------------------------|
| 100        | Product not exists.                                |
| 101        | Invalid data given. Details in error message.      |
| 102        | Tier prices not updated. Details in error message. |

### Examples

```php
$proxy = new SoapClient('https://mahohost/api/soap/?wsdl');
$sessionId = $proxy->login('apiUser', 'apiKey');

// Get tier prices
$tierPrices = $proxy->call($sessionId, 'product_tier_price.info', 'sku');

var_dump($tierPrices);

// Add new
$tierPrices[] = [
    'website' => 'all',
    'customer_group_id' => 'all',
    'qty' => 68,
    'price' => 18.20
];

// Update tier prices
$proxy->call($sessionId, 'product_tier_price.update', ['sku', $tierPrices]);

// Compare values
var_dump($proxy->call($sessionId, 'product_tier_price.info', 'sku'));
var_dump($tierPrices);
```

## Info

### Method

- `catalog_product_attribute_tier_price.info` (SOAP V1)
- `catalogProductAttributeTierPriceInfo` (SOAP V2)

Allows you to retrieve information about product tier prices.

### Aliases

- `product_attribute_tier_price.info`
- `product_tier_price.info`

### Arguments

| Type   | Name             | Description                                                                |
|--------|------------------|----------------------------------------------------------------------------|
| string | sessionId        | Session ID                                                                 |
| string | productproductId | Product ID or SKU                                                          |
| string | identifierType   | Defines whether the product ID or SKU is passed in the 'product' parameter |

### Returns

| Type  | Name   | Description                            |
|-------|--------|----------------------------------------|
| array | result | Array of catalogProductTierPriceEntity |

### Content `catalogProductTierPriceEntity`

| Type   | Name              | Description                                          |
|--------|-------------------|------------------------------------------------------|
| string | customer_group_id | Customer group ID                                    |
| string | website           | Website                                              |
| int    | qty               | Quantity of items to which the price will be applied |
| double | price             | Price that each item will cost                       |

### Examples

#### Request Example SOAP V1

```php
$client = new SoapClient('https://mahohost/api/soap/?wsdl');
$session = $client->login('apiUser', 'apiKey');

$result = $client->call($session, 'catalog_product_attribute_tier_price.info', 'productId');
var_dump($result);
```

#### Request Example SOAP V2

```php
$client = new SoapClient('https://mahohost/api/v2_soap/?wsdl');
$session = $client->login('apiUser', 'apiKey');

$productId = 1;

$result = $client->catalogProductAttributeTierPriceInfo(
	$session,
	$productId
);
```

#### Request Example SOAP V2 (WS-I Compliance Mode)

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl');
$sessionId = $proxy->login((object)['username' => 'apiUser', 'apiKey' => 'apiKey']);

$result = $proxy->catalogProductAttributeTierPriceInfo(
    (object)['sessionId' => $sessionId->result, 'productId' => '1']
);
var_dump($result->result);
```

#### Response Example SOAP V1

```php
array
  0 =>
    array
      'customer_group_id' => string '1' (length=1)
      'website' => string 'all' (length=3)
      'qty' => string '2.0000' (length=6)
      'price' => string '129.9900' (length=8)
```

#### Response Example SOAP V2

```php
array
  0 =>
    object(stdClass)[2]
      public 'customer_group_id' => string '0' (length=1)
      public 'website' => string 'all' (length=3)
      public 'qty' => int 5
      public 'price' => float 99
  1 =>
    object(stdClass)[3]
      public 'customer_group_id' => string '0' (length=1)
      public 'website' => string 'all' (length=3)
      public 'qty' => int 10
      public 'price' => float 98
```

## Update

### Method

- `catalog_product_attribute_tier_price.update` (SOAP V1)
- `catalogProductAttributeTierPriceUpdate` (SOAP V2)

Allows you to update the product tier prices.

### Aliases

- `product_attribute_tier_price.update`
- `product_tier_price.update`

### Arguments

| Type   | Name             | Description                                                                |
|--------|------------------|----------------------------------------------------------------------------|
| string | sessionId        | Session ID                                                                 |
| string | productproductId | Product ID or SKU                                                          |
| array  | tierPrices       | Array of catalogProductTierPriceEntity                                     |
| string | identifierType   | Defines whether the product ID or SKU is passed in the 'product' parameter |

### Returns

| Type       | Name   | Description                                   |
|------------|--------|-----------------------------------------------|
| booleanint | result | True (1) if the product tier price is updated |

### Content `catalogProductTierPriceEntity`

| Type   | Name              | Description                                          |
|--------|-------------------|------------------------------------------------------|
| string | customer_group_id | Customer group ID                                    |
| string | website           | Website                                              |
| int    | qty               | Quantity of items to which the price will be applied |
| double | price             | Price that each item will cost                       |

### Examples

#### Request Example SOAP V1

```php
$proxy = new SoapClient('https://mahohost/api/soap/?wsdl');
$sessionId = $proxy->login('apiUser', 'apiKey');

$productId = 1;
$tierPrices = [
    [
        'customer_group_id' => '0',
        'website' => '0',
        'qty' => '50',
        'price' => '9.90'
    ]
];

$result = $proxy->call(
    $sessionId,
    'product_attribute_tier_price.update',
    [
        $productId,
        $tierPrices
    ]
);
var_dump($result);
```

#### Request Example SOAP V2

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl');
$sessionId = $proxy->login('apiUser', 'apiKey');

$productId = 1;
$tierPrices = [
    [
        'customer_group_id' => '0',
        'website' => '0',
        'qty' => '50',
        'price' => '9.90'
    ]
];

$result = $proxy->catalogProductAttributeTierPriceUpdate($sessionId, $productId, $tierPrices);
var_dump($result);
```

#### Request Example SOAP V2 (WS-I Compliance Mode)

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl');
$sessionId = $proxy->login((object)['username' => 'apiUser', 'apiKey' => 'apiKey']);

$productId = 1;
$tierPrices = [
    [
        'customer_group_id' => '0',
        'website' => '0',
        'qty' => '50',
        'price' => '9.90'
    ]
];

$result = $proxy->catalogProductAttributeTierPriceUpdate(
    (object)[
        'sessionId' => $sessionId->result,
        'productId' => $productId,
        'tierPrices' => $tierPrices
    ]
);
var_dump($result->result);
```
