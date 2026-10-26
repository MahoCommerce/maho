---
description: Manage products over the Maho SOAP API - list, info, create, update, delete, and get or set the special price.
---

# Catalog Product

!!! warning "Legacy API"
    This is Maho's legacy SOAP/XML-RPC API, inherited from Magento 1 and kept for backward compatibility. New integrations should use the modern [REST & GraphQL API (v2)](../v2/index.md) instead.

## Introduction

Allows you to manage products.

### Resource Name

- `catalog_product`

### Alias

- `product`

### Methods

- `catalog_product.currentStore` - Set/Get the current store view.
- `catalog_product.list` - Retrieve the list of products using filters.
- `catalog_product.info` - Retrieve information about the required product.
- `catalog_product.create` - Create a new product.
- `catalog_product.update` - Update a required product.
- `catalog_product.setSpecialPrice` - Set special price for a product.
- `catalog_product.getSpecialPrice` - Get special price for a product.
- `catalog_product.delete` - Delete a required product.
- `catalog_product.listOfAdditionalAttributes` - Get the list of additional attributes.

### Faults

| Fault Code | Fault Message                                  |
|------------|------------------------------------------------|
| 100        | Requested store view not found.                |
| 101        | Product not exists.                            |
| 102        | Invalid data given. Details in error message.  |
| 103        | Product not deleted. Details in error message. |

### Examples

#### Retrieving the Product List

```php
$proxy = new SoapClient('https://mahohost/api/soap/?wsdl');
$sessionId = $proxy->login('apiUser', 'apiKey');

$filters = [
    'sku' => ['like'=>'zol%']
];

$products = $proxy->call($sessionId, 'product.list', [$filters]);
var_dump($products);
```

#### Creating, Viewing, Updating and Deleting a Product

```php
$proxy = new SoapClient('https://mahohost/api/soap/?wsdl');
$sessionId = $proxy->login('apiUser', 'apiKey');

$attributeSets = $proxy->call($sessionId, 'product_attribute_set.list');
$attributeSet = current($attributeSets);

$newProductData = [
    'name' => 'name of product',
    'websites' => [1],
    'short_description' => 'short description',
    'description' => 'description',
    'status' => 1,
    'weight' => 0,
    'tax_class_id' => 1,
    'categories' => [3], // referenced by category IDs
    'price' => 12.05
];

// Create new product
$proxy->call(
    $sessionId,
    'product.create',
    [
        'simple',
        $attributeSet['set_id'],
        'sku_of_product',
        $newProductData
    ]
);
$proxy->call(
    $sessionId,
    'product_stock.update',
    [
        'sku_of_product',
        [
            'qty' => 50,
            'is_in_stock' => 1
        ]
    ]
);

// Get info of created product
var_dump($proxy->call($sessionId, 'product.info', 'sku_of_product'));

// Update product name on german store view
$proxy->call(
    $sessionId,
    'product.update',
    [
        'sku_of_product',
        [
            'name' => 'new name of product'
        ],
        'german'
    ]
);

// Get info for default values
var_dump($proxy->call($sessionId, 'product.info', 'sku_of_product'));
// Get info for german store view

var_dump($proxy->call($sessionId, 'product.info', ['sku_of_product', 'german']));

// Delete product
$proxy->call($sessionId, 'product.delete', 'sku_of_product');

try {
    // Ensure that product deleted
    var_dump($proxy->call($sessionId, 'product.info', 'sku_of_product'));
} catch (SoapFault $e) {
    echo 'Product already deleted';
}
```

## CurrentStore

### Method

- `catalog_product.currentStore` (SOAP V1)
- `catalogProductCurrentStore` (SOAP V2)

Allows you to set/get the current store view.

### Alias

- `product.currentStore`

### Arguments

| Type   | Name      | Description                      |
|--------|-----------|----------------------------------|
| string | sessionId | Session ID                       |
| string | storeView | Store view ID or code (optional) |

### Returns

| Type       | Name      | Description   |
|------------|-----------|---------------|
| int/string | storeView | Store view ID |

### Examples

#### Request Example SOAP V1

```php
$client = new SoapClient('https://mahohost/api/soap/?wsdl');
$session = $client->login('apiUser', 'apiKey');

$result = $client->call($session, 'catalog_product.currentStore', 'english');
var_dump($result);
```

#### Request Example SOAP V2

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl'); // replace with your store's WSDL URL
$sessionId = $proxy->login('apiUser', 'apiKey'); // replace with your API user and key
 
