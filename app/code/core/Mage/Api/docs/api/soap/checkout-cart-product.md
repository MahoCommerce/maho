---
description: Manage products in a shopping cart over the Maho SOAP API - add, update, remove, list, and move to the customer quote.
---

# Checkout Cart Product

!!! warning "Legacy API"
    This is Maho's legacy SOAP/XML-RPC API, inherited from Magento 1 and kept for backward compatibility. New integrations should use the modern [REST & GraphQL API (v2)](../v2/index.md) instead.

## Introduction

Allows you to manage products in a shopping cart.

### Resource Name

- `cart_product`

### Methods

- `cart_product.add` - Add one or more products to a shopping cart.
- `cart_product.update` - Update one or more products in a shopping cart.
- `cart_product.remove` - Remove one or more products from a shopping cart.
- `cart_product.list` - Get a list of products in a shopping cart.
- `cart_product.moveToCustomerQuote` - Move one or more products from the quote to the customer shopping cart.

### Faults

| Fault Code | Fault Message                                                       |
|------------|---------------------------------------------------------------------|
| 1001       | Can not make operation because store is not exists                  |
| 1002       | Can not make operation because quote is not exists                  |
| 1021       | Product’s data is not valid.                                        |
| 1022       | Product(s) could not be added.                                      |
| 1023       | Quote could not be saved during adding product(s) operation.        |
| 1024       | Product(s) could not be updated.                                    |
| 1025       | Quote could not be saved during updating product(s) operation.      |
| 1026       | Product(s) could not be removed.                                    |
| 1027       | Quote could not be saved during removing product(s) operation.      |
| 1028       | Customer is not set for quote.                                      |
| 1029       | Customer’s quote is not existed.                                    |
| 1030       | Quotes are identical.                                               |
| 1031       | Product(s) could not be moved.                                      |
| 1032       | One of quote could not be saved during moving product(s) operation. |

## Add

### Method

- `cart_product.add` (SOAP V1)
- `shoppingCartProductAdd` (SOAP V2)

Allows you to add one or more products to the shopping cart (quote).

### Arguments

| Type   | Name                 | Description                                         |
|--------|----------------------|-----------------------------------------------------|
| string | sessionId            | Session ID                                          |
| int    | quoteId              | Shopping cart ID (quote ID)                         |
| array  | productsproductsData | An array with the list of shoppingCartProductEntity |
| string | storeId              | Store view ID or code (optional)                    |

### Returns

| Type    | Description                                                    |
|---------|----------------------------------------------------------------|
| boolean | True on success (if the product is added to the shopping cart) |

### Content `shoppingCartProductEntity`

| Type             | Name              | Description                                                            |
|------------------|-------------------|------------------------------------------------------------------------|
| string           | product_id        | ID of the product to be added to the shopping cart (quote) (optional)  |
| string           | sku               | SKU of the product to be added to the shopping cart (quote) (optional) |
| double           | qty               | Number of products to be added to the shopping cart (quote) (optional) |
| associativeArray | options           | An array in the form of option_id => content (optional)                |
| associativeArray | bundle_option     | An array of bundle item options (optional)                             |
| associativeArray | bundle_option_qty | An array of bundle items quantity (optional)                           |
| ArrayOfString    | links             | An array of links (optional)                                           |

### Faults  

_No Faults._

### Examples

#### Request Example SOAP V1

```php
$proxy = new SoapClient('https://mahohost/api/soap/?wsdl');
$sessionId = $proxy->login('apiUser', 'apiKey');

$quoteId = $proxy->call($sessionId, 'cart.create', ['maho_store']);
$arrProducts = [
	[
		'product_id' => '1',
		'qty' => 2
                'options' => [
                    optionId_1 => optionValue_1,
                    ...
                    optionId_n => optionValue_n
                 ]
	],
	[
		'sku' => 'testSKU',
		'quantity' => 4
	]
];
$resultCartProductAdd = $proxy->call(
	$sessionId,
	'cart_product.add',
	[
		$quoteId,
		$arrProducts
	]
);
```

