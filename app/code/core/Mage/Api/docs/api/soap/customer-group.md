---
description: Retrieve the list of customer groups over the Maho SOAP API.
---

# Customer Group

!!! warning "Legacy API"
    This is Maho's legacy SOAP/XML-RPC API, inherited from Magento 1 and kept for backward compatibility. New integrations should use the modern [REST & GraphQL API (v2)](../v2/index.md) instead.

## Introduction

### Method

- `customer_group.list` (SOAP V1)
- `customerGroupList` (SOAP V2)

Retrieve the list of customer groups.

### Arguments

| Type   | Name      | Description  |
|--------|-----------|--------------|
| string | sessionId | Session ID   |

### Returns

| Type  | Name   | Description                     |
|-------|--------|---------------------------------|
| array | result | An array of customerGroupEntity |

### Content `customerGroupEntity`

| Type   | Name                | Description              |
|--------|---------------------|--------------------------|
| int    | customer_group_id   | ID of the customer group |
| string | customer_group_code | Customer group code      |

### Examples

#### Request Example SOAP V1

```php
$client = new SoapClient('https://mahohost/api/soap/?wsdl');
$session = $client->login('apiUser', 'apiKey');

$result = $client->call($session, 'customer_group.list');
var_dump($result);

// When the session can be closed
$client->endSession($session);
```

#### Request Example SOAP V2

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl'); // replace with your store's WSDL URL
$sessionId = $proxy->login('apiUser', 'apiKey'); // replace with your API user and key

$result = $proxy->customerGroupList($sessionId);
var_dump($result);
```

#### Request Example SOAP V2 (WS-I Compliance Mode)

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl');
$sessionId = $proxy->login((object)['username' => 'apiUser', 'apiKey' => 'apiKey']);

$result = $proxy->customerGroupList((object)['sessionId' => $sessionId->result]);
var_dump($result->result);
```

#### Response Example SOAP V1

```php
array
  0 =>
    array
      'customer_group_id' => string '0' (length=1)
      'customer_group_code' => string 'NOT LOGGED IN' (length=13)
  1 =>
    array
      'customer_group_id' => string '1' (length=1)
      'customer_group_code' => string 'General' (length=7)
  2 =>
    array
      'customer_group_id' => string '2' (length=1)
      'customer_group_code' => string 'Wholesale' (length=9)
  3 =>
    array
      'customer_group_id' => string '3' (length=1)
      'customer_group_code' => string 'Retailer' (length=8)
```
