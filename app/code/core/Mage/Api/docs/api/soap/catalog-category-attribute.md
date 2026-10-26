---
description: Retrieve the list of category attributes and their options over the Maho SOAP API.
---

# Catalog Category Attribute

!!! warning "Legacy API"
    This is Maho's legacy SOAP/XML-RPC API, inherited from Magento 1 and kept for backward compatibility. New integrations should use the modern [REST & GraphQL API (v2)](../v2/index.md) instead.

## Introduction

Allows you to retrieve the list of category attributes and options.

### Methods

- `catalog_category_attribute.currentStore` - Set/Get the current store view.
- `catalog_category_attribute.list` - Retrieve the category attributes.
- `catalog_category_attribute.options` - Retrieve the attribute options.

### Faults

| Fault Code | Fault Message                      |
|------------|------------------------------------|
| 100        | Requested store view not found.    |
| 101        | Requested attribute not found.     |

### Example - Retrieving Attributes and Options

```php
$proxy = new SoapClient('https://mahohost/api/soap/?wsdl');
$sessionId = $proxy->login('apiUser', 'apiKey');
$attributes = $proxy->call($sessionId, 'category_attribute.list');
foreach ($attributes as &$attribute) {
    $attributeType = $attribute['type'];
    if (isset($attributeType)
        && ($attributeType === 'select' || $attributeType === 'multiselect')) {
        $attribute['options'] = $proxy->call($sessionId, 'category_attribute.options', $attribute['code']);
    }
}
var_dump($attributes);
```

## Current Store

### Method

- `catalog_category_attribute.currentStore` (SOAP V1)
- `catalogCategoryAttributeCurrentStore` (SOAP V2)

Allows you to set/get the current store view.

### Alias

- `category_attribute.currentStore`

### Arguments

| Type   | Name      | Description           |
|--------|-----------|-----------------------|
| string | sessionId | Session ID            |
| string | storeView | Store view ID or code |

### Returns

| Type | Name      | Description   |
|------|-----------|---------------|
| int  | storeView | Store view ID |

### Examples

#### Request Example SOAP V1

```php
$client = new SoapClient('https://mahohost/api/soap/?wsdl');
$session = $client->login('apiUser', 'apiKey');

$result = $client->call($session, 'catalog_category_attribute.currentStore', 'english');
var_dump($result);
```

#### Request Example SOAP V2

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl'); // replace with your store's WSDL URL
$sessionId = $proxy->login('apiUser', 'apiKey'); // replace with your API user and key

$result = $proxy->catalogCategoryAttributeCurrentStore($sessionId, 'english');
var_dump($result);
```

## Attribute List

### Method

- `catalog_category_attribute.list` (SOAP V1)
- `catalogCategoryAttributeList` (SOAP V2)

Allows you to retrieve the list of category attributes.

### Alias

- `category_attribute.list`

### Arguments

| Type   | Name      | Description |
|--------|-----------|-------------|
| string | sessionId | Session ID  |

### Returns

| Type  | Name   | Description                     |
|-------|--------|---------------------------------|
| array | result | Array of catalogAttributeEntity |

### Content `catalogAttributeEntity`

| Type   | Name         | Description                                     |
|--------|--------------|-------------------------------------------------|
| int    | attribute_id | Attribute ID                                    |
| string | code         | Attribute code                                  |
| string | type         | Attribute type                                  |
| string | required     | Defines whether the attribute is required       |
| string | scope        | Attribute scope: `global`, `website` or `store` |

### Examples

#### Request Example SOAP V1

```php
$client = new SoapClient('https://mahohost/api/soap/?wsdl');
$session = $client->login('apiUser', 'apiKey');

$result = $client->call($session, 'catalog_category_attribute.list');
var_dump($result);

// When the session can be closed
$client->endSession($session);
```

#### Request Example SOAP V2

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl'); // replace with your store's WSDL URL
$sessionId = $proxy->login('apiUser', 'apiKey'); // replace with your API user and key

$result = $proxy->catalogCategoryAttributeList($sessionId);
var_dump($result);
```

#### Request Example SOAP V2 (WS-I Compliance Mode)

```php
$client = new SoapClient('https://mahohost/api/v2_soap/?wsdl');
$session = $client->login((object)['username' => 'apiUser', 'apiKey' => 'apiKey']);

$result = $client->catalogCategoryAttributeList((object)['sessionId' => $session->result]);
var_dump($result);
```

#### Response Example SOAP V1

```php
array
  0 => array
    'attribute_id' => null
    'code' => string 'parent_id' (length=9)
    'type' => null
    'required' => null
    'scope' => string 'global' (length=6)
  1 => array
    'attribute_id' => null
    'code' => string 'increment_id' (length=12)
    'type' => null
    'required' => null
    'scope' => string 'global' (length=6)
  2 => array
    'attribute_id' => null
    'code' => string 'updated_at' (length=10)
    'type' => null
    'required' => null
    'scope' => string 'global' (length=6)
```

## Attribute Options

### Method

- `catalog_category_attribute.options` (SOAP V1)
- `catalogCategoryAttributeOptions` (SOAP V2)

Allows you to retrieve the attribute options.

### Alias

- `category_attribute.options`

### Arguments

| Type   | Name        | Description           |
|--------|-------------|-----------------------|
| string | sessionId   | Session ID            |
| string | attributeId | Attribute ID or code  |
| string | storeView   | Store view ID or code |

### Returns

| Type  | Name   | Description                           |
|-------|--------|---------------------------------------|
| array | result | Array of catalogAttributeOptionEntity |

### Content `catalogAttributeOptionEntity`

| Type   | Name  | Description  |
|--------|-------|--------------|
| string | label | Option label |
| string | value | Option value |

### Examples

#### Request Example SOAP V1

```php
$client = new SoapClient('https://mahohost/api/soap/?wsdl');
$session = $client->login('apiUser', 'apiKey');

$result = $client->call($session, 'catalog_category_attribute.options', '65');
var_dump($result);

// When the session can be closed
$client->endSession($session);
```

#### Request Example SOAP V2

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl'); // replace with your store's WSDL URL
$sessionId = $proxy->login('apiUser', 'apiKey'); // replace with your API user and key

$result = $proxy->catalogCategoryAttributeOptions($sessionId, '65');
var_dump($result);
```

#### Request Example SOAP V2 (WS-I Compliance Mode)

```php
$client = new SoapClient('https://mahohost/api/v2_soap/?wsdl');
$session = $client->login((object)['username' => 'apiUser', 'apiKey' => 'apiKey']);

$result = $client->catalogCategoryAttributeOptions(
    (object)[
        'sessionId' => $session->result,
        'attributeId' => '65'
    ]
);
var_dump($result);
```

#### Response Example SOAP V1

```php
array
  0 => array
    'label' => string 'Yes' (length=3)
    'value' => int 1
  1 => array
    'label' => string 'No' (length=2)
    'value' => int 0
```