#### Request Example SOAP V2

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl'); 
$sessionId = $proxy->login('apiUser', 'apiKey'); 
  
$result = $proxy->shoppingCartProductAdd(
    $sessionId,
    10,
    [
        [
            'product_id' => '4',
            'sku' => 'simple_product',
            'qty' => '5',
            'options' => null,
            'bundle_option' => null,
            'bundle_option_qty' => null,
            'links' => null
        ]
    ]
);
var_dump($result);
```

#### Request Example SOAP V2 (WS-I Compliance Mode)

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl');
$sessionId = $proxy->login((object)['username' => 'apiUser', 'apiKey' => 'apiKey']);

$result = $proxy->shoppingCartProductAdd(
    (object)[
        'sessionId' => $sessionId->result,
        'quoteId' => 10,
        'productsData' => [
            [
                'product_id' => '4',
                'sku' => 'simple_product',
                'qty' => '1',
                'options' => null,
                'bundle_option' => null,
                'bundle_option_qty' => null,
                'links' => null
            ]
        ]
    ]
);
var_dump($result->result);
```

## Update

### Method

- `cart_product.update` (SOAP V1)
- `shoppingCartProductUpdate` (SOAP V2)

Allows you to update one or several products in the shopping cart (quote).

### Arguments

| Type   | Name         | Description                        |
|--------|--------------|------------------------------------|
| string | sessionId    | Session ID                         |
| int    | quoteId      | Shopping cart ID                   |
| array  | productsData | Array of shoppingCartProductEntity |
| string | store        | Store view ID or code (optional)   |

### Return

| Type    | Description                    |
|---------|--------------------------------|
| boolean | True if the product is updated |

### Content `shoppingCartProductEntity`

| Type             | Name              | Description                                  |
|------------------|-------------------|----------------------------------------------|
| string           | product_id        | Product ID                                   |
| string           | sku               | Product SKU                                  |
| double           | qty               | Product quantity                             |
| associativeArray | options           | Product custom options                       |
| associativeArray | bundle_option     | An array of bundle item options (optional)   |
| associativeArray | bundle_option_qty | An array of bundle items quantity (optional) |
| ArrayOfString    | links             | An array of links (optional)                 |

### Faults

_No Faults._

### Examples

#### Request Example SOAP V1

```php
$proxy = new SoapClient('https://mahohost/api/soap/?wsdl');
$sessionId = $proxy->login('apiUser', 'apiKey');

$shoppingCartIncrementId = $proxy->call($sessionId, 'cart.create', ['maho_store']);
$arrProducts = [
	[
		'product_id' => '1',
		'qty' => 2
	],
	[
		'sku' => 'testSKU',
		'quantity' => 4
	]
];
$resultCartProductAdd = $proxy->call(
	$sessionId,
	'cart_product.add',
	[
		$shoppingCartId,
		$arrProducts
	]
);
$arrProducts = [
	[
		'product_id' => '1',
		'qty' => 5
	],
];
$resultCartProductUpdate = $proxy->call(
	$sessionId,
	'cart_product.update',
	[
		$shoppingCartId,
		$arrProducts
	]
);
```

#### Request Example SOAP V2

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl'); 
$sessionId = $proxy->login('apiUser', 'apiKey'); 
  
$result = $proxy->shoppingCartProductUpdate(
    $sessionId,
    10,
    [
        [
            'product_id' => '4',
            'sku' => 'simple_product',
            'qty' => '2',
            'options' => null,
            'bundle_option' => null,
            'bundle_option_qty' => null,
            'links' => null
        ]
    ]
);
var_dump($result);
```

#### Request Example SOAP V2 (WS-I Compliance Mode)

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl'); 
$sessionId = $proxy->login((object)['username' => 'apiUser', 'apiKey' => 'apiKey']); 
  
$result = $proxy->shoppingCartProductUpdate(
    (object)[
        'sessionId' => $sessionId->result,
        'quoteId' => 10,
        'productsData' => [
            [
                'product_id' => '4',
                'sku' => 'simple_product',
                'qty' => '5',
                'options' => null,
                'bundle_option' => null,
                'bundle_option_qty' => null,
                'links' => null
            ]
        ]
    ]
);
var_dump($result->result);
```

