---
description: Add customer information and addresses to a shopping cart over the Maho SOAP API.
---

# Checkout Cart Customer

!!! warning "Legacy API"
    This is Maho's legacy SOAP/XML-RPC API, inherited from Magento 1 and kept for backward compatibility. New integrations should use the modern [REST & GraphQL API (v2)](../v2/index.md) instead.

## Introduction

Allows you to add customer information and addresses into a shopping cart.

### Resource Name

- `cart_customer`

### Methods

- `cart_customer.set` - Add customer information into a shopping cart.
- `cart_customer.addresses` - Set the customer addresses (shipping and billing) into a shopping cart.

### Faults

| Fault Code | Fault Message                                                          |
|------------|------------------------------------------------------------------------|
| 1001       | Can not make operation because store is not exists                     |
| 1002       | Can not make operation because quote is not exists                     |
| 1041       | Customer is not set.                                                   |
| 1042       | The customer's identifier is not valid or customer is not existed      |
| 1043       | Customer could not be created.                                         |
| 1044       | Customer data is not valid.                                            |
| 1045       | Customer's mode is unknown                                             |
| 1051       | Customer address data is empty.                                        |
| 1052       | Customer's address data is not valid.                                  |
| 1053       | The customer’s address identifier is not valid                         |
| 1054       | Customer address is not set.                                           |
| 1055       | Customer address identifier do not belong customer, which set in quote |

## Set

### Method

- `cart_customer.set` (SOAP V1)
- `shoppingCartCustomerSet` (SOAP V2)

Allows you to add information about the customer to a shopping cart (quote).

### Arguments

| Type   | Name         | Description                         |
|--------|--------------|-------------------------------------|
| string | sessionId    | Session ID                          |
| int    | quoteId      | Shopping cart ID                    |
| array  | customerData | Array of shoppingCartCustomerEntity |
| string | store        | Store view ID or code (optional)    |

### Return

| Type    | Name   | Description                  |
|---------|--------|------------------------------|
| boolean | result | True if information is added |

### Content `shoppingCartCustomerEntity`

| Type   | Name         | Description            |
|--------|--------------|------------------------|
| string | mode         | Customer mode          |
| int    | customer_id  | Customer ID            |
| string | email        | Customer email address |
| string | firstname    | Customer first name    |
| string | lastname     | Customer last name     |
| string | password     | Customer password      |
| string | confirmation | Confirmation flag      |
| int    | website_id   | Website ID             |
| int    | store_id     | Store ID               |
| int    | group_id     | Group ID               |

### Faults 

_No Faults._

### Examples

#### Request Example SOAP V1

```php
$proxy = new SoapClient('https://mahohost/api/soap/?wsdl');
$sessionId = $proxy->login('apiUser', 'apiKey');

$shoppingCartId = $proxy->call($sessionId, 'cart.create', ['maho_store']);
$customerAsGuest = [
	'firstname' => 'testFirstname',
	'lastname' => 'testLastName',
	'email' => 'testEmail',
	'website_id' => '0',
	'store_id' => '0',
	'mode' => 'guest'
];
$resultCustomerSet = $proxy->call($sessionId, 'cart_customer.set', [$shoppingCartId, $customerAsGuest]);
```

#### Request Example SOAP V2

```php
$client = new SoapClient('https://mahohost/api/v2_soap/?wsdl');
$session = $client->login('apiUser', 'apiKey');

$quoteId = $client->shoppingCartCreate($session);

$customerData = [
    'firstname' => 'testFirstname',
    'lastname' => 'testLastName',
    'email' => 'testEmail@mail.com',
    'mode' => 'guest',
    'website_id' => '0'
];

$resultCustomerSet = $client->shoppingCartCustomerSet($session, $quoteId, $customerData);
```

## Addresses

### Method

- `cart_customer.addresses` (SOAP V1)
- `shoppingCartCustomerAddresses` (SOAP V2)

Allows you to set the customer addresses in the shopping cart (quote).

### Arguments

| Type   | Name                | Description                                |
|--------|---------------------|--------------------------------------------|
| string | sessionId           | Session ID                                 |
| int    | quoteId             | Shopping cart ID                           |
| array  | customerAddressData | Array of shoppingCartCustomerAddressEntity |
| string | store               | Store view ID or code (optional)           |

### Return

| Type    | Name   | Description                |
|---------|--------|----------------------------|
| boolean | result | True if the address is set |

### Content `shoppingCartCustomerAddressEntity`

| Type   | Name                | Description                                               |
|--------|---------------------|-----------------------------------------------------------|
| string | mode                | Mode: billing or shipping                                 |
| string | address_id          | Address ID                                                |
| string | firstname           | Customer first name                                       |
| string | lastname            | Customer last name                                        |
| string | company             | Company name                                              |
| string | street              | Street                                                    |
| string | city                | City                                                      |
| string | region              | Region                                                    |
| string | region_id           | Region ID                                                 |
| string | postcode            | Post code                                                 |
| string | country_id          | Country ID                                                |
| string | telephone           | Telephone number                                          |
| string | fax                 | Fax number                                                |
| int    | is_default_billing  | Defines whether the address is a default billing address  |
| int    | is_default_shipping | Defines whether the address is a default shipping address |

### Faults

_No Faults._

### Examples

#### Request Example SOAP V1

```php
$proxy = new SoapClient('https://mahohost/api/soap/?wsdl');
$sessionId = $proxy->login('apiUser', 'apiKey');

$shoppingCartId = $proxy->call($sessionId, 'cart.create', ['maho_store']);

$arrAddresses = [
	[
		'mode' => 'shipping',
		'firstname' => 'testFirstname',
		'lastname' => 'testLastname',
		'company' => 'testCompany',
		'street' => 'testStreet',
		'city' => 'testCity',
		'region' => 'testRegion',
		'postcode' => 'testPostcode',
		'country_id' => 'id',
		'telephone' => '0123456789',
		'fax' => '0123456789',
		'is_default_shipping' => 0,
		'is_default_billing' => 0
	],
	[
		'mode' => 'billing',
		'address_id' => 'customer_address_id'
	]
];

$resultCustomerAddresses = $proxy->call(
	$sessionId,
	'cart_customer.addresses',
	[
		$shoppingCartId,
		$arrAddresses,
	]
);
```

#### Request Example SOAP V2

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl');
$sessionId = $proxy->login('apiUser', 'apiKey');

$result = $proxy->shoppingCartCustomerAddresses(
    $sessionId,
    10,
    [
        [
            'mode' => 'billing',
            'firstname' => 'firstname',
            'lastname' => 'lastname',
            'street' => 'street address',
            'city' => 'city',
            'region' => 'region',
            'postcode' => 'postcode',
            'country_id' => 'US',
            'telephone' => '123456789',
            'is_default_billing' => 1
        ]
    ]
);
var_dump($result);
```

#### Request Example SOAP V2 (WS-I Compliance Mode)

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl');
$sessionId = $proxy->login((object)['username' => 'apiUser', 'apiKey' => 'apiKey']);

$result = $proxy->shoppingCartCustomerAddresses(
    (object)[
        'sessionId' => $sessionId->result,
        'quoteId' => 10,
        'customerAddressData' => [
            [
                'mode' => 'billing',
                'firstname' => 'firstname',
                'lastname' => 'lastname',
                'street' => 'street address',
                'city' => 'city',
                'region' => 'region',
                'postcode' => 'postcode',
                'country_id' => 'US',
                'telephone' => '123456789',
                'is_default_billing' => 1
            ]
        ]
    ]
);
var_dump($result->result);
```
