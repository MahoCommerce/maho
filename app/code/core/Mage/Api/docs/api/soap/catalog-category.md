---
description: Manage categories over the Maho SOAP API - retrieve the category tree, create, update, move, and delete categories, and assign products to them.
---

# Catalog Category

!!! warning "Legacy API"
    This is Maho's legacy SOAP/XML-RPC API, inherited from Magento 1 and kept for backward compatibility. New integrations should use the modern [REST & GraphQL API (v2)](../v2/index.md) instead.

## Introduction

Allows you to manage categories and how products are assigned to categories.

### Methods

- `catalog_category.currentStore` - Set/Get the current store view.
- `catalog_category.tree` - Retrieve the hierarchical category tree.
- `catalog_category.level` - Retrieve one level of categories by a website, store view, or parent category.
- `catalog_category.info` - Retrieve the category data.
- `catalog_category.create` - Create a new category.
- `catalog_category.update` - Update a category.
- `catalog_category.move` - Move a category in its tree.
- `catalog_category.delete` - Delete a category.
- `catalog_category.assignedProducts` - Retrieve a list of products assigned to a category.
- `catalog_category.assignProduct` - Assign product to a category.
- `catalog_category.updateProduct` - Update an assigned product.
- `catalog_category.removeProduct` - Remove a product assignment.

### Faults

| Fault Code | Fault Message                                   |
|------------|-------------------------------------------------|
| 100        | Requested store view not found.                 |
| 101        | Requested website not found.                    |
| 102        | Category not exists.                            |
| 103        | Invalid data given. Details in error message.   |
| 104        | Category not moved. Details in error message.   |
| 105        | Category not deleted. Details in error message. |
| 106        | Requested product is not assigned to category.  |

### Examples

#### Working With Categories

```php
function getSomeRandomCategory(&$categories, $targetLevel, $currentLevel = 0) {
    if (count($categories) === 0) {
        return false;
    }
    if ($targetLevel == $currentLevel) {
        return $categories[array_rand($categories)];
    } else {
        return getSomeRandomCategory(
            $categories[array_rand($categories)]['children'],
            $targetLevel + 1
        );
    }
}

$proxy = new SoapClient('https://mahohost/api/soap/?wsdl');
$sessionId = $proxy->login('apiUser', 'apiKey');
$allCategories = $proxy->call($sessionId, 'category.tree'); // Get all categories.

// Select random category from tree
while (($selectedCategory = getSomeRandomCategory($allCategories, 3)) === false) {}

// Create new category
$newCategoryId = $proxy->call(
    $sessionId,
    'category.create',
    [
        $selectedCategory['category_id'],
        [
            'name' => 'Newopenerp',
            'is_active' => 1,
            'include_in_menu' => 2,
            'available_sort_by' => 'position',
            'default_sort_by' => 'position'
        ]
    ]
);

$newData = ['is_active' => 1];
// update created category on German store view
$proxy->call(
    $sessionId,
    'category.update',
    [
        $newCategoryId,
        $newData,
        'german'
    ]
);

$firstLevel = $proxy->call(
    $sessionId,
    'category.level',
    [
        null,
        'german',
        $selectedCategory['category_id']
    ]
);

var_dump($firstLevel);

// If you wish remove category, uncomment next line
// $proxy->call($sessionId, 'category.delete', $newCategoryId);
```

#### Working With Assigned Products

```php
$proxy = new SoapClient('https://mahohost/api/soap/?wsdl');
$sessionId = $proxy->login('apiUser', 'apiKey');

$categoryId = 5; // Put here your category id
$storeId = 1; // You can add store level

$assignedProducts = $proxy->call($sessionId, 'category.assignedProducts', [$categoryId, $storeId]);
var_dump($assignedProducts); // Will output assigned products.

// Assign product
$proxy->call($sessionId, 'category.assignProduct', [$categoryId, 'someProductSku', 5]);

// Update product assignment position
$proxy->call($sessionId, 'category.updateProduct', [$categoryId, 'someProductSku', 25]);

// Remove product assignment
$proxy->call($sessionId, 'category.removeProduct', [$categoryId, 'someProductSku']);
```

## Assigned Products

### Method

- `catalog_category.assignedProducts` (SOAP V1)
- `catalogCategoryAssignedProducts` (SOAP V2)

Retrieve the list of products assigned to a required category.

### Alias

- `category.assignedProducts`

### Arguments

| Type   | Name       | Description                 |
|--------|------------|-----------------------------|
| string | sessionId  | Session ID                  |
| int    | categoryId | ID of the required category |

### Returns

| Type  | Name   | Description                     |
|-------|--------|---------------------------------|
| array | result | Array of catalogAssignedProduct |

### Content `catalogAssignedProduct`