## Remove

### Method

- `cart_product.remove` (SOAP V1)
- `shoppingCartProductRemove` (SOAP V2)

Allows you to remove one or several products from a shopping cart (quote).

### Arguments

| Type   | Name         | Description                        |
|--------|--------------|------------------------------------|
| string | sessionId    | Session ID                         |
| int    | quoteId      | Shopping cart ID                   |
| array  | productsData | Array of shoppingCartProductEntity |
| string | store        | Store view ID or code (optional)   |

### Return

| Type    | Description                    |
|---------|--------------------------------|
| boolean | True if the product is removed |

### Content `shoppingCartProductEntity`

| Type             | Name              | Description                                  |
|------------------|-------------------|----------------------------------------------|
| string           | product_id        | Product ID                                   |
| string           | sku               | Product SKU                                  |
| double           | qty               | Product quantity                             |
| associativeArray | options           | Product custom options                       |
| associativeArray | bundle_option     | An array of bundle item options (optional)   |
| associativeArray | bundle_option_qty | An array of bundle items quantity (optional) |
| ArrayOfString    | links             | An array of links (optional)                 |

### Faults

_No Faults._

### Examples

#### Request Example SOAP V1

```php
$proxy = new SoapClient('https://mahohost/api/soap/?wsdl');
$sessionId = $proxy->login('apiUser', 'apiKey');

$shoppingCartIncrementId = $proxy->call($sessionId, 'cart.create', ['maho_store']);
$arrProducts = [
	[
		'product_id' => '1',
		'qty' => 2
	],
	[
		'sku' => 'testSKU',
		'quantity' => 4
	]
];
$resultCartProductAdd = $proxy->call(
	$sessionId,
	'cart_product.add',
	[
		$shoppingCartId,
		$arrProducts
	]
);
$arrProducts = [
	[
		'product_id' => '1'
	],
];
$resultCartProductUpdate = $proxy->call(
	$sessionId,
	'cart_product.remove',
	[
		$shoppingCartId,
		$arrProducts
	]
);
```

#### Request Example SOAP V2

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl'); 
$sessionId = $proxy->login('apiUser', 'apiKey'); 
 
$result = $proxy->shoppingCartProductRemove(
    $sessionId,
    10,
    [
        [
            'product_id' => '4',
            'sku' => 'simple_product',
            'qty' => '1',
            'options' => null,
            'bundle_option' => null,
            'bundle_option_qty' => null,
            'links' => null
        ]
    ]
);
var_dump($result);
```

#### Request Example SOAP V2 (WS-I Compliance Mode)

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl'); 
$sessionId = $proxy->login((object)['username' => 'apiUser', 'apiKey' => 'apiKey']); 
 
$result = $proxy->shoppingCartProductRemove(
    (object)[
        'sessionId' => $sessionId->result,
        'quoteId' => 10,
        'productsData' => [
            [
                'product_id' => '4',
                'sku' => 'simple_product',
                'qty' => '1',
                'options' => null,
                'bundle_option' => null,
                'bundle_option_qty' => null,
                'links' => null
            ]
        ]
    ]
);
var_dump($result->result);
```

## List

### Method

- `cart_product.list` (SOAP V1)
- `shoppingCartProductList` (SOAP V2)

Allows you to retrieve the list of products in the shopping cart (quote).

### Arguments

| Type   | Name      | Description                      |
|--------|-----------|----------------------------------|
| string | sessionId | Session ID                       |
| int    | quoteId   | Shopping cart ID                 |
| string | store     | Store view ID or code (optional) |

### Return

| Type  | Name   | Description                                |
|-------|--------|--------------------------------------------|
| array | result | Array of shoppingCartProductResponseEntity |

