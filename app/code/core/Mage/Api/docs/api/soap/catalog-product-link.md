---
description: Manage related, cross-sell, up-sell, and grouped product links over the Maho SOAP API.
---

# Catalog Product Link

!!! warning "Legacy API"
    This is Maho's legacy SOAP/XML-RPC API, inherited from Magento 1 and kept for backward compatibility. New integrations should use the modern [REST & GraphQL API (v2)](../v2/index.md) instead.

## Introduction

Allows you to manage links for products (including related), cross-sells, upsells and grouped.

### Resource Name

- `catalog_product_link`

### Alias

- `product_link`

### Methods

- `catalog_product_link.list` - Retrieve products linked to the specified product.
- `catalog_product_link.assign` - Link a product to another product.
- `catalog_product_link.update` - Update a product link.
- `catalog_product_link.remove` - Remove a product link.
- `catalog_product_link.types` - Retrieve product link types.
- `catalog_product_link.attributes` - Retrieve product link type attributes.

### Faults

| Fault Code | Fault Message                                 |
|------------|-----------------------------------------------|
| 100        | Given invalid link type.                      |
| 101        | Product not exists.                           |
| 102        | Invalid data given. Details in error message. |
| 104        | Product link not removed.                     |

### Example - Working With Product Links

```php
$proxy = new SoapClient('https://mahohost/api/soap/?wsdl');
$sessionId = $proxy->login('apiUser', 'apiKey');

// Get list of related products
var_dump($proxy->call($sessionId, 'product_link.list', ['related', 'sku']));

// Assign related product
$proxy->call($sessionId, 'product_link.assign', ['related', 'sku', 'sku2', ['position' => 0, 'qty' => 56]]);

var_dump($proxy->call($sessionId, 'product_link.list', ['related', 'sku']));

// Update related product
$proxy->call($sessionId, 'product_link.update', ['related', 'sku', 'sku2', ['position' => 2]]);

var_dump($proxy->call($sessionId, 'product_link.list', ['related', 'sku']));

// Remove related product
$proxy->call($sessionId, 'product_link.remove', ['related', 'sku', 'sku2']);

var_dump($proxy->call($sessionId, 'product_link.list', ['related', 'sku']));
```

## List

### Method

- `catalog_product_link.list` (SOAP V1)
- `catalogProductLinkList` (SOAP V2)

Allows you to retrieve the list of linked products for a specific product.

### Alias

- `product_link.list`

### Arguments

| Type   | Name           | Description                                                                |
|--------|----------------|----------------------------------------------------------------------------|
| string | sessionId      | Session ID                                                                 |
| string | type           | Type of the link (cross_sell, up_sell, related, or grouped)                |
| string | productId      | Product ID or SKU                                                          |
| string | identifierType | Defines whether the product ID or SKU is passed in the 'product' parameter |

### Returns

| Type  | Name   | Description                       |
|-------|--------|-----------------------------------|
| array | result | Array of catalogProductLinkEntity |

### Content `catalogProductLinkEntity`

| Type   | Name       | Description           |
|--------|------------|-----------------------|
| string | product_id | Product ID            |
| string | type       | Type of the link      |
| string | set        | Product attribute set |
| string | sku        | Product SKU           |
| string | position   | Position              |
| string | qty        | Quantity              |

### Examples

#### Request Example SOAP V1

```php
$client = new SoapClient('https://mahohost/api/soap/?wsdl');
$session = $client->login('apiUser', 'apiKey');

$result = $client->call($session, 'catalog_product_link.list', ['type' => 'related', 'product' => '1']);
var_dump($result);

// When the session can be closed
$client->endSession($session);
```

#### Request Example SOAP V2

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl'); // replace with your store's WSDL URL
$sessionId = $proxy->login('apiUser', 'apiKey'); // replace with your API user and key

$result = $proxy->catalogProductLinkList($sessionId, 'related', '1');
var_dump($result);
```

#### Request Example SOAP V2 (WS-I Compliance Mode)

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl');
$sessionId = $proxy->login((object)['username' => 'apiUser', 'apiKey' => 'apiKey']);

$result = $proxy->catalogProductLinkList(
    (object)[
        'sessionId' => $sessionId->result,
        'type' => 'related',
        'productId' => '1'
    ]
);
var_dump($result->result);
```

#### Response Example SOAP V1

```php
array
  0 =>
    array
      'product_id' => string '3' (length=1)
      'type' => string 'simple' (length=6)
      'set' => string '4' (length=1)
      'sku' => string 'canonxt' (length=7)
      'position' => string '1' (length=1)
  1 =>
    array
      'product_id' => string '4' (length=1)
      'type' => string 'simple' (length=6)
      'set' => string '4' (length=1)
      'sku' => string 'canon_powershot' (length=15)
      'position' => string '0' (length=1)
```

## Assign

### Method

- `catalog_product_link.assign` (SOAP V1)
- `catalogProductLinkAssign` (SOAP V2)

Allows you to assign a product link (cross_sell, grouped, related, or up_sell) to another product.

### Alias

- `product_link.assign`

### Arguments