$result = $proxy->catalogProductCurrentStore($sessionId, 'english');
var_dump($result);
```

## List

### Method

- `catalog_product.list` (SOAP V1)
- `catalogProductList` (SOAP V2)

Allows you to retrieve the list of products.

### Alias

- `product.list`

### Arguments

| Type   | Name      | Description                               |
|--------|-----------|-------------------------------------------|
| string | sessionId | Session ID                                |
| array  | filters   | Array of filters by attributes (optional) |
| string | storeView | Store view ID or code (optional)          |

### Returns

| Type  | Name      | Description                   |
|-------|-----------|-------------------------------|
| array | storeView | Array of catalogProductEntity |

### Content `catalogProductEntity`

| Type          | Name         | Description           |
|---------------|--------------|-----------------------|
| string        | product_id   | Product ID            |
| string        | sku          | Product SKU           |
| string        | name         | Product name          |
| string        | set          | Product attribute set |
| string        | type         | Type of the product   |
| ArrayOfString | category_ids | Array of category IDs |
| ArrayOfString | website_ids  | Array of website IDs  |

### Examples

#### Request Example SOAP V1

```php
$client = new SoapClient('https://mahohost/api/soap/?wsdl');
$session = $client->login('apiUser', 'apiKey');

$result = $client->call($session, 'catalog_product.list');
var_dump($result);

// When the session can be closed
$client->endSession($session);
```

#### Request Example SOAP V2 (List of All Products)

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl'); // replace with your store's WSDL URL
$sessionId = $proxy->login('apiUser', 'apiKey'); // replace with your API user and key

$result = $proxy->catalogProductList($sessionId);
var_dump($result);
```

#### Request Example SOAP V2 (Complex Filter)

```php
$client = new SoapClient('https://mahohost/api/v2_soap/?wsdl');
$session = $client->login('apiUser', 'apiKey');

$complexFilter = [
    'complex_filter' => [
        [
            'key' => 'type',
            'value' => ['key' => 'in', 'value' => 'simple,configurable']
        ]
    ]
];
$result = $client->catalogProductList($session, $complexFilter);
var_dump($result);
```

#### Request Example SOAP V2 (WS-I Compliance Mode)

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl');
$sessionId = $proxy->login((object)['username' => 'apiUser', 'apiKey' => 'apiKey']);

$result = $proxy->catalogProductList((object)['sessionId' => $sessionId->result, 'filters' => null]);
var_dump($result->result);
```

#### Response Example SOAP V1

```php
array
  0 =>
    array
      'product_id' => string '1' (length=1)
      'sku' => string 'n2610' (length=5)
      'name' => string 'Nokia 2610 Phone' (length=16)
      'set' => string '4' (length=1)
      'type' => string 'simple' (length=6)
      'category_ids' =>
        array
          0 => string '4' (length=1)
  1 =>
    array
      'product_id' => string '2' (length=1)
      'sku' => string 'b8100' (length=5)
      'name' => string 'BlackBerry 8100 Pearl' (length=21)
      'set' => string '4' (length=1)
      'type' => string 'simple' (length=6)
      'category_ids' =>
        array
          0 => string '4' (length=1)
```

## Info

### Method

- `catalog_product.info` (SOAP V1)
- `catalogProductInfo` (SOAP V2)

Allows you to retrieve information about the required product.

### Alias

- `product.info`

### Arguments

| Type   | Name           | Description                                                                                    |
|--------|----------------|------------------------------------------------------------------------------------------------|
| string | sessionId      | Session ID                                                                                     |
| string | productId      | Product ID or SKU                                                                              |
| string | storeView      | Store view ID or code (optional)                                                               |
| array  | attributes     | Array of catalogProductRequestAttributes (optional)                                            |
| string | identifierType | Defines whether the product ID or SKU value is passed in the `productId` parameter. (optional) |

### Returns

| Type  | Name | Description                         |
|-------|------|-------------------------------------|
| array | info | Array of catalogProductReturnEntity |

### Content `catalogProductRequestAttributes`

| Type          | Name                  | Description                    |
|---------------|-----------------------|--------------------------------|
| ArrayOfString | attributes            | Array of attributes            |
| ArrayOfString | additional_attributes | Array of additional attributes |

### Content `catalogProductReturnEntity`

| Type             | Name                   | Description                                                          |
|------------------|------------------------|----------------------------------------------------------------------|
| string           | product_id             | Product ID                                                           |
| string           | sku                    | Product SKU                                                          |
| string           | set                    | Product set                                                          |
| string           | type                   | Product type                                                         |
| ArrayOfString    | categories             | Array of categories                                                  |
| ArrayOfString    | websites               | Array of websites                                                    |
| string           | created_at             | Date when the product was created                                    |
| string           | updated_at             | Date when the product was last updated                               |
| string           | type_id                | Type ID                                                              |
| string           | name                   | Product name                                                         |
| string           | description            | Product description                                                  |
| string           | short_description      | Short description for a product                                      |
| string           | weight                 | Product weight                                                       |
| string           | status                 | Status of a product                                                  |
| string           | url_key                | Relative URL path that can be entered in place of a target path      |
| string           | url_path               | URL path                                                             |
| string           | visibility             | Product visibility on the frontend                                   |
| ArrayOfString    | category_ids           | Array of category IDs                                                |
| ArrayOfString    | website_ids            | Array of website IDs                                                 |
| string           | has_options            | Defines whether the product has options                              |
| string           | gift_message_available | Defines whether the gift message is available for the product        |
| string           | price                  | Product price                                                        |
| string           | special_price          | Product special price                                                |
| string           | special_from_date      | Date starting from which the special price is applied to the product |
| string           | special_to_date        | Date till which the special price is applied to the product          |
| string           | tax_class_id           | Tax class ID                                                         |
| array            | tier_price             | Array of catalogProductTierPriceEntity                               |
| string           | meta_title             | Mate title                                                           |
| string           | meta_keyword           | Meta keyword                                                         |
| string           | meta_description       | Meta description                                                     |
| string           | custom_design          | Custom design                                                        |
| string           | custom_layout_update   | Custom layout update                                                 |
| string           | options_container      | Options container                                                    |
| associativeArray | additional_attributes  | Array of additional attributes                                       |
| string           | enable_googlecheckout  | Defines whether Google Checkout is applied to the product            |

### Content `catalogProductTierPriceEntity`

| Type   | Name              | Description                                 |
|--------|-------------------|---------------------------------------------|
| string | customer_group_id | ID of the customer group                    |
| string | website           | Website                                     |
| int    | qty               | Quantity to which the price will be applied |
| double | price             | Price that each item will cost              |

### Examples

#### Request Example SOAP V1

```php
$client = new SoapClient('https://mahohost/api/soap/?wsdl');
$session = $client->login('apiUser', 'apiKey');

