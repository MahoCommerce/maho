---
description: Retrieve and set shipping methods for a shopping cart over the Maho SOAP API.
---

# Checkout Cart Shipping

!!! warning "Legacy API"
    This is Maho's legacy SOAP/XML-RPC API, inherited from Magento 1 and kept for backward compatibility. New integrations should use the modern [REST & GraphQL API (v2)](../v2/index.md) instead.

## Introduction

Allows you to retrieve and set shipping methods for a shopping cart.

### Resource Name

- `cart_shipping`

### Methods

- `cart_shipping.method` - Set a shipping method for a shopping cart.
- `cart_shipping.list` - Retrieve the list of available shipping methods for a shopping cart.

### Faults

| Fault Code | Fault Message                                                          |
|------------|------------------------------------------------------------------------|
| 1001       | Can not make operation because store is not exists                     |
| 1002       | Can not make operation because quote is not exists                     |
| 1061       | Can not make operation because of customer shipping address is not set |
| 1062       | Shipping method is not available                                       |
| 1063       | Can not set shipping method.                                           |
| 1064       | Can not receive list of shipping methods.                              |

## Method

- `cart_shipping.method` (SOAP V1)
- `shoppingCartShippingMethod` (SOAP V2)

Allows you to set a shipping method for a shopping cart (quote).

### Arguments

| Type   | Name      | Description Ω                    |
|--------|-----------|----------------------------------|
| string | sessionId | Session ID                       |
| int    | quoteId   | Shopping cart ID                 |
| string | method    | Shipping method code             |
| string | storeId   | Store view ID or code (optional) |

### Return

| Type    | Name   | Description                        |
|---------|--------|------------------------------------|
| boolean | result | True if the shipping method is set |

### Faults

_No Faults._

### Examples

#### Request Example SOAP V1

```php
$proxy = new SoapClient('https://mahohost/api/soap/?wsdl');
$sessionId = $proxy->login('apiUser', 'apiKey');

$result = $proxy->call($sessionId, 'cart_shipping.method', [10, 'freeshipping_freeshipping']);
var_dump($result);
```

#### Request Example SOAP V2

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl');
$sessionId = $proxy->login('apiUser', 'apiKey');

$result = $proxy->shoppingCartShippingMethod($sessionId, 10, 'freeshipping_freeshipping');
var_dump($result);
```

#### Request Example SOAP V2 (WS-I Compliance Mode)

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl');
$sessionId = $proxy->login((object)['username' => 'apiUser', 'apiKey' => 'apiKey']);

$result = $proxy->shoppingCartShippingMethod(
    (object)[
        'sessionId' => $sessionId->result,
        'quoteId' => 10,
        'shippingMethod' => 'freeshipping_freeshipping'
    ]
);
var_dump($result->result);
```

## List

### Method

- `cart_shipping.list` (SOAP V1)
- `shoppingCartShippingList` (SOAP V2)

Allows you to retrieve the list of available shipping methods for a shopping cart (quote).

### Arguments

| Type   | Name      | Description                      |
|--------|-----------|----------------------------------|
| string | sessionId | Session ID                       |
| int    | quoteId   | Shopping cart ID                 |
| string | storeId   | Store view ID or code (optional) |

### Returns

| Type  | Name   | Descriptio n                              |
|-------|--------|-------------------------------------------|
| array | result | Array of shoppingCartShippingMethodEntity |

### Content `shoppingCartShippingMethodEntity`

| Type   | Name               | Description                 |
|--------|--------------------|-----------------------------|
| string | code               | Code                        |
| string | carrier            | Carrier                     |
| string | carrier_title      | Carrier title               |
| string | method             | Shipping method             |
| string | method_title       | Shipping method title       |
| string | method_description | Shipping method description |
| double | price              | Shipping price              |

### Faults 

_No Faults._

### Examples

#### Request Example SOAP V1

```php
$proxy = new SoapClient('https://mahohost/api/soap/?wsdl'); 
$sessionId = $proxy->login('apiUser', 'apiKey'); 
 
$result = $proxy->call($sessionId, 'cart_shipping.list', 10);   
var_dump($result);
```

#### Request Example SOAP V2

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl'); 
$sessionId = $proxy->login('apiUser', 'apiKey'); 
 
$result = $proxy->shoppingCartShippingList($sessionId, 10);   
var_dump($result);
```

#### Request Example SOAP V2 (WS-I Compliance Mode)

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl'); 
$sessionId = $proxy->login((object)['username' => 'apiUser', 'apiKey' => 'apiKey']); 
 
$result = $proxy->shoppingCartShippingList((object)['sessionId' => $sessionId->result, 'quoteId' => 10]);   
var_dump($result->result);
```