| Type   | Name       | Description                      |
|--------|------------|----------------------------------|
| int    | product_id | ID of the assigned product       |
| string | type       | Product type                     |
| int    | set        | Attribute set ID                 |
| string | sku        | Product SKU                      |
| int    | position   | Position of the assigned product |

### Examples
 
#### Request Example SOAP V1

```php
$client = new SoapClient('https://mahohost/api/soap/?wsdl');
$session = $client->login('apiUser', 'apiKey');

$result = $client->call($session, 'catalog_category.assignedProducts', '4');
var_dump($result);

// When the session can be closed
$client->endSession($session);
```

#### Request Example SOAP V2

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl'); // replace with your store's WSDL URL
$sessionId = $proxy->login('apiUser', 'apiKey'); // replace with your API user and key

$result = $proxy->catalogCategoryAssignedProducts($sessionId, '4');
var_dump($result);
```

#### Request Example SOAP V2 (WS-I Compliance Mode)

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl');
$sessionId = $proxy->login((object)['username' => 'apiUser', 'apiKey' => 'apiKey']);

$result = $proxy->catalogCategoryAssignedProducts(
    (object)[
        'sessionId' => $sessionId->result,
        'categoryId' => '4'
    ]
);
var_dump($result->result);
```

#### Response Example SOAP V1

```php
array
  0 =>
    array
      'product_id' => string '1' (length=1)
      'type' => string 'simple' (length=6)
      'set' => string '4' (length=1)
      'sku' => string 'n2610' (length=5)
      'position' => string '1' (length=1)
  1 =>
    array
      'product_id' => string '2' (length=1)
      'type' => string 'simple' (length=6)
      'set' => string '4' (length=1)
      'sku' => string 'b8100' (length=5)
      'position' => string '1' (length=1)
```

## Assign Product

### Method

- `catalog_category.assignProduct` (SOAP V1)
- `catalogCategoryAssignProduct` (SOAP V2)

Assign a product to the required category.

### Alias

- `category.assignProduct`

### Arguments

| Type   | Name              | Description                                                               |
|--------|-------------------|---------------------------------------------------------------------------|
| string | sessionId         | Session ID                                                                |
| int    | categoryId        | ID of the category                                                        |
| string | product/productId | ID or SKU of the product to be assigned to the category                   |
| string | position          | Position of the assigned product in the category (optional)               |
| string | identifierType    | Defines whether the product ID or SKU is passed in the 'product' argument |

### Returns

| Type    | Description                                               |
|---------|-----------------------------------------------------------|
| boolean | True if the product is assigned to the specified category |

#### Examples

#### Request Example SOAP V1

```php
$client = new SoapClient('https://mahohost/api/soap/?wsdl');
$session = $client->login('apiUser', 'apiKey');

$result = $client->call($session, 'catalog_category.assignProduct', ['categoryId' => '4', 'product' => '1']);
var_dump($result);

// When the session can be closed
$client->endSession($session);
```

#### Request Example SOAP V2

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl'); // replace with your store's WSDL URL
$sessionId = $proxy->login('apiUser', 'apiKey'); // replace with your API user and key

$result = $proxy->catalogCategoryAssignProduct($sessionId, '4', '3');
var_dump($result);
```

#### Request Example SOAP V2 (WS-I Compliance Mode)

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl');
$sessionId = $proxy->login((object)['username' => 'apiUser', 'apiKey' => 'apiKey']);

$result = $proxy->catalogCategoryAssignProduct(
    (object)[
        'sessionId' => $sessionId->result,
        'categoryId' => '5',
        'productId' => '1',
        'position' => '5'
    ]
);
var_dump($result->result);
```

## Create Category

### Method

- `catalog_category.create` (SOAP V1)
- `catalogCategoryCreate` (SOAP V2)

Create a new category and return its ID.

### Alias

- `category.create`

### Arguments

| Type   | Name         | Description                          |
|--------|--------------|--------------------------------------|
| string | sessionId    | Session ID                           |
| int    | parentId     | Parent category ID                   |
| array  | categoryData | Array of catalogCategoryEntityCreate |
| string | storeView    | Store view ID or code (optional)     |

### Returns

| Type | Name         | Description                |
|------|--------------|----------------------------|
| int  | attribute_id | ID of the created category |

### Content `categoryData`