| Type   | Name            | Description                                                                |
|--------|-----------------|----------------------------------------------------------------------------|
| string | sessionId       | Session ID                                                                 |
| string | type            | Type of the link (cross_sell, grouped, related, or up_sell)                |
| string | productId       | Product ID or SKU                                                          |
| string | linkedProductId | Product ID or SKU for the link                                             |
| array  | data            | Array of catalogProductLinkEntity                                          |
| string | identifierType  | Defines whether the product ID or SKU is passed in the 'product' parameter |

### Returns

| Type    | Description                                 |
|---------|---------------------------------------------|
| boolean | True if the link is assigned to the product |

### Content `catalogProductLinkEntity`

| Type   | Name       | Description                                                 |
|--------|------------|-------------------------------------------------------------|
| string | product_id | Product ID                                                  |
| string | type       | Type of the link (cross_sell, grouped, related, or up_sell) |
| string | set        | Product attribute set                                       |
| string | sku        | Product SKU                                                 |
| string | position   | Position of the product                                     |
| string | qty        | Quantity of products                                        |

### Examples

#### Request Example SOAP V1

```php
$client = new SoapClient('https://mahohost/api/soap/?wsdl');
$session = $client->login('apiUser', 'apikey');

$result = $client->call(
    $session,
    'catalog_product_link.assign',
    [
        'type' => 'related',
        'product' => '1',
        'linkedProduct' => '4'
    ]
);
var_dump($result);

// When the session can be closed
$client->endSession($session);
```

#### Request Example SOAP V2

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl'); // replace with your store's WSDL URL
$sessionId = $proxy->login('apiUser', 'apiKey'); // replace with your API user and key

$result = $proxy->catalogProductLinkAssign($sessionId, 'related', '1', '4');
var_dump($result);
```

#### Request Example SOAP V2 (WS-I Compliance Mode)

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl');
$sessionId = $proxy->login((object)['username' => 'apiUser', 'apiKey' => 'apiKey']);

$result = $proxy->catalogProductLinkAssign(
    (object)[
        'sessionId' => $sessionId->result,
        'type' => 'related',
        'productId' => '1',
        'linkedProductId' => '4'
    ]
);
var_dump($result->result);
```

## Update

### Method

- `catalog_product_link.update` (SOAP V1)
- `catalogProductLinkUpdate` (SOAP V2)

Allows you to update the product link.

### Alias

- `product_link.update`

### Arguments

| Type   | Name            | Description                                                                |
|--------|-----------------|----------------------------------------------------------------------------|
| string | sessionId       | Session ID                                                                 |
| string | type            | Type of the link (cross_sell, grouped, related, or up_sell)                |
| string | productId       | Product ID or SKU                                                          |
| string | linkedProductId | Product ID or SKU for the link                                             |
| array  | data            | Array of catalogProductLinkEntity                                          |
| string | identifierType  | Defines whether the product ID or SKU is passed in the 'product' parameter |

### Returns

| Type       | Name   | Description                     |
|------------|--------|---------------------------------|
| booleanint | result | True (1) if the link is updated |

### Content `catalogProductLinkEntity`

| Type   | Name       | Description           |
|--------|------------|-----------------------|
| string | product_id | Product ID            |
| string | type       | Type of the link      |
| string | set        | Product attribute set |
| string | sku        | Product SKU           |
| string | position   | Position              |
| string | qty        | Quantity              |

### Examples

#### Request Example SOAP V1

```php
$proxy = new SoapClient('https://mahohost/api/soap/?wsdl');
$sessionId = $proxy->login('apiUser', 'apiKey');

$productId = '1';
$linkedProductId = '2';
$data = [
	'position' => '50'
];

$result = $proxy->call(
	$session,
	'catalog_product_link.update',
	[
		'cross_sell',
		$productId,
		$linkedProductId,
		$data
	]
);
```

#### Request Example SOAP V2

```php
$client = new SoapClient('https://mahohost/api/v2_soap/?wsdl');
$session = $client->login('apiUser', 'apiKey');

$data = [
   'position' => 15
  ];

$identifierType = 'product_id';
$type = 'related';
$product = '1';
$linkedProduct = '3';

$orders = $client->catalogProductLinkUpdate(
    $session,
    $type,
    $product,
    $linkedProduct,
    $data,
    $identifierType
);

echo 'Number of results: ' . count($orders) . '<br>';
var_dump($orders);
```

#### Request Example SOAP V2 (WS-I Compliance Mode)

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl');
$sessionId = $proxy->login((object)['username' => 'apiUser', 'apiKey' => 'apiKey']);

