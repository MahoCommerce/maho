---
description: Retrieve and set payment methods for a shopping cart over the Maho SOAP API.
---

# Checkout Cart Payment

!!! warning "Legacy API"
    This is Maho's legacy SOAP/XML-RPC API, inherited from Magento 1 and kept for backward compatibility. New integrations should use the modern [REST & GraphQL API (v2)](../v2/index.md) instead.

## Introduction

Allows you to retrieve and set payment methods for a shopping cart.

### Resource Name

- `cart_payment`

### Methods

- `cart_payment.method` - Set a payment method for a shopping cart.
- `cart_payment.list` - Get the list of available payment methods for a shopping cart.

### Faults

| Fault Code | Fault Message                                                             |
|------------|---------------------------------------------------------------------------|
| 1001       | Can not make operation because store is not exists                        |
| 1002       | Can not make operation because quote is not exists                        |
| 1071       | Payment method data is empty.                                             |
| 1072       | Customer’s billing address is not set. Required for payment method data.  |
| 1073       | Customer’s shipping address is not set. Required for payment method data. |
| 1074       | Payment method is not allowed                                             |
| 1075       | Payment method is not set.                                                |

## (Payment) Method

### Method

- `cart_payment.method` (SOAP V1)
- `shoppingCartPaymentMethod` (SOAP V2)

Allows you to set a payment method for a shopping cart (quote).

### Arguments

| Type   | Name      | Description                              |
|--------|-----------|------------------------------------------|
| string | sessionId | Session ID                               |
| int    | quoteId   | Shopping cart ID                         |
| array  | method    | Array of shoppingCartPaymentMethodEntity |
| string | store     | Store view ID or code (optional)         |

### Return

| Type    | Description     |
|---------|-----------------|
| boolean | True on success |

### Content `shoppingCartPaymentMethodEntity`

| Type   | Name         | Description                  |
|--------|--------------|------------------------------|
| string | po_number    | Purchase order number        |
| string | method       | Payment method               |
| string | cc_cid       | Credit card CID              |
| string | cc_owner     | Credit card owner            |
| string | cc_number    | Credit card number           |
| string | cc_type      | Credit card type             |
| string | cc_exp_year  | Credit card expiration year  |
| string | cc_exp_month | Credit card expiration month |

### Faults 

_No Faults._

### Examples

#### Request Example SOAP V1

```php
$proxy = new SoapClient('https://mahohost/api/soap/?wsdl');
$sessionId = $proxy->login('apiUser', 'apiKey');

$shoppingCartId = $proxy->call($sessionId, 'cart.create', ['maho_store']);

$paymentMethod = [
	'method' => 'checkmo'
];

$resultPaymentMethod = $proxy->call(
	$sessionId,
	'cart_payment.method',
	[
		$shoppingCartId,
		$paymentMethod
	]
);
```

#### Request Example SOAP V2

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl'); 
$sessionId = $proxy->login('apiUser', 'apiKey'); 
 
$result = $proxy->shoppingCartPaymentMethod($sessionId, 10, [
    'po_number' => null,
    'method' => 'checkmo',
    'cc_cid' => null,
    'cc_owner' => null,
    'cc_number' => null,
    'cc_type' => null,
    'cc_exp_year' => null,
    'cc_exp_month' => null
]);
var_dump($result);
```

#### Request Example SOAP V2 (WS-I Compliance Mode)

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl'); 
$sessionId = $proxy->login((object)['username' => 'apiUser', 'apiKey' => 'apiKey']); 
 
$result = $proxy->shoppingCartPaymentMethod((object)['sessionId' => $sessionId->result, 'quoteId' => 10, 'method' => [
    'po_number' => null,
    'method' => 'checkmo',
    'cc_cid' => null,
    'cc_owner' => null,
    'cc_number' => null,
    'cc_type' => null,
    'cc_exp_year' => null,
    'cc_exp_month' => null
]]);
var_dump($result->result);
```

## List

### Method

- `cart_payment.list` (SOAP V1)
- `shoppingCartPaymentList` (SOAP V2)

Allows you to retrieve a list of available payment methods for a shopping cart (quote).

### Arguments

| Type   | Name      | Description                      |
|--------|-----------|----------------------------------|
| string | sessionId | Session ID                       |
| int    | quoteId   | Shopping cart ID                 |
| string | store     | Store view ID or code (optional) |

### Return

| Type  | Name   | Description                                      |
|-------|--------|--------------------------------------------------|
| array | result | Array of shoppingCartPaymentMethodResponseEntity |

### Content `shoppingCartPaymentMethodResponseEntity`

| Type             | Name     | Description                |
|------------------|----------|----------------------------|
| string           | code     | Payment method code        |
| string           | title    | Payment method title       |
| associativeArray | cc_types | Array of credit card types |

### Examples

#### Request Example SOAP V1

```php
$client = new SoapClient('https://mahohost/api/soap/?wsdl');
$session = $client->login('apiUser', 'apiKey');

$result = $client->call($session, 'cart_payment.list', 'quoteId');
var_dump($result);
```

#### Response Example SOAP V1

```php
array
  0 =>
    array
      'code' => string 'checkmo' (length=7)
      'title' => string 'Check / Money order' (length=19)
      'ccTypes' => null
  1 =>
    array
      'code' => string 'ccsave' (length=6)
      'title' => string 'Credit Card (saved)' (length=19)
      'ccTypes' =>
        array
          'AE' => string 'American Express' (length=16)
          'VI' => string 'Visa' (length=4)
          'MC' => string 'MasterCard' (length=10)
          'DI' => string 'Discover' (length=8)
```