$result = $client->call($session, 'catalog_product.info', '4');
var_dump($result);

// When the session can be closed
$client->endSession($session);
```

#### Request Example SOAP V2

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl'); // replace with your store's WSDL URL
$sessionId = $proxy->login('apiUser', 'apiKey'); // replace with your API user and key

$result = $proxy->catalogProductInfo($sessionId, '4');
var_dump($result);
```

#### Request Example SOAP V2 (WS-I Compliance Mode)

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl');
$sessionId = $proxy->login((object)['username' => 'apiUser', 'apiKey' => 'apiKey']);

$result = $proxy->catalogProductInfo((object)['sessionId' => $sessionId->result, 'productId' => '4']);
var_dump($result->result);
```

#### Response Example SOAP V1

```php
array
  'product_id' => string '4' (length=1)
  'sku' => string 'canon_powershot' (length=15)
  'set' => string '4' (length=1)
  'type' => string 'simple' (length=6)
  'categories' =>
    array
      0 => string '3' (length=1)
      1 => string '5' (length=1)
  'websites' =>
    array
      0 => string '2' (length=1)
  'type_id' => string 'simple' (length=6)
  'name' => string 'Canon PowerShot A630 8MP Digital Camera with 4x Optical Zoom' (length=60)
  'description' => string 'Replacing the highly popular PowerShot A620, the PowerShot A630 features a rotating 2.5-inch vari-angle LCD, 4x optical zoom lens, and a vast array of creative shooting modes.

The PowerShot A630 packs a vast array of advanced features into a remarkably compact space' (length=267)
  'short_description' => string 'Replacing the highly popular PowerShot A620, the PowerShot A630 features a rotating 2.5-inch vari-angle LCD, 4x optical zoom lens, and a vast array of creative shooting modes.' (length=175)
  'weight' => string '1.0000' (length=6)
  'old_id' => null
  'news_from_date' => null
  'news_to_date' => null
  'status' => string '1' (length=1)
  'url_key' => string 'canon-powershot-a630-8mp-digital-camera-with-4x-optical-zoom' (length=60)
  'url_path' => string 'canon-powershot-a630-8mp-digital-camera-with-4x-optical-zoom.html' (length=65)
  'visibility' => string '4' (length=1)
  'category_ids' =>
    array
      0 => string '3' (length=1)
      1 => string '5' (length=1)
  'required_options' => string '0' (length=1)
  'has_options' => string '0' (length=1)
  'image_label' => null
  'created_at' => string '2012-03-29 12:47:56' (length=19)
  'updated_at' => string '2012-03-29 12:47:56' (length=19)
  'country_of_manufacture' => null
  'price' => string '329.9900' (length=8)
  'group_price' =>
    array
      empty
  'special_price' => null
  'special_from_date' => null
  'special_to_date' => null
  'tier_price' =>
    array
      empty
  'minimal_price' => null
  'msrp_enabled' => string '2' (length=1)
  'msrp_display_actual_price_type' => string '4' (length=1)
  'msrp' => null
  'enable_googlecheckout' => string '1' (length=1)
  'tax_class_id' => string '2' (length=1)
  'meta_title' => null
  'meta_keyword' => null
  'meta_description' => null
  'is_recurring' => string '0' (length=1)
  'recurring_profile' => null
  'custom_design' => null
  'custom_design_from' => null
  'custom_design_to' => null
  'custom_layout_update' => null
  'page_layout' => null
  'options_container' => string 'container2' (length=10)
  'gift_message_available' => null