| Type          | Name                       | Description                                                                                                                          |
|---------------|----------------------------|--------------------------------------------------------------------------------------------------------------------------------------|
| string        | name                       | Name of the created category                                                                                                         |
| int           | is_active                  | Defines whether the category will be visible in the frontend                                                                         |
| int           | position                   | Position of the created category (optional)                                                                                          |
| ArrayOfString | available_sort_by          | All available options by which products in the category can be sorted                                                                |
| string        | custom_design              | The custom design for the category (optional)                                                                                        |
| int           | custom_apply_to_products   | Apply the custom design to all products assigned to the category (optional)                                                          |
| string        | custom_design_from         | Date starting from which the custom design will be applied to the category (optional)                                                |
| string        | custom_design_to           | Date till which the custom design will be applied to the category (optional)                                                         |
| string        | custom_layout_update       | Custom layout update (optional)                                                                                                      |
| string        | default_sort_by            | The default option by which products in the category are sorted                                                                      |
| string        | description                | Category description (optional)                                                                                                      |
| string        | display_mode               | Content that will be displayed on the category view page (optional)                                                                  |
| int           | is_anchor                  | Defines whether the category will be anchored (optional)                                                                             |
| int           | landing_page               | Landing page (optional)                                                                                                              |
| string        | meta_description           | Category meta description (optional)                                                                                                 |
| string        | meta_keywords              | Category meta keywords (optional)                                                                                                    |
| string        | meta_title                 | Category meta title (optional)                                                                                                       |
| string        | page_layout                | Type of page layout that the category should use (optional)                                                                          |
| string        | url_key                    | A relative URL path which can be entered in place of the standard target path (optional)                                             |
| int           | include_in_menu            | Defines whether the category is visible on the top menu bar                                                                          |
| string        | filter_price_range         | Price range of each price level displayed in the layered navigation block (optional)                                                 |
| int           | custom_use_parent_settings | Defines whether the category will inherit custom design settings of the category to which it is assigned. 1 - Yes, 0 - No (optional) |

**Notes:** The `position` parameter is deprecated.
The category will be positioned anyway at the end of the list, and you can't set the position directly.
You should use the `catalog_category.move` method instead.
You cannot also assign a root category to the specified store.

### Examples

#### Request Example SOAP V1

```php
$client = new SoapClient('https://mahohost/api/soap/?wsdl');
$session = $client->login('apiUser', 'apiKey');

$result = $client->call($session, 'catalog_category.create', [2, [
    'name' => 'Category name',
    'is_active' => 1,
    'position' => 1,
    // The position parameter is deprecated, so category anyway will be positioned in the end of list
    // and you can't set position directly. Use catalog_category.move instead.
    'available_sort_by' => 'position',
    'custom_design' => null,
    'custom_apply_to_products' => null,
    'custom_design_from' => null,
    'custom_design_to' => null,
    'custom_layout_update' => null,
    'default_sort_by' => 'position',
    'description' => 'Category description',
    'display_mode' => null,
    'is_anchor' => 0,
    'landing_page' => null,
    'meta_description' => 'Category meta description',
    'meta_keywords' => 'Category meta keywords',
    'meta_title' => 'Category meta title',
    'page_layout' => 'two_columns_left',
    'url_key' => 'url-key',
    'include_in_menu' => 1,
]]);

var_dump($result);
```

#### Request Example SOAP V2

```php
$client = new SoapClient('https://mahohost/api/v2_soap/?wsdl');
$session = $client->login('apiUser', 'apiKey');

$result = $client->catalogCategoryCreate($session, 2, [
    'name' => 'Category name 2',
    'is_active' => 1,
    'position' => 1,
    // The position parameter is deprecated, so category anyway will be positioned in the end of list
    // and you can't set position directly. Use catalog_category.move instead.
    'available_sort_by' => ['position'],
    'custom_design' => null,
    'custom_apply_to_products' => null,
    'custom_design_from' => null,
    'custom_design_to' => null,
    'custom_layout_update' => null,
    'default_sort_by' => 'position',
    'description' => 'Category description',
    'display_mode' => null,
    'is_anchor' => 0,
    'landing_page' => null,
    'meta_description' => 'Category meta description',
    'meta_keywords' => 'Category meta keywords',
    'meta_title' => 'Category meta title',
    'page_layout' => 'two_columns_left',
    'url_key' => 'url-key',
    'include_in_menu' => 1,
]);

var_dump($result);
```

#### Request Example SOAP V2 (WS-I Compliance Mode)

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl');
$sessionId = $proxy->login((object)['username' => 'apiUser', 'apiKey' => 'apiKey']);

$result = $proxy->catalogCategoryCreate(
    (object)[
        'sessionId' => $sessionId->result,
        'parentId' => '5',
        'categoryData' => (object)[
            'name' => 'category',
            'is_active' => '1',
            'position' => '1',
            'available_sort_by' => ['position'],
            'default_sort_by' => 'position',
            'description' => 'Category description',
            'is_anchor' => '1',
            'include_in_menu' => '1'
        ]
    ]
);
var_dump($result->result);
```

## Current Store

### Method

- `catalog_category.currentStore` (SOAP V1)
- `catalogCategoryCurrentStore` (SOAP V2)

Allows you to set/get the current store view.

### Alias

- `category.currentStore`

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

$result = $client->call($session, 'category.currentStore', '1');
var_dump($result);
```