### Content `shoppingCartProductResponseEntity` (`catalogProductEntity`)

| Type          | Name         | Description           |
|---------------|--------------|-----------------------|
| string        | product_id   | Product ID            |
| string        | sku          | Product SKU           |
| string        | name         | Product name          |
| string        | set          | Product attribute set |
| string        | type         | Product type          |
| ArrayOfString | category_ids | Array of category IDs |
| ArrayOfString | website_ids  | Array of website IDs  |

### Faults

_No Faults._

### Examples

#### Request Example SOAP V1

```php
$client = new SoapClient('https://mahohost/api/soap/?wsdl');
$session = $client->login('apiUser', 'apiKey');

$result = $client->call($session, 'cart_product.list', '15');
var_dump($result);
```

#### Request Example SOAP V2

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl'); // replace with your store's WSDL URL
$sessionId = $proxy->login('apiUser', 'apiKey'); // replace with your API user and key

$result = $proxy->shoppingCartProductList($sessionId, '15');
var_dump($result);
```

#### Request Example SOAP V2 (WS-I Compliance Mode)

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl'); 
$sessionId = $proxy->login((object)['username' => 'apiUser', 'apiKey' => 'apiKey']); 
 
$result = $proxy->shoppingCartProductList((object)['sessionId' => $sessionId->result, 'quoteId' => 15]);   
var_dump($result->result);
```

#### Response Example SOAP V1

```php
array
  0 =>
    array
      'product_id' => string '3' (length=1)
      'sku' => string 'canonxt' (length=7)
      'name' => string 'Canon Digital Rebel XT 8MP Digital SLR Camera' (length=45)
      'set' => string '4' (length=1)
      'type' => string 'simple' (length=6)
      'category_ids' =>
        array
          0 => string '5' (length=1)
      'website_ids' =>
        array
          0 => string '2' (length=1)
```

## MoveToCustomerQuote

### Method

- `cart_product.moveToCustomerQuote` (SOAP V1)
- `shoppingCartProductMoveToCustomerQuote` (SOAP V2)

Allows you to move products from the current quote to a customer quote.

### Arguments

| Type   | Name         | Description                        |
|--------|--------------|------------------------------------|
| string | sessionId    | Session ID                         |
| int    | quoteId      | Shopping cart ID                   |
| array  | productsData | Array of shoppingCartProductEntity |
| string | store        | Store view ID or code (optional)   |

### Return

| Type    | Name   | Description                                    |
|---------|--------|------------------------------------------------|
| boolean | result | True if the product is moved to customer quote |

### Content `shoppingCartProductEntity`

| Type             | Name              | Description                                  |
|------------------|-------------------|----------------------------------------------|
| string           | product_id        | Product ID                                   |
| string           | sku               | Product SKU                                  |
| double           | qty               | Product quantity                             |
| associativeArray | options           | Product custom options                       |
| associativeArray | bundle_option     | An array of bundle item options (optional)   |
| associativeArray | bundle_option_qty | An array of bundle items quantity (optional) |
| ArrayOfString    | links             | An array of links (optional)                 |

### Faults

_No Faults._

### Examples

#### Request Example SOAP V1

```php
$proxy = new SoapClient('https://mahohost/api/soap/?wsdl');
$sessionId = $proxy->login('apiUser', 'apiKey');

$shoppingCartIncrementId = $proxy->call($sessionId, 'cart.create', ['maho_store']);
$arrProducts = [
	[
		'product_id' => '1',
		'qty' => 2
	],
	[
		'sku' => 'testSKU',
		'quantity' => 4
	]
];
$resultCartProductAdd = $proxy->call(
	$sessionId,
	'cart_product.add',
	[
		$shoppingCartId,
		$arrProducts
	]
);
$arrProducts = [
	[
		'product_id' => '1'
	],
];
$resultCartProductMove = $proxy->call(
	$sessionId,
	'cart_product.moveToCustomerQuote',
	[
		$shoppingCartId,
		$arrProducts
	]
);
```