```

## Create

### Method

- `catalog_product.create` (SOAP V1)
- `catalogProductCreate` (SOAP V2)

Allows you to create a new product and return ID of the created product.

### Alias

- `product.create`

**Note:** Although the API accepts up to four digits of precision for all price arguments,
Maho strongly recommends you pass in two digits to reduce inaccuracy in the tax calculation process.
(That is, use a price like `12.35` and not `12.3487`).

### Arguments

| Type   | Name        | Description                         |
|--------|-------------|-------------------------------------|
| string | sessionId   | Session ID                          |
| string | type        | Product type                        |
| string | set         | ID of the product attribute set     |
| string | sku         | Product SKU                         |
| array  | productData | Array of catalogProductCreateEntity |
| string | storeView   | Store view ID or code               |

### Returns

| Type | Name   | Description               |
|------|--------|---------------------------|
| int  | result | ID of the created product |

### Content `catalogProductCreateEntity`

| Type          | Name                   | Description                                                               |
|---------------|------------------------|---------------------------------------------------------------------------|
| ArrayOfString | categories             | Array of categories                                                       |
| ArrayOfString | websites               | Array of websites                                                         |
| string        | name                   | Product name                                                              |
| string        | description            | Product description                                                       |
| string        | short_description      | Product short description                                                 |
| string        | weight                 | Product weight                                                            |
| string        | status                 | Product status                                                            |
| string        | url_key                | URL key                                                                   |
| string        | url_path               | URL path                                                                  |
| string        | visibility             | Product visibility on the frontend                                        |
| ArrayOfString | category_ids           | Array of category IDs                                                     |
| ArrayOfString | website_ids            | Array of website IDs                                                      |
| string        | has_options            | Defines whether the product has options                                   |
| string        | gift_message_available | Defines whether the gift message is available for the product             |
| string        | price                  | Product price                                                             |
| string        | special_price          | Product special price                                                     |
| string        | special_from_date      | Date starting from which the special price will be applied to the product |
| string        | special_to_date        | Date till which the special price will be applied to the product          |
| string        | tax_class_id           | Tax class ID                                                              |
| array         | tier_price             | Array of catalogProductTierPriceEntity                                    |
| string        | meta_title             | Meta title                                                                |
| string        | meta_keyword           | Meta keyword                                                              |
| string        | meta_description       | Meta description                                                          |
| string        | custom_design          | Custom design                                                             |
| string        | custom_layout_update   | Custom layout update                                                      |
| string        | options_container      | Options container                                                         |
| array         | additional_attributes  | Array of catalogProductAdditionalAttributesEntity                         |
| array         | stock_data             | Array of catalogInventoryStockItemUpdateEntity                            |

**Note:** The `websites` and `website_ids` or `categories` and `category_ids` parameters are interchangeable.
You can specify an array of website IDs (int)
and then you don't need to specify the array of website codes (string) and vice versa.

### Content `catalogProductTierPriceEntity`

| Type   | Name              | Description       |
|--------|-------------------|-------------------|
| string | customer_group_id | Customer group ID |
| string | website           | Website           |
| int    | qty               | Quantity          |
| double | price             | Tier price        |

### Content `catalogInventoryStockItemUpdateEntity`

| Type   | Name                        | Description                                                                    |
|--------|-----------------------------|--------------------------------------------------------------------------------|
| string | qty                         | Quantity of items                                                              |
| int    | is_in_stock                 | Defines whether the item is in stock                                           |
| int    | manage_stock                | Manage stock                                                                   |
| int    | use_config_manage_stock     | Use config manage stock                                                        |
| int    | min_qty                     | Minimum quantity for items to be in stock                                      |
| int    | use_config_min_qty          | Use config settings flag (value defined in the Inventory System Configuration) |
| int    | min_sale_qty                | Minimum quantity allowed in the shopping cart                                  |
| int    | use_config_min_sale_qty     | Use config settings flag                                                       |
| int    | max_sale_qty                | Maximum quantity allowed in the shopping cart                                  |
| int    | use_config_max_sale_qty     | Use config settings flag                                                       |
| int    | is_qty_decimal              | Defines whether the quantity is decimal                                        |
| int    | backorders                  | Backorders status                                                              |
| int    | use_config_backorders       | Use config settings flag (for backorders)                                      |
| int    | notify_stock_qty            | Stock quantity below which a notification will appear                          |
| int    | use_config_notify_stock_qty | Use config settings flag (for stock quantity)                                  |

### Content `catalogProductAdditionalAttributesEntity`

| Type                  | Name                                                                |
|-----------------------|---------------------------------------------------------------------|
| associativeMultiArray | multi_data (array of attributes which could contain several values) |
| associativeArray      | single_data (array of attributes with only single value)            |

### Faults

| Fault Code | Fault Message                                                   |
|------------|-----------------------------------------------------------------|
| 100        | Requested store view not found.                                 |
| 102        | Invalid data given. Details in error message.                   |
| 104        | Product type is not in allowed types.                           |
| 105        | Product attribute set is not existed                            |
| 106        | Product attribute set is not belong catalog product entity type |

### Examples

#### Request Example SOAP V1

```php
$client = new SoapClient('https://mahohost/api/soap/?wsdl');
$session = $client->login('apiUser', 'apiKey');