#### Request Example SOAP V2

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl'); // replace with your store's WSDL URL
$sessionId = $proxy->login('apiUser', 'apiKey'); // replace with your API user and key

$result = $proxy->catalogCategoryCurrentStore($sessionId, '1');
var_dump($result);
```

## Category Delete

### Method

- `catalog_category.delete` (SOAP V1)
- `catalogCategoryDelete` (SOAP V2)

Allows you to delete the required category.

### Alias

- `category.delete`

### Arguments

| Type   | Name       | Description                      |
|--------|------------|----------------------------------|
| string | sessionId  | Session ID                       |
| int    | categoryId | ID of the category to be deleted |

### Returns

| Type    | Description                     |
|---------|---------------------------------|
| boolean | True if the category is deleted |

### Examples

#### Request Example SOAP V1

```php
$client = new SoapClient('https://mahohost/api/soap/?wsdl');
$session = $client->login('apiUser', 'apiKey');

$result = $client->call($session, 'catalog_category.delete', '7');
var_dump($result);

// When the session can be closed
$client->endSession($session);
```

#### Request Example SOAP V2

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl'); // replace with your store's WSDL URL
$sessionId = $proxy->login('apiUser', 'apiKey'); // replace with your API user and key

$result = $proxy->catalogCategoryDelete($sessionId, '7');
var_dump($result);
```

#### Request Example SOAP V2 (WS-I Compliance Mode)

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl');
$sessionId = $proxy->login((object)['username' => 'apiUser', 'apiKey' => 'apiKey']);

$result = $proxy->catalogCategoryDelete((object)['sessionId' => $sessionId->result, 'categoryId' => '7']);
var_dump($result->result);
```

## Category Info

### Method

- `catalog_category.info` (SOAP V1)
- `catalogCategoryInfo` (SOAP V2)

Allows you to retrieve information about the required category.

### Aliases

- `category.info`

### Arguments

| Type          | Name       | Description                      |
|---------------|------------|----------------------------------|
| string        | sessionId  | Session ID                       |
| int           | categoryId | Category ID                      |
| string        | storeView  | Store view ID or code (optional) |
| ArrayOfString | attributes | Array of attributes (optional)   |

### Returns

| Type  | Name | Description                  |
|-------|------|------------------------------|
| array | info | Array of catalogCategoryInfo |

### Content `catalogCategoryInfo`

| Type          | Name                       | Description                                                                                                               |
|---------------|----------------------------|---------------------------------------------------------------------------------------------------------------------------|
| string        | category_id                | Category ID                                                                                                               |
| int           | is_active                  | Defines whether the category is active                                                                                    |
| string        | position                   | Category position                                                                                                         |
| string        | level                      | Category level                                                                                                            |
| string        | parent_id                  | Parent category ID                                                                                                        |
| string        | all_children               | All child categories of the current category                                                                              |
| string        | children                   | Names of direct child categories                                                                                          |
| string        | created_at                 | Date when the category was created                                                                                        |
| string        | updated_at                 | Date when the category was updated                                                                                        |
| string        | name                       | Category name                                                                                                             |
| string        | url_key                    | A relative URL path which can be entered in place of the standard target path (optional)                                  |
| string        | description                | Category description                                                                                                      |
| string        | meta_title                 | Category meta title                                                                                                       |
| string        | meta_keywords              | Category meta keywords                                                                                                    |
| string        | meta_description           | Category meta description                                                                                                 |
| string        | path                       | Path                                                                                                                      |
| string        | url_path                   | URL path                                                                                                                  |
| int           | children_count             | Number of child categories                                                                                                |
| string        | display_mode               | Content that will be displayed on the category view page (optional)                                                       |
| int           | is_anchor                  | Defines whether the category is anchored                                                                                  |
| ArrayOfString | available_sort_by          | All available options by which products in the category can be sorted                                                     |
| string        | custom_design              | The custom design for the category (optional)                                                                             |
| string        | custom_apply_to_products   | Apply the custom design to all products assigned to the category (optional)                                               |
| string        | custom_design_from         | Date starting from which the custom design will be applied to the category (optional)                                     |
| string        | custom_design_to           | Date till which the custom design will be applied to the category (optional)                                              |
| string        | page_layout                | Type of page layout that the category should use (optional)                                                               |
| string        | custom_layout_update       | Custom layout update (optional)                                                                                           |
| string        | default_sort_by            | The default option by which products in the category are sorted                                                           |
| int           | landing_page               | Landing page (optional)                                                                                                   |
| int           | include_in_menu            | Defines whether the category is available on the Maho top menu bar                                                        |
| string        | filter_price_range         | Price range of each price level displayed in the layered navigation block                                                 |
| int           | custom_use_parent_settings | Defines whether the category will inherit custom design settings of the category to which it is assigned. 1 - Yes, 0 - No |

### Examples

#### Request Example SOAP V1

```php
$client = new SoapClient('https://mahohost/api/soap/?wsdl');
$session = $client->login('apiUser', 'apiKey');

