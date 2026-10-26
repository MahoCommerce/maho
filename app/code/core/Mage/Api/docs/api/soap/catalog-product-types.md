---
description: Retrieve the list of available product types over the Maho SOAP API.
---

# Catalog Product Types

!!! warning "Legacy API"
    This is Maho's legacy SOAP/XML-RPC API, inherited from Magento 1 and kept for backward compatibility. New integrations should use the modern [REST & GraphQL API (v2)](../v2/index.md) instead.

## Introduction

Allows you to retrieve product types.

### Resource Name

- `catalog_product_type`

### Alias

- `product_type`

### Method

- `catalog_product_type.list` - Retrieve the list of product types.

### Example - Retrieving the Product Type

```php
$proxy = new SoapClient('https://mahohost/api/soap/?wsdl');
$sessionId = $proxy->login('apiUser', 'apiKey');

$types = $proxy->call($sessionId, 'product_type.list');
var_dump($types);
```

## List

### Method

- `catalog_product_type.list` (SOAP V1)
- `catalogProductTypeList` (SOAP V2)

Allows you to retrieve the list of product types.

### Alias

- `product_type.list`

### Arguments

| Type   | Name      | Description |
|--------|-----------|-------------|
| string | sessionId | Session ID  |

### Returns

| Type  | Name   | Description                       |
|-------|--------|-----------------------------------|
| array | result | Array of catalogProductTypeEntity |

### Content `catalogProductTypeEntity`

| Type   | Name  | Description                      |
|--------|-------|----------------------------------|
| string | type  | Product type                     |
| string | label | Product label in the Admin Panel |

### Examples

#### Request Example SOAP V1

```php
$client = new SoapClient('https://mahohost/api/soap/?wsdl');
$session = $client->login('apiUser', 'apiKey');

$result = $client->call($session, 'catalog_product_type.list');
var_dump($result);

// When the session can be closed
$client->endSession($session);
```

#### Request Example SOAP V2

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl'); // replace with your store's WSDL URL
$sessionId = $proxy->login('apiUser', 'apiKey'); // replace with your API user and key

$result = $proxy->catalogProductTypeList($sessionId);
var_dump($result);
```

#### Request Example SOAP V2 (WS-I Compliance Mode)

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl');
$sessionId = $proxy->login((object)['username' => 'apiUser', 'apiKey' => 'apiKey']);

$result = $proxy->catalogProductTypeList((object)['sessionId' => $sessionId->result]);
var_dump($result->result);
```

#### Response Example SOAP V1

```php
array
  0 =>
    array
      'type' => string 'simple' (length=6)
      'label' => string 'Simple Product' (length=14)
  1 =>
    array
      'type' => string 'grouped' (length=7)
      'label' => string 'Grouped Product' (length=15)
  2 =>
    array
      'type' => string 'configurable' (length=12)
      'label' => string 'Configurable Product' (length=20)
  3 =>
    array
      'type' => string 'virtual' (length=7)
      'label' => string 'Virtual Product' (length=15)
  4 =>
    array
      'type' => string 'bundle' (length=6)
      'label' => string 'Bundle Product' (length=14)
  5 =>
    array
      'type' => string 'downloadable' (length=12)
      'label' => string 'Downloadable Product' (length=20)
```