// get attribute set
$attributeSets = $client->call($session, 'product_attribute_set.list');
$attributeSet = current($attributeSets);

$result = $client->call(
    $session,
    'catalog_product.create',
    [
        'simple',
        $attributeSet['set_id'],
        'product_sku',
        [
            'categories' => [2],
            'websites' => [1],
            'name' => 'Product name',
            'description' => 'Product description',
            'short_description' => 'Product short description',
            'weight' => '10',
            'status' => '1',
            'url_key' => 'product-url-key',
            'url_path' => 'product-url-path',
            'visibility' => '4',
            'price' => '100',
            'tax_class_id' => 1,
            'meta_title' => 'Product meta title',
            'meta_keyword' => 'Product meta keyword',
            'meta_description' => 'Product meta description'
        ]
    ]
);
var_dump($result);
```

#### Request Example SOAP V2

```php
$client = new SoapClient('https://mahohost/api/v2_soap/?wsdl');
$session = $client->login('apiUser', 'apiKey');

// get attribute set
$attributeSets = $client->catalogProductAttributeSetList($session);
$attributeSet = current($attributeSets);

$result = $client->catalogProductCreate(
    $session,
    'simple',
    $attributeSet['set_id'],
    'product_sku',
    [
        'categories' => [2],
        'websites' => [1],
        'name' => 'Product name',
        'description' => 'Product description',
        'short_description' => 'Product short description',
        'weight' => '10',
        'status' => '1',
        'url_key' => 'product-url-key',
        'url_path' => 'product-url-path',
        'visibility' => '4',
        'price' => '100',
        'tax_class_id' => 1,
        'meta_title' => 'Product meta title',
        'meta_keyword' => 'Product meta keyword',
        'meta_description' => 'Product meta description'
    ]
);
var_dump($result);
```

#### Request Example SOAP V2 (WS-I Compliance Mode)

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl');
$sessionId = $proxy->login((object)['username' => 'apiUser', 'apiKey' => 'apiKey']);

$result = $proxy->catalogProductCreate((object)['sessionId' => $sessionId->result, 'type' => 'simple', 'set' => '4', 'sku' => 'simple_sku',
'productData' => (object)[
    'name' => 'Product name',
    'description' => 'Product description',
    'short_description' => 'Product short description',
    'weight' => '10',
    'status' => '1',
    'visibility' => '4',
    'price' => '100',
    'tax_class_id' => 1,
]]);
var_dump($result->result);
```

## Update

### Method

- `catalog_product.update` (SOAP V1)
- `catalogProductUpdate` (SOAP V2)

Allows you to update the required product. Note that you should specify only those parameters which you want to be updated.

### Alias

- `product.update`

**Note:** Although the API accepts up to four digits of precision for all price arguments,
Maho strongly recommends you pass in two digits to reduce inaccuracy in the tax calculation process.
(That is, use a price like `12.35` and not `12.3487`).

### Arguments

| Type   | Name           | Description                                                                |
|--------|----------------|----------------------------------------------------------------------------|
| string | sessionId      | Session ID                                                                 |
| string | productId      | Product ID                                                                 |
| array  | productData    | Array of catalogProductCreateEntity                                        |
| string | storeView      | Store view ID or code (optional)                                           |
| string | identifierType | Defines whether the product ID or SKU is passed in the 'product' parameter |

### Returns

| Type    | Description                    |
|---------|--------------------------------|
| boolean | True if the product is updated |

### Content `catalogProductCreateEntity`