$result = $client->call($session, 'catalog_category.info', '5');
var_dump($result);

// When the session can be closed
$client->endSession($session);
```

#### Request Example SOAP V2

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl'); // replace with your store's WSDL URL
$sessionId = $proxy->login('apiUser', 'apiKey'); // replace with your API user and key

$result = $proxy->catalogCategoryInfo($sessionId, '5');
var_dump($result);
```

#### Request Example SOAP V2 (WS-I Compliance Mode)

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl');
$sessionId = $proxy->login((object)['username' => 'apiUser', 'apiKey' => 'apiKey']);

$result = $proxy->catalogCategoryInfo((object)['sessionId' => $sessionId->result, 'categoryId' => '5']);
var_dump($result->result);
```

#### Response Example SOAP V1

```php
array
  'category_id' => string '5' (length=1)
  'is_active' => string '1' (length=1)
  'position' => string '1' (length=1)
  'level' => string '2' (length=1)
  'parent_id' => int 3
  'increment_id' => null
  'created_at' => string '2012-03-29 12:30:51' (length=19)
  'updated_at' => string '2012-03-29 14:25:08' (length=19)
  'name' => string 'Mobile Phones' (length=13)
  'url_key' => string 'mobile-phones' (length=13)
  'thumbnail' => null
  'description' => string 'Category for cell phones' (length=24)
  'image' => null
  'meta_title' => string 'Cell Phones' (length=11)
  'meta_keywords' => string 'cell, phone' (length=11)
  'meta_description' => null
  'include_in_menu' => string '1' (length=1)
  'path' => string '1/3/4' (length=5)
  'all_children' => string '4' (length=1)
  'path_in_store' => null
  'children' => string '' (length=0)
  'url_path' => string 'mobile-phones.html' (length=18)
  'children_count' => string '0' (length=1)
  'display_mode' => string 'PRODUCTS' (length=8)
  'landing_page' => null
  'is_anchor' => string '1' (length=1)
  'available_sort_by' => null
  'default_sort_by' => null
  'filter_price_range' => null
  'custom_use_parent_settings' => string '1' (length=1)
  'custom_apply_to_products' => null
  'custom_design' => null
  'custom_design_from' => null
  'custom_design_to' => null
  'page_layout' => null
  'custom_layout_update' => null
```

## Category Level

### Method

- `catalog_category.level` (SOAP V1)
- `catalogCategoryLevel` (SOAP V2)

Allows you to retrieve one level of categories by a website, a store view or a parent category.

### Alias

- `category.level`

### Arguments

| Type   | Name           | Description                      |
|--------|----------------|----------------------------------|
| string | sessionId      | Session ID                       |
| string | website        | Website ID or code (optional)    |
| string | storeView      | Store view ID or code (optional) |
| string | parentCategory | Parent category ID (optional)    |

### Returns

| Type  | Name | Description                                |
|-------|------|--------------------------------------------|
| array | tree | Array of CatalogCategoryEntitiesNoChildren |

### Content `CatalogCategoryEntitityNoChildren`

| Type   | Name        | Description                            |
|--------|-------------|----------------------------------------|
| int    | category_id | Category ID                            |
| int    | parent_id   | Parent category ID                     |
| string | name        | Category name                          |
| int    | is_active   | Defines whether the category is active |
| int    | position    | Category position                      |
| int    | level       | Category level                         |

### Examples

#### Request Example SOAP V1

```php
$client = new SoapClient('https://mahohost/api/soap/?wsdl');
$session = $client->login('apiUser', 'apiKey');

$result = $client->call($session, 'catalog_category.level');
var_dump($result);

// When the session can be closed
$client->endSession($session);
```

#### Request Example SOAP V2

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl'); // replace with your store's WSDL URL
$sessionId = $proxy->login('apiUser', 'apiKey'); // replace with your API user and key

$result = $proxy->catalogCategoryLevel($sessionId);
var_dump($result);
```

#### Response Example SOAP V1