$result = $proxy->catalogProductLinkUpdate(
    (object)[
        'sessionId' => $sessionId->result,
        'type' => 'cross_sell',
        'productId' => '1',
        'linkedProductId' => '2',
        'data' => (object)[
            'position' => '1'
        ]
    ]
);
var_dump($result->result);
```

## Remove

### Method

- `catalog_product_link.remove` (SOAP V1)
- `catalogProductLinkRemove` (SOAP V2)

Allows you to remove the product link from a specific product.

### Alias

- `product_link.remove`

### Arguments

| Type   | Name            | Description                                                                |
|--------|-----------------|----------------------------------------------------------------------------|
| string | sessionId       | Session ID                                                                 |
| string | type            | Type of the link (cross_sell, up_sell, related, or grouped)                |
| string | productId       | Product ID or SKU                                                          |
| string | linkedProductId | Product ID or SKU for the link                                             |
| string | identifierType  | Defines whether the product ID or SKU is passed in the 'product' parameter |

### Returns

| Type       | Description                                    |
|------------|------------------------------------------------|
| booleanint | True (1) if the link is removed from a product |

### Examples

#### Request Example SOAP V1

```php
$client = new SoapClient('https://mahohost/api/soap/?wsdl');
$session = $client->login('apiUser', 'apiKey');

$result = $client->call(
    $session,
    'catalog_product_link.remove',
    [
        'type' => 'related',
        'product' => '1',
        'linkedProduct' => '4'
    ]
);
var_dump($result);

// When the session can be closed
$client->endSession($session);
```

#### Request Example SOAP V2

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl'); // replace with your store's WSDL URL
$sessionId = $proxy->login('apiUser', 'apiKey'); // replace with your API user and key

$result = $proxy->catalogProductLinkRemove($sessionId, 'related', '1', '4');
var_dump($result);
```

#### Request Example SOAP V2 (WS-I Compliance Mode)

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl');
$sessionId = $proxy->login((object)['username' => 'apiUser', 'apiKey' => 'apiKey']);

$result = $proxy->catalogProductLinkRemove(
    (object)[
        'sessionId' => $sessionId->result,
        'type' => 'related',
        'productId' => '1',
        'linkedProductId' => '4'
    ]
);
var_dump($result->result);
```

## Types

### Method

- `catalog_product_link.types` (SOAP V1)
- `catalogProductLinkTypes` (SOAP V2)

Allows you to retrieve the list of product link types.

### Alias

- `product_link.types`

### Arguments

| Type   | Name      | Description |
|--------|-----------|-------------|
| string | sessionId | Session ID  |

### Returns

| Type          | Name   | Description         |
|---------------|--------|---------------------|
| ArrayOfString | result | Array of link types |

### Examples

#### Request Example SOAP V1

```php
$client = new SoapClient('https://mahohost/api/soap/?wsdl');
$session = $client->login('apiUser', 'apiKey');

$result = $client->call($session, 'product_link.types');
var_dump($result);

// When the session can be closed
$client->endSession($session);
```

#### Request Example SOAP V2

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl'); // replace with your store's WSDL URL
$sessionId = $proxy->login('apiUser', 'apiKey'); // replace with your API user and key

$result = $proxy->catalogProductLinkTypes($sessionId);
var_dump($result);
```

#### Request Example SOAP V2 (WS-I Compliance Mode)

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl');
$sessionId = $proxy->login((object)['username' => 'apiUser', 'apiKey' => 'apiKey']);

$result = $proxy->catalogProductLinkTypes((object)['sessionId' => $sessionId->result]);
var_dump($result->result);
```

#### Response Example SOAP V1

```php
array
  0 => string 'related' (length=7)
  1 => string 'up_sell' (length=7)
  2 => string 'cross_sell' (length=10)
  3 => string 'grouped' (length=7)
```

## Attributes

### Method

- `catalog_product_link.attributes` (SOAP V1)
- `catalogProductLinkAttributes` (SOAP V2)

Allows you to retrieve the product link type attributes.

### Alias

- `product_link.attributes`

### Arguments

| Type   | Name      | Description                                                 |
|--------|-----------|-------------------------------------------------------------|
| string | sessionId | Session ID                                                  |
| string | type      | Type of the link (cross_sell, up_sell, related, or grouped) |

### Returns

| Type  | Name   | Description                                |
|-------|--------|--------------------------------------------|
| array | result | Array of catalogProductLinkAttributeEntity |

### Content `catalogProductLinkAttributeEntity`

| Type   | Name | Description    |
|--------|------|----------------|
| string | code | Attribute code |
| string | type | Attribute type |

### Examples

#### Request Example SOAP V1

```php
$client = new SoapClient('https://mahohost/api/soap/?wsdl');
$session = $client->login('apiUser', 'apiKey');

$result = $client->call($session, 'catalog_product_link.attributes', 'related');
var_dump($result);

// When the session can be closed
$client->endSession($session);
```

#### Request Example SOAP V2

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl'); // replace with your store's WSDL URL
$sessionId = $proxy->login('apiUser', 'apiKey'); // replace with your API user and key

$result = $proxy->catalogProductLinkAttributes($sessionId, 'related');
var_dump($result);
```

#### Request Example SOAP V2 (WS-I Compliance Mode)

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl');
$sessionId = $proxy->login((object)['username' => 'apiUser', 'apiKey' => 'apiKey']);

$result = $proxy->catalogProductLinkAttributes((object)['sessionId' => $sessionId->result, 'type' => 'related']);
var_dump($result->result);
```

#### Response Example SOAP V1

```php
array
  0 =>
    array
      'code' => string 'position' (length=8)
      'type' => string 'int' (length=3)
```