| Type             | Name                   | Description                                                               |
|------------------|------------------------|---------------------------------------------------------------------------|
| ArrayOfString    | categories             | Array of categories                                                       |
| ArrayOfString    | websites               | Array of websites                                                         |
| string           | name                   | Product name                                                              |
| string           | description            | Product description                                                       |
| string           | short_description      | Product short description                                                 |
| string           | weight                 | Product weight                                                            |
| string           | status                 | Product status                                                            |
| string           | url_key                | A relative URL path that can be entered in place of the target path       |
| string           | url_path               | URL path                                                                  |
| string           | visibility             | Product visibility on the frontend                                        |
| ArrayOfString    | category_ids           | Array of category IDs                                                     |
| ArrayOfString    | website_ids            | Array of website IDs                                                      |
| string           | has_options            | Defines whether the product has options                                   |
| string           | gift_message_available | Defines whether the gift message is available for the product             |
| string           | price                  | Product price                                                             |
| string           | special_price          | Product special price                                                     |
| string           | special_from_date      | Date starting from which the special price will be applied to the product |
| string           | special_to_date        | Date till which the special price will be applied to the product          |
| string           | tax_class_id           | Tax class ID                                                              |
| array            | tier_price             | Array of catalogProductTierPriceEntity                                    |
| string           | meta_title             | Meta title                                                                |
| string           | meta_keyword           | Meta keyword                                                              |
| string           | meta_description       | Meta description                                                          |
| string           | custom_design          | Custom design                                                             |
| string           | custom_layout_update   | Custom layout update                                                      |
| string           | options_container      | Options container                                                         |
| associativeArray | additional_attributes  | Array of catalogProductAdditionalAttributesEntity                         |
| array            | stock_data             | Array of catalogInventoryStockItemUpdateEntity                            |

**Note:** The `websites` and `website_ids` or `categories` and `category_ids` parameters are interchangeable.
You can specify an array of website IDs (int)
and then you don't need to specify the array of website codes (string) and vice versa.

### Content `catalogProductTierPriceEntity`

| Type   | Name              | Description                                          |
|--------|-------------------|------------------------------------------------------|
| string | customer_group_id | Customer group ID                                    |
| string | website           | Website                                              |
| int    | qty               | Quantity of items to which the price will be applied |
| double | price             | Price that each item will cost                       |

### Content `catalogInventoryStockItemUpdateEntity`

| Type   | Name                        | Description                                                                    |
|--------|-----------------------------|--------------------------------------------------------------------------------|
| string | qty                         | Quantity of items                                                              |
| int    | is_in_stock                 | Defines whether the item is in stock                                           |
| int    | manage_stock                | Manage stock                                                                   |
| int    | use_config_manage_stock     | Use config manage stock                                                        |
| int    | min_qty                     | Minimum quantity for items to be in stock                                      |
| int    | use_config_min_qty          | Use config settings flag (value defined in the Inventory System Configuration) |
| int    | min_sale_qty                | Minimum quantity allowed in the shopping cart                                  |
| int    | use_config_min_sale_qty     | Use config settings flag                                                       |
| int    | max_sale_qty                | Maximum quantity allowed in the shopping cart                                  |
| int    | use_config_max_sale_qty     | Use config settings flag                                                       |
| int    | is_qty_decimal              | Defines whether the quantity is decimal                                        |
| int    | backorders                  | Backorders status                                                              |
| int    | use_config_backorders       | Use config settings flag (for backorders)                                      |
| int    | notify_stock_qty            | Stock quantity below which a notification will appear                          |
| int    | use_config_notify_stock_qty | Use config settings flag (for stock quantity)                                  |

### Content `catalogProductAdditionalAttributesEntity`

| Type                  | Name                                                                |
|-----------------------|---------------------------------------------------------------------|
| associativeMultiArray | multi_data (array of attributes which could contain several values) |
| associativeArray      | single_data (array of attributes with only single value)            |

### Faults

| Fault Code | Fault Message                                 |
|------------|-----------------------------------------------|
| 100        | Requested store view not found.               |
| 101        | Product not exists.                           |
| 102        | Invalid data given. Details in error message. |

### Examples

#### Request Example SOAP V1

```php
$client = new SoapClient('https://mahohost/api/soap/?wsdl');
$session = $client->login('apiUser', 'apiKey');

$result = $client->call(
    $session,
    'catalog_product.update',
    [
        'product_sku',
        [
            'categories' => [2],
            'websites' => [1],
            'name' => 'Product name new 2',
            'description' => 'Product description',
            'short_description' => 'Product short description',
            'weight' => '10',
            'status' => '1',
            'url_key' => 'product-url-key',
            'url_path' => 'product-url-path',
            'visibility' => '4',
            'price' => '100',
            'tax_class_id' => 1,
            'meta_title' => 'Product meta title',
            'meta_keyword' => 'Product meta keyword',
            'meta_description' => 'Product meta description'
        ]
    ]
);
var_dump($result);
```

#### Request Example SOAP V2

```php
$client = new SoapClient('https://mahohost/api/v2_soap/?wsdl');
$session = $client->login('apiUser', 'apiKey');

$result = $client->catalogProductUpdate(
    $session,
    'product_sku',
    [
        'categories' => [2],
        'websites' => [1],
        'name' => 'Product name new',
        'description' => 'Product description',
        'short_description' => 'Product short description',
        'weight' => '10',
        'status' => '1',
        'url_key' => 'product-url-key',
        'url_path' => 'product-url-path',
        'visibility' => '4',
        'price' => '100',
        'tax_class_id' => 1,
        'meta_title' => 'Product meta title',
        'meta_keyword' => 'Product meta keyword',
        'meta_description' => 'Product meta description'
    ]
);
var_dump($result);
```