```php
array
  0 =>
    array
      'category_id' => string '2' (length=1)
      'parent_id' => int 1
      'name' => string 'Default Category' (length=16)
      'is_active' => string '1' (length=1)
      'position' => string '1' (length=1)
      'level' => string '1' (length=1)
  1 =>
    array
      'category_id' => string '3' (length=1)
      'parent_id' => int 1
      'name' => string 'root_category' (length=13)
      'is_active' => string '1' (length=1)
      'position' => string '2' (length=1)
      'level' => string '1' (length=1)
```

## Category Move

### Method

- `catalog_category.move` (SOAP V1)
- `catalogCategoryMove` (SOAP V2)

Allows you to move the required category in the category tree.

### Alias

- `category.move`

### Arguments

| Type   | Name       | Description                                                                                 |
|--------|------------|---------------------------------------------------------------------------------------------|
| string | sessionId  | Session ID                                                                                  |
| int    | categoryId | ID of the category to be moved                                                              |
| int    | parentId   | ID of the new parent category                                                               |
| string | afterId    | ID of the category after which the required category will be moved (optional for V1 and V2) |

### Returns

| Type    | Name | Description                   |
|---------|------|-------------------------------|
| boolean | id   | True if the category is moved |

### Examples

#### Request Example SOAP V1

```php
$client = new SoapClient('https://mahohost/api/soap/?wsdl');
$session = $client->login('apiUser', 'apiKey');

$result = $client->call($session, 'catalog_category.move', ['categoryId' => '4', 'parentId' => '3']);
var_dump($result);

// When the session can be closed
$client->endSession($session);
```

#### Request Example SOAP V2

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl'); // replace with your store's WSDL URL
$sessionId = $proxy->login('apiUser', 'apiKey'); // replace with your API user and key

$result = $proxy->catalogCategoryMove($sessionId, '4', '3');
var_dump($result);
```

#### Request Example SOAP V2 (WS-I Compliance Mode)

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl');
$sessionId = $proxy->login((object)['username' => 'apiUser', 'apiKey' => 'apiKey']);

$result = $proxy->catalogCategoryMove((object)['sessionId' => $sessionId->result, 'categoryId' => '19', 'parentId' => '8', 'afterId' => '4']);
var_dump($result->result);
```

**Note**: Please make sure that you are not moving the category to any of its own children.
There are no extra checks to prevent doing it through API,
and you won’t be able to fix this from the admin interface later.

## Remove Product

### Method

- `catalog_category.removeProduct` (SOAP V1)
- `catalogCategoryRemoveProduct` (SOAP V2)

Allows you to remove the product assignment from the category.

### Alias

- `category.removeProduct`

### Arguments

| Type   | Name           | Description                                                                |
|--------|----------------|----------------------------------------------------------------------------|
| string | sessionId      | Session ID                                                                 |
| int    | categoryId     | Category ID                                                                |
| string | productId      | ID or SKU of the product to be removed from the category                   |
| string | identifierType | Defines whether the product ID or SKU is passed in the `product` parameter |

### Returns

| Type    | Description                                      |
|---------|--------------------------------------------------|
| boolean | True if the product is removed from the category |

### Examples

#### Request Example SOAP V1

```php
$client = new SoapClient('https://mahohost/api/soap/?wsdl');
$session = $client->login('apiUser', 'apiKey');

$result = $client->call(
    $session,
    'catalog_category.removeProduct',
    [
        'categoryId' => '4',
        'product' => '3'
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

$result = $proxy->catalogCategoryRemoveProduct($sessionId, '4', '3');
var_dump($result);
```

#### Request Example SOAP V2 (WS-I Compliance Mode)

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl');
$sessionId = $proxy->login((object)['username' => 'apiUser', 'apiKey' => 'apiKey']);

$result = $proxy->catalogCategoryRemoveProduct(
    (object)[
        'sessionId' => $sessionId->result,
        'categoryId' => '4',
        'productId' => '3'
    ]
);
var_dump($result->result);
```

## Category Tree

### Method

- `catalog_category.tree` (SOAP V1)
- `catalogCategoryTree` (SOAP V2)

Allows you to retrieve the hierarchical tree of categories.

### Alias

- `category.tree`

### Arguments

| Type   | Name      | Description                          |
|--------|-----------|--------------------------------------|
| string | sessionId | Session ID                           |
| string | parentId  | ID of the parent category (optional) |
| string | storeView | Store view (optional)                |

### Returns

| Type  | Name | Description                  |
|-------|------|------------------------------|
| array | tree | Array of catalogCategoryTree |

### Content `catalogCategoryTree`

| Type   | Name        | Description                      |
|--------|-------------|----------------------------------|
| int    | category_id | Category ID                      |
| int    | parent_id   | Parent category ID               |
| string | name        | Category name                    |
| int    | position    | Category position                |
| int    | level       | Category level                   |
| array  | children    | Array of CatalogCategoryEntities |

### Content `catalogCategoryEntity`

| Type   | Name        | Description                            |
|--------|-------------|----------------------------------------|
| int    | category_id | Category ID                            |
| int    | parent_id   | Parent category ID                     |
| string | name        | Category name                          |
| int    | is_active   | defines whether the category is active |
| int    | position    | Category position                      |
| int    | level       | Category level                         |
| array  | children    | Array of CatalogCategoryEntities       |

### Examples

#### Request Example SOAP V1

```php
$client = new SoapClient('https://mahohost/api/soap/?wsdl');
$session = $client->login('apiUser', 'apiKey');