#### Request Example SOAP V2 (WS-I Compliance Mode)

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl');
$sessionId = $proxy->login((object)['username' => 'apiUser', 'apiKey' => 'apiKey']);

$result = $proxy->catalogProductUpdate((object)['sessionId' => $sessionId->result, 'productId' => '1',
'productData' => (object)[
    'name' => 'Product name updated',
    'status' => '1',
]]);
var_dump($result->result);
```

## SetSpecialPrice

### Method

- `catalog_product.setSpecialPrice` (SOAP V1)
- `catalogProductSetSpecialPrice` (SOAP V2)

Allows you to set the product's special price.

### Aliases

- `product.setSpecialPrice`

**Note:** Although the API accepts up to four digits of precision for all price arguments,
Maho strongly recommends you pass in two digits to reduce inaccuracy in the tax calculation process.
(That is, use a price like `12.35` and not `12.3487`).

### Arguments

| Type   | Name                  | Description                                                                             |
|--------|-----------------------|-----------------------------------------------------------------------------------------|
| string | sessionId             | Session ID                                                                              |
| string | productId             | Product ID or SKU                                                                       |
| string | specialPrice          | Product special price                                                                   |
| string | fromDate              | Date starting from which special price will be applied                                  |
| string | toDate                | Date till which special price will be applied                                           |
| string | storeView             | Store view ID or code (optional)                                                        |
| string | productIdentifierType | Defines whether the product ID or SKU is passed in the 'productId' parameter (optional) |

### Returns

| Type        | Name   | Description                                          |
|-------------|--------|------------------------------------------------------|
| boolean/int | result | True (1) if the special price is set for the product |

### Examples

#### Request Example SOAP V1

```php
$client = new SoapClient('https://mahohost/api/soap/?wsdl');
$session = $client->login('apiUser', 'apiKey');

$result = $client->call($session, 'catalog_product.setSpecialPrice', ['product' => 'testproduct', 'specialPrice' => '77.5', 'fromDate' => '2012-03-29 12:30:51', 'toDate' => '2012-04-29 12:30:51']);
var_dump($result);

// When the session can be closed
$client->endSession($session);
```

#### Request Example SOAP V2

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl'); // replace with your store's WSDL URL
$sessionId = $proxy->login('apiUser', 'apiKey'); // replace with your API user and key

$result = $proxy->catalogProductSetSpecialPrice(
    $sessionId,
    '2',
    '77.5',
    '2012-03-29 12:30:51',
    '2012-04-29 12:30:51'
);
var_dump($result);
```

#### Request Example SOAP V2 (WS-I Compliance Mode)

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl');
$sessionId = $proxy->login((object)['username' => 'apiUser', 'apiKey' => 'apiKey']);

$result = $proxy->catalogProductSetSpecialPrice(
    (object)[
        'sessionId' => $sessionId->result,
        'productId' => '2',
        'specialPrice' => '77.5',
        'fromDate' => '2012-03-29 12:30:51',
        'toDate' => '2012-04-29 12:30:51'
    ]
);
var_dump($result->result);
```

## GetSpecialPrice

### Method

- `catalog_product.getSpecialPrice` (SOAP V1)
- `catalogProductGetSpecialPrice` (SOAP V2)

Allows you to get the product special price data.

### Alias

- `product.getSpecialPrice`

### Arguments

| Type   | Name           | Description                                                                |
|--------|----------------|----------------------------------------------------------------------------|
| string | sessionId      | Session ID                                                                 |
| string | productId      | Product ID or SKU                                                          |
| string | storeView      | Store view ID or code                                                      |
| string | identifierType | Defines whether the product ID or SKU is passed in the 'product' parameter |

### Returns

| Type  | Name   | Description                         |
|-------|--------|-------------------------------------|
| array | result | Array of catalogProductReturnEntity |

### Content `catalogProductReturnEntity`

| Type   | Name              | Description                                                          |
|--------|-------------------|----------------------------------------------------------------------|
| string | special_price     | Product special price                                                |
| string | special_from_date | Date starting from which the special price is applied to the product |
| string | special_to_date   | Date till which the special price is applied to the product          |

### Examples

#### Request Example SOAP V1

```php
$client = new SoapClient('https://mahohost/api/soap/?wsdl');
$session = $client->login('apiUser', 'apiKey');

$result = $client->call($session, 'catalog_product.getSpecialPrice', '1');
var_dump($result);

// When the session can be closed
$client->endSession($session);
```

#### Request Example SOAP V2

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl'); // replace with your store's WSDL URL
$sessionId = $proxy->login('apiUser', 'apiKey'); // replace with your API user and key

$result = $proxy->catalogProductGetSpecialPrice($sessionId, '1');
var_dump($result);
```

#### Request Example SOAP V2 (WS-I Compliance Mode)

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl');
$sessionId = $proxy->login((object)['username' => 'apiUser', 'apiKey' => 'apiKey']);

$result = $proxy->catalogProductGetSpecialPrice(
    (object)[
        'sessionId' => $sessionId->result,
        'productId' => '1'
    ]
);
var_dump($result->result);
```

#### Response Example SOAP V1

```php
array
  'special_price' => string '139.9900' (length=8)
  'special_from_date' => string '2012-03-28 00:00:00' (length=19)
  'special_to_date' => string '2012-03-31 00:00:00' (length=19)
```

## Delete

### Method

- `catalog_product.delete` (SOAP V1)
- `catalogProductDelete` (SOAP V2)

Allows you to delete the required product.

### Aliases

- `product.delete`

### Arguments

| Type   | Name           | Description                                                                |
|--------|----------------|----------------------------------------------------------------------------|
| string | sessionId      | Session ID                                                                 |
| string | productId      | Product ID or SKU                                                          |
| string | identifierType | Defines whether the product ID or SKU is passed in the 'product' parameter |

### Returns

| Type        | Name   | Description                        |
|-------------|--------|------------------------------------|
| boolean/int | result | True (1) if the product is deleted |

### Examples

#### Request Example SOAP V1

```php
$client = new SoapClient('https://mahohost/api/soap/?wsdl');
$session = $client->login('apiUser', 'apiKey');

$result = $client->call($session, 'catalog_product.delete', '6');
var_dump($result);

// When the session can be closed
$client->endSession($session);
```

#### Request Example SOAP V2

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl'); // replace with your store's WSDL URL
$sessionId = $proxy->login('apiUser', 'apiKey'); // replace with your API user and key

$result = $proxy->catalogProductDelete($sessionId, '6');
var_dump($result);
```

#### Request Example SOAP V2 (WS-I Compliance Mode)

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl');
$sessionId = $proxy->login((object)['username' => 'apiUser', 'apiKey' => 'apiKey']);

$result = $proxy->catalogProductDelete(
    (object)[
        'sessionId' => $sessionId->result,
        'productId' => '21'
    ]
);
var_dump($result->result);
```

## ListOfAdditionalAttributes

### Method

- `product.listOfAdditionalAttributes` (SOAP V1)
- `catalogProductListOfAdditionalAttributes` (SOAP V2)

Get the list of additional attributes.
Additional attributes are attributes that are not in the default set of attributes.

### Arguments

| Type   | Name           | Description                  |
|--------|----------------|------------------------------|
| string | sessionId      | Session ID                   |
| string | productType    | Product type (e.g. `simple`) |
| string | attributeSetId | Attribute set ID             |

### Returns

| Type   | Name         | Description                                          |
|--------|--------------|------------------------------------------------------|
| int    | attribute_id | Attribute ID                                         |
| string | code         | Attribute code                                       |
| string | type         | Attribute type (e.g. `text`, `select`, `date`, etc.) |
| string | required     | Defines whether the attribute is required            |
| string | scope        | Attribute scope (global, website, or store)          |

### Faults

| Fault Code | Fault Message                                                   |
|------------|-----------------------------------------------------------------|
| 104        | Product type is not in allowed types.                           |
| 105        | Product attribute set is not existed                            |
| 106        | Product attribute set is not belong catalog product entity type |

### Examples

#### Request Example SOAP V1

```php
$proxy = new SoapClient('https://mahohost/api/soap/?wsdl');
$sessionId = $proxy->login('apiUser', 'apiKey');

$listAttributes = $proxy->call(
    $sessionId,
    'product.listOfAdditionalAttributes',
    [
        'simple',
        13
    ]
);
```

#### Request Example SOAP V2

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl'); // replace with your store's WSDL URL
$sessionId = $proxy->login('apiUser', 'apiKey'); // replace with your API user and key

$result = $proxy->catalogProductListOfAdditionalAttributes($sessionId, 'simple', '13');
var_dump($result);
```

#### Response Example SOAP V1

```php
array
  0 =>
    array
      'attribute_id' => string '89' (length=2)
      'code' => string 'old_id' (length=6)
      'type' => string 'text' (length=4)
      'required' => string '0' (length=1)
      'scope' => string 'global' (length=6)
  1 =>
    array
      'attribute_id' => string '93' (length=2)
      'code' => string 'news_from_date' (length=14)
      'type' => string 'date' (length=4)
      'required' => string '0' (length=1)
      'scope' => string 'website' (length=7)
  2 =>
    array
      ...
```