$result = $client->call($session, 'catalog_category.tree');
var_dump($result);

// When the session can be closed
$client->endSession($session);
```

#### Request Example SOAP V2

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl'); // replace with your store's WSDL URL
$sessionId = $proxy->login('apiUser', 'apiKey'); // replace with your API user and key

$result = $proxy->catalogCategoryTree($sessionId);
var_dump($result);
```

#### Request Example SOAP V2 (WS-I Compliance Mode)

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl');
$sessionId = $proxy->login((object)['username' => 'apiUser', 'apiKey' => 'apiKey']);

$result = $proxy->catalogCategoryTree((object)['sessionId' => $sessionId->result, 'parentId' => '15']);
var_dump($result->result);
```

#### Response Example SOAP V1

```php
array
  'category_id' => string '1' (length=1)
  'parent_id' => string '0' (length=1)
  'name' => string 'Root Catalog' (length=12)
  'is_active' => null
  'position' => string '0' (length=1)
  'level' => string '0' (length=1)
  'children' =>
    array
      0 =>
        array
          'category_id' => string '2' (length=1)
          'parent_id' => string '1' (length=1)
          'name' => string 'Default Category' (length=16)
          'is_active' => string '1' (length=1)
          'position' => string '1' (length=1)
          'level' => string '1' (length=1)
          'children' =>
            array
              ...
      1 =>
        array
          'category_id' => string '3' (length=1)
          'parent_id' => string '1' (length=1)
          'name' => string 'root_category' (length=13)
          'is_active' => string '1' (length=1)
          'position' => string '2' (length=1)
          'level' => string '1' (length=1)
          'children' =>
            array
              ...
```

## Category Update

### Method

- `catalog_category.update` (SOAP V1)
- `catalogCategoryUpdate` (SOAP V2)

Update the required category. Note that you should specify only those parameters which you want to be updated.

### Alias

- `category.update`

### Arguments

| Type   | Name         | Description                             |
|--------|--------------|-----------------------------------------|
| string | sessionId    | Session ID                              |
| int    | categoryId   | ID of the category to be updated        |
| array  | categoryData | An array of catalogCategoryEntityCreate |
| string | storeView    | Store view ID or code (optional)        |

### Returns

| Type    | Description                     |
|---------|---------------------------------|
| boolean | True if the category is updated |

### Content `catalogCategoryEntityCreate`

| Type          | Name                       | Description                                                                                                               |
|---------------|----------------------------|---------------------------------------------------------------------------------------------------------------------------|
| string        | name                       | Name of the category to be updated                                                                                        |
| int           | is_active                  | Defines whether the category is visible in the frontend                                                                   |
| int           | position                   | Position of the category to be updated                                                                                    |
| arrayOfString | available_sort_by          | All available options by which products in the category can be sorted                                                     |
| string        | custom_design              | The custom design for the category                                                                                        |
| int           | custom_apply_to_products   | Apply the custom design to all products assigned to the category                                                          |
| string        | custom_design_from         | Date starting from which the custom design will be applied to the category                                                |
| string        | custom_design_to           | Date till which the custom design will be applied to the category                                                         |
| string        | custom_layout_update       | Custom layout update                                                                                                      |
| string        | default_sort_by            | The default option by which products in the category are sorted                                                           |
| string        | description                | Category description                                                                                                      |
| string        | display_mode               | Content that will be displayed on the category view page                                                                  |
| int           | is_anchor                  | Defines whether the category will be anchored                                                                             |
| int           | landing_page               | Landing page                                                                                                              |
| string        | meta_description           | Category meta description                                                                                                 |
| string        | meta_keywords              | Category meta keywords                                                                                                    |
| string        | meta_title                 | Category meta title                                                                                                       |
| string        | page_layout                | Type of page layout that the category should use                                                                          |
| string        | url_key                    | A relative URL path which can be entered in place of the standard target path                                             |
| int           | include_in_menu            | Defines whether the category is visible on the top menu bar in the frontend                                               |
| string        | filter_price_range         | Price range of each price level displayed in the layered navigation block                                                 |
| int           | custom_use_parent_settings | Defines whether the category will inherit custom design settings of the category to which it is assigned. 1 - Yes, 0 - No |

### Faults

_No Faults._

### Examples

#### Request Example SOAP V1

```php
$client = new SoapClient('https://mahohost/api/soap/?wsdl');
$session = $client->login('apiUser', 'apiKey');

$result = $client->call(
    $session,
    'catalog_category.update',
    [
        23,
        [
            'name' => 'Category name',
            'is_active' => 1,
            'position' => 1,
            // The position parameter is deprecated, so category anyway will be positioned in the end of list
            // and you can't set position directly. Use catalog_category.move instead.
            'available_sort_by' => 'position',
            'custom_design' => null,
            'custom_apply_to_products' => null,
            'custom_design_from' => null,
            'custom_design_to' => null,
            'custom_layout_update' => null,
            'default_sort_by' => 'position',
            'description' => 'Category description',
            'display_mode' => null,
            'is_anchor' => 0,
            'landing_page' => null,
            'meta_description' => 'Category meta description',
            'meta_keywords' => 'Category meta keywords',
            'meta_title' => 'Category meta title',
            'page_layout' => 'two_columns_left',
            'url_key' => 'url-key',
            'include_in_menu' => 1
        ]
    ]
);

var_dump($result);
```

#### Request Example SOAP V2

```php
$client = new SoapClient('https://mahohost/api/v2_soap/?wsdl');
$session = $client->login('apiUser', 'apiKey');

$result = $client->catalogCategoryUpdate(
    $session,
    23,
    [
        'name' => 'Category name 2',
        'is_active' => 1,
        'position' => 1,
        // The position parameter is deprecated, so category anyway will be positioned in the end of list
        // and you can't set position directly. Use catalog_category.move instead.
        'available_sort_by' => ['position'],
        'custom_design' => null,
        'custom_apply_to_products' => null,
        'custom_design_from' => null,
        'custom_design_to' => null,
        'custom_layout_update' => null,
        'default_sort_by' => 'position',
        'description' => 'Category description',
        'display_mode' => null,
        'is_anchor' => 0,
        'landing_page' => null,
        'meta_description' => 'Category meta description',
        'meta_keywords' => 'Category meta keywords',
        'meta_title' => 'Category meta title',
        'page_layout' => 'two_columns_left',
        'url_key' => 'url-key',
        'include_in_menu' => 1
    ]
);

var_dump($result);
```

#### Request Example SOAP V2 (WS-I Compliance Mode)

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl');
$sessionId = $proxy->login((object)['username' => 'apiUser', 'apiKey' => 'apiKey']);

$result = $proxy->catalogCategoryUpdate(
    (object)[
        'sessionId' => $sessionId->result,
        'categoryId' => '23',
        'categoryData' => (object)[
            'name' => 'Category Name Updated',
            'is_active' => '1',
            'position' => '1',
            'available_sort_by' => ['name'],
            'default_sort_by' => 'name',
            'description' => 'Category description',
            'is_anchor' => '1',
            'include_in_menu' => '1'
        ]
    ]
);
var_dump($result->result);
```

## Category Product Update

### Method

- `catalog_category.updateProduct` (SOAP V1)
- `catalogCategoryUpdateProduct` (SOAP V2)

Allows you to update the product assigned to a category. The product position is updated.

### Aliases

- `category.updateProduct`

### Arguments

| Type   | Name           | Description                                                                |
|--------|----------------|----------------------------------------------------------------------------|
| string | sessionId      | Session ID                                                                 |
| int    | categoryId     | ID of the category to which the product is assigned                        |
| string | productId      | ID or SKU of the product to be updated                                     |
| string | position       | Position of the product in the category (optional)                         |
| string | identifierType | Defines whether the product ID or SKU is passed in the 'product' parameter |

### Returns

| Type    | Description                                    |
|---------|------------------------------------------------|
| boolean | True if the product is updated in the category |

### Examples

#### Request Example SOAP V1

```php
$client = new SoapClient('https://mahohost/api/soap/?wsdl');
$session = $client->login('apiUser', 'apiKey');

$result = $client->call(
    $session,
    'catalog_category.updateProduct',
    [
        'categoryId' => '4',
        'product' => '1',
        'position' => '3'
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

$result = $proxy->catalogCategoryUpdateProduct($sessionId, '4', '1', '3');
var_dump($result);
```

#### Request Example SOAP V2 (WS-I Compliance Mode)

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl');
$sessionId = $proxy->login((object)['username' => 'apiUser', 'apiKey' => 'apiKey']);

$result = $proxy->catalogCategoryUpdateProduct(
    (object)[
        'sessionId' => $sessionId->result,
        'categoryId' => '4',
        'productId' => '1',
        'position' => '3'
    ]
);
var_dump($result->result);
```
