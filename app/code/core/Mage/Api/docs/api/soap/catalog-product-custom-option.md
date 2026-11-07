---
description: Manage product custom options over the Maho SOAP API - add, update, list, retrieve, and remove options, and get the available option types.
---

# Catalog Product Custom Option

!!! warning "Legacy API"
    This is Maho's legacy SOAP/XML-RPC API, inherited from Magento 1 and kept for backward compatibility. New integrations should use the modern [REST & GraphQL API (v2)](../v2/index.md) instead.

## Introduction

### Resource

- `catalog_product_custom_option`

### Alias

- `product_custom_option`

### Methods

- `product_custom_option.add` - Add a new custom option to a product.
- `product_custom_option.update` - Update the product custom option.
- `product_custom_option.types` - Get the list of available custom option types.
- `product_custom_option.list` - Retrieve the list of product custom options.
- `product_custom_option.info` - Get full information about the custom option in a product.
- `product_custom_option.remove` - Remove the custom option.

### Faults

| Fault Code | Fault Message                                                                   |
|------------|---------------------------------------------------------------------------------|
| 101        | Product with requested id does not exist.                                       |
| 102        | Provided data is invalid.                                                       |
| 103        | Error while saving an option. Details are in the error message.                 |
| 104        | Store with requested code/id does not exist.                                    |
| 105        | Option with requested id does not exist.                                        |
| 106        | Invalid option type provided. Call 'types' to get list of allowed option types. |
| 107        | Error while deleting an option. Details are in the error message.               |

## Add

### Method

- `product_custom_option.add` (SOAP V1)
- `catalogProductCustomOptionAdd` (SOAP V2)

Allows you to add a new custom option for a product.

### Arguments

| Type   | Name      | Description                              |
|--------|-----------|------------------------------------------|
| string | sessionId | Session ID                               |
| string | productId | Product ID                               |
| array  | data      | Array of catalogProductCustomOptionToAdd |
| string | store     | Store view ID or code (optional)         |

### Return

| Type    | Description                        |
|---------|------------------------------------|
| boolean | True if the custom option is added |

### Content `catalogProductCustomOptionToAdd`

| Type   | Name              | Description                                         |
|--------|-------------------|-----------------------------------------------------|
| string | title             | Option title                                        |
| string | type              | Option type                                         |
| string | sort_order        | Option sort order                                   |
| int    | is_require        | Defines whether the option is required              |
| array  | additional_fields | Array of catalogProductCustomOptionAdditionalFields |

### Content `catalogProductCustomOptionAdditionalFieldsEntity`

| Type   | Name           | Description                                                                      |
|--------|----------------|----------------------------------------------------------------------------------|
| string | title          | Custom option title                                                              |
| string | price          | Custom option price                                                              |
| string | price_type     | Price type. Possible values are as follows: 'fixed' or 'percent'                 |
| string | sku            | Custom option SKU                                                                |
| string | max_characters | Maximum number of characters for the customer input on the frontend (optional)   |
| string | sort_order     | Custom option sort order                                                         |
| string | file_extension | List of file extensions allowed to upload by the user on the frontend (optional) |
| string | image_size_x   | Width limit for uploaded images (optional)                                       |
| string | image_size_y   | Height limit for uploaded images (optional)                                      |
| string | value_id       | Value ID                                                                         |

### Faults

| Fault Code | Fault Message                                                                   |
|------------|---------------------------------------------------------------------------------|
| 101        | Product with requested id does not exist.                                       |
| 102        | Provided data is invalid.                                                       |
| 103        | Error while saving an option. Details are in the error message.                 |
| 104        | Store with requested code/id does not exist.                                    |
| 106        | Invalid option type provided. Call 'types' to get list of allowed option types. |

### Examples

#### Request Example SOAP V1

```php
$proxy = new SoapClient('https://mahohost/api/soap/?wsdl');
$sessionId = $proxy->login('apiUser', 'apiKey');

$productId = 1; // Existing product ID

// Add custom option of Text Field type
$customTextFieldOption = [
    'title' => 'Custom Text Field Option Title',
    'type' => 'field',
    'is_require' => 1,
    'sort_order' => 0,
    'additional_fields' => [
        [
            'price' => 10.00,
            'price_type' => 'fixed',
            'sku' => 'custom_text_option_sku',
            'max_characters' => 255
        ]
    ]
];
$resultCustomTextFieldOptionAdd = $proxy->call(
    $sessionId,
    'product_custom_option.add',
    [
        $productId,
        $customTextFieldOption
    ]
);

// Add custom option of File type
$customFileOption = [
    'title' => 'Custom File Option Title',
    'type' => 'file',
    'is_require' => 1,
    'sort_order' => 5,
    'additional_fields' => [
        [
            'price' => 10.00,
            'price_type' => 'fixed',
            'sku' => 'custom_file_option_sku',
            'file_extension' => 'jpg',
            'image_size_x' => 800,
            'image_size_y' => 600
        ]
    ]
];
$resultCustomFileOptionAdd = $proxy->call(
    $sessionId,
    'product_custom_option.add',
    [
        $productId,
        $customFileOption
    ]
);

// Add custom option of Dropdown type
$customDropdownOption = [
    'title' => 'Custom Dropdown Option Title',
    'type' => 'drop_down',
    'is_require' => 1,
    'sort_order' => 10,
    'additional_fields' => [
        [
            'title' => 'Dropdown row #1',
            'price' => 10.00,
            'price_type' => 'fixed',
            'sku' => 'custom_select_option_sku_1',
            'sort_order' => 0
        ],
        [
            'title' => 'Dropdown row #2',
            'price' => 10.00,
            'price_type' => 'fixed',
            'sku' => 'custom_select_option_sku_2',
            'sort_order' => 5
        ]
    ]
];
$resultCustomDropdownOptionAdd = $proxy->call(
    $sessionId,
    'product_custom_option.add',
    [
        $productId,
        $customDropdownOption
    ]
);
```

#### Request Example SOAP V2

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl');
$sessionId = $proxy->login('apiUser', 'apiKey');

$result = $proxy->catalogProductCustomOptionAdd(
    $sessionId,
    '1',
    [
        'title' => 'title',
        'type' => 'field',
        'sort_order' => '1',
        'is_require' => 1,
        'additional_fields' => [
            [
                'price' => '15',
                'price_type' => 'fixed',
                'sku' => 'sku',
                'max_characters' => '100'
            ]
        ]
    ]
);
var_dump($result);
```

#### Request Example SOAP V2 (WS-I Compliance Example)

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl');
$sessionId = $proxy->login((object)['username' => 'apiUser', 'apiKey' => 'apiKey']);

$result = $proxy->catalogProductCustomOptionAdd(
    (object)[
        'sessionId' => $sessionId->result,
        'productId' => '1',
        'store' => '1',
        'data' => (object)[
            'title' => 'title',
            'type' => 'field',
            'sort_order' => '1',
            'is_require' => 1,
            'additional_fields' => [
                [
                    'price' => '15',
                    'price_type' => 'fixed',
                    'sku' => 'sku',
                    'max_characters' => '100'
                ]
            ]
        ]
    ]
);
var_dump($result->result);
```

## Update

### Method

- `product_custom_option.update` (SOAP V1)
- `catalogProductCustomOptionUpdate` (SOAP V2)

Allows you to update the required product custom option.

### Arguments

| Type   | Name      | Description                                 |
|--------|-----------|---------------------------------------------|
| string | sessionId | Session ID                                  |
| string | optionId  | Option ID                                   |
| array  | data      | Array of catalogProductCustomOptionToUpdate |
| string | store     | Store view ID or code (optional)            |

### Return

| Type       | Description                              |
|------------|------------------------------------------|
| booleanint | True (1) if the custom option is updated |

### Content `catalogProductCustomOptionToUpdate`

| Type   | Name              | Description                                         |
|--------|-------------------|-----------------------------------------------------|
| string | title             | Title of the custom option to be updated            |
| string | type              | Custom option type                                  |
| string | sort_order        | Custom option sort order                            |
| int    | is_require        | Defines whether the custom option is required       |
| array  | additional_fields | Array of catalogProductCustomOptionAdditionalFields |

### Content `catalogProductCustomOptionAdditionalFields`

| Type   | Name           | Description                                                                                                   |
|--------|----------------|---------------------------------------------------------------------------------------------------------------|
| string | title          | Custom option title                                                                                           |
| string | price          | Custom option price                                                                                           |
| string | price_type     | Price type. Possible values are as follows: 'fixed' or 'percent'                                              |
| string | sku            | Custom option SKU                                                                                             |
| string | max_characters | Maximum number of characters for the customer input on the frontend (optional)                                |
| string | sort_order     | Custom option sort order                                                                                      |
| string | file_extension | List of file extensions allowed to upload by the user on the frontend (optional; for the **File** input type) |
| string | image_size_x   | Width limit for uploaded images (optional; for the **File** input type)                                       |
| string | image_size_y   | Height limit for uploaded images (optional; for the **File** input type)                                      |
| string | value_id       | Value ID                                                                                                      |

### Faults

| Fault Code | Fault Message                                                                   |
|------------|---------------------------------------------------------------------------------|
| 101        | Product with requested id does not exist.                                       |
| 102        | Provided data is invalid.                                                       |
| 103        | Error while saving an option. Details are in the error message.                 |
| 104        | Store with requested code/id does not exist.                                    |
| 105        | Option with requested id does not exist.                                        |
| 106        | Invalid option type provided. Call 'types' to get list of allowed option types. |

### Examples

#### Request Example SOAP V1

```php
$proxy = new SoapClient('https://mahohost/api/soap/?wsdl');
$sessionId = $proxy->login('apiUser', 'apiKey');

$selectOptionId = 1379;
$selectOptionValueId = 794;
$textOptionId = 1380;
$fileOptionId = 1381;

// Update custom option of Text Field type
$customTextFieldOption = [
    'title' => 'Custom Text Field Option Title Updated',
    'type' => 'field',
    'is_require' => 1,
    'sort_order' => 20,
    'additional_fields' => [
        [
            'price' => 13.00,
            'price_type' => 'fixed',
            'sku' => 'custom_text_option_sku_updated',
            'max_characters' => 127
        ]
    ]
];
$resultCustomTextFieldOptionUpdate = $proxy->call(
    $sessionId,
    'product_custom_option.update',
    [
         $textOptionId,
         $customTextFieldOption
    ]
);

// Update custom option of File type
$customFileOption = [
    'title' => 'Custom File Option Title Updated',
    'additional_fields' => [
        [
            'image_size_x' => 800,
            'image_size_y' => 999
        ]
    ]
];
$resultCustomFileOptionUpdate = $proxy->call(
    $sessionId,
    'product_custom_option.update',
    [
         $fileOptionId,
         $customFileOption
    ]
);

// Update custom option of Dropdown type
$customDropdownOption = [
    'title' => 'Custom Dropdown Option Title Updated to Multiselect',
    'type' => 'multiple',
    'additional_fields' => [
        [
            'value_id' => $selectOptionValueId,
            'price' => 14.00,
            'price_type' => 'percent',
            'sku' => 'custom_select_option_sku_1 updated',
            'sort_order' => 26
        ]
    ]
];
$resultCustomDropdownOptionUpdate = $proxy->call(
    $sessionId,
    'product_custom_option.update',
    [
         $selectOptionId,
         $customDropdownOption
    ]
);
```

#### Request Example SOAP V2

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl');
$sessionId = $proxy->login('apiUser', 'apiKey');

$result = $proxy->catalogProductCustomOptionUpdate($sessionId, '1', [
    'title' => 'title_updated',
    'is_require' => 0,
    'sort_order' => '2'
]);
var_dump($result);
```

#### Request Example SOAP V2 (WS-I Compliance Mode)

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl');
$sessionId = $proxy->login((object)['username' => 'apiUser', 'apiKey' => 'apiKey']);

$result = $proxy->catalogProductCustomOptionUpdate(
    (object)[
        'sessionId' => $sessionId->result,
        'optionId' => '1',
        'data' => (object)[
            'title' => 'title_updated',
            'is_require' => 0,
            'sort_order' => '2'
        ]
    ]
);
var_dump($result->result);
```

## Types

### Method

- `product_custom_option.types` (SOAP V1)
- `catalogProductCustomOptionTypes` (SOAP V2)

Allows you to retrieve the list of available custom option types.

### Arguments

| Type   | Name      | Description  |
|--------|-----------|--------------|
| string | sessionId | Session ID   |

### Return

| Type  | Name   | Description                              |
|-------|--------|------------------------------------------|
| array | result | Array of catalogProductCustomOptionTypes |

### Content `catalogProductCustomOptionTypesEntity`

| Type   | Name  | Description         |
|--------|-------|---------------------|
| string | label | Custom option label |
| string | value | Custom option value |

### Faults

_No Faults._

### Examples

#### Request Example SOAP V1

```php
$client = new SoapClient('https://mahohost/api/soap/?wsdl');
$session = $client->login('apiUser', 'apiKey');

$result = $client->call($session, 'product_custom_option.types');
var_dump($result);

// When the session can be closed
$client->endSession($session);
```

#### Request Example SOAP V2

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl'); // replace with your store's WSDL URL
$sessionId = $proxy->login('apiUser', 'apiKey'); // replace with your API user and key

$result = $proxy->catalogProductCustomOptionTypes($sessionId);
var_dump($result);
```

#### Request Example SOAP V2 (WS-I Compliance Mode)

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl');
$sessionId = $proxy->login((object)['username' => 'apiUser', 'apiKey' => 'apiKey']);

$result = $proxy->catalogProductCustomOptionTypes((object)['sessionId' => $sessionId->result]);
var_dump($result->result);
```

#### Response Example SOAP V1

```php
array
  0 =>
    array
      'label' => string 'Field' (length=5)
      'value' => string 'field' (length=5)
  1 =>
    array
      'label' => string 'Area' (length=4)
      'value' => string 'area' (length=4)
  2 =>
    array
      'label' => string 'File' (length=4)
      'value' => string 'file' (length=4)
  3 =>
    array
      'label' => string 'Drop-down' (length=9)
      'value' => string 'drop_down' (length=9)
  4 =>
    array
      'label' => string 'Radio Buttons' (length=13)
      'value' => string 'radio' (length=5)
  5 =>
    array
      'label' => string 'Checkbox' (length=8)
      'value' => string 'checkbox' (length=8)
  6 =>
    array
      'label' => string 'Multiple Select' (length=15)
      'value' => string 'multiple' (length=8)
  7 =>
    array
      'label' => string 'Date' (length=4)
      'value' => string 'date' (length=4)
  8 =>
    array
      'label' => string 'Date & Time' (length=11)
      'value' => string 'date_time' (length=9)
  9 =>
    array
      'label' => string 'Time' (length=4)
      'value' => string 'time' (length=4)
```

## List

### Method

- `product_custom_option.list` (SOAP V1)
- `catalogProductCustomOptionList` (SOAP V2)

Allows you to retrieve the list of custom options for a specific product.

### Arguments

| Type   | Name      | Description                                                 |
|--------|-----------|-------------------------------------------------------------|
| string | sessionId | Session ID                                                  |
| string | productId | Product ID                                                  |
| string | store     | Store view ID or code (optional but required for WS-I mode) |

### Return

| Type  | Name   | Description                             |
|-------|--------|-----------------------------------------|
| array | result | Array of catalogProductCustomOptionList |

### Content `catalogProductCustomOptionList`

| Type   | Name       | Description                                   |
|--------|------------|-----------------------------------------------|
| string | option_id  | Custom option ID                              |
| string | title      | Custom option title                           |
| string | type       | Custom option type                            |
| string | sort_order | Custom option sort order                      |
| int    | is_require | Defines whether the custom option is required |

### Faults

| Fault Code | Fault Message                                |
|------------|----------------------------------------------|
| 101        | Product with requested id does not exist.    |
| 104        | Store with requested code/id does not exist. |

### Examples

#### Request Example SOAP V1

```php
$client = new SoapClient('https://mahohost/api/soap/?wsdl');
$session = $client->login('apiUser', 'apiKey');

$result = $client->call($session, 'product_custom_option.list', '1');
var_dump($result);

// When the session can be closed
$client->endSession($session);
```

#### Request Example SOAP V2

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl'); // replace with your store's WSDL URL
$sessionId = $proxy->login('apiUser', 'apiKey'); // replace with your API user and key

$result = $proxy->catalogProductCustomOptionList($sessionId, '1');
var_dump($result);
```

#### Request Example SOAP V2 (WS-I Compliance Mode)

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl');
$sessionId = $proxy->login((object)['username' => 'apiUser', 'apiKey' => 'apiKey']);

$result = $proxy->catalogProductCustomOptionList((object)['sessionId' => $sessionId->result, 'productId' => '1', 'store' => '1']);
var_dump($result->result);
```

#### Response Example SOAP V1

```php
array
  0 =>
    array
      'option_id' => string '1' (length=1)
      'title' => string 'model' (length=5)
      'type' => string 'drop_down' (length=9)
      'is_require' => string '1' (length=1)
      'sort_order' => string '0' (length=1)
```

## Info

### Method

- `product_custom_option.info` (SOAP V1)
- `catalogProductCustomOptionInfo` (SOAP V2)

Allows you to retrieve full information about the custom option in a product.

### Arguments

| Type   | Name      | Description                      |
|--------|-----------|----------------------------------|
| string | sessionId | Session ID                       |
| string | optionId  | Option ID                        |
| string | store     | Store view ID or code (optional) |

### Return

| Type  | Name   | Description                                   |
|-------|--------|-----------------------------------------------|
| array | result | Array of catalogProductCustomOptionInfoEntity |

### Content `catalogProductCustomOptionInfoEntity`

| Type   | Name              | Description                                                                    |
|--------|-------------------|--------------------------------------------------------------------------------|
| string | title             | Custom option title                                                            |
| string | type              | Custom option type. Can have one of the following values: 'fixed' or 'percent' |
| string | sort_order        | Custom option sort order                                                       |
| int    | is_require        | Defines whether the custom option is required                                  |
| array  | additional_fields | Array of catalogProductCustomOptionAdditionalFields                            |

### Content `catalogProductCustomOptionAdditionalFields`

| Type   | Name           | Description                                                                                                   |
|--------|----------------|---------------------------------------------------------------------------------------------------------------|
| string | title          | Custom option title                                                                                           |
| string | price          | Custom option price                                                                                           |
| string | price_type     | Price type. Possible values are as follows: 'fixed' or 'percent'                                              |
| string | sku            | Custom option SKU                                                                                             |
| string | max_characters | Maximum number of characters for the customer input on the frontend (optional)                                |
| string | sort_order     | Custom option sort order                                                                                      |
| string | file_extension | List of file extensions allowed to upload by the user on the frontend (optional; for the **File** input type) |
| string | image_size_x   | Width limit for uploaded images (optional; for the **File** input type)                                       |
| string | image_size_y   | Height limit for uploaded images (optional; for the **File** input type)                                      |
| string | value_id       | Value ID                                                                                                      |

### Faults

| Fault Code | Fault Message                                |
|------------|----------------------------------------------|
| 101        | Product with requested id does not exist.    |
| 104        | Store with requested code/id does not exist. |
| 105        | Option with requested id does not exist.     |

### Examples

#### Request Example SOAP V1

```php
$client = new SoapClient('https://mahohost/api/soap/?wsdl');
$session = $client->login('apiUser', 'apiKey');

$result = $client->call($session, 'product_custom_option.info', '1');
var_dump($result);

// When the session can be closed
$client->endSession($session);
```

#### Request Example SOAP V2

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl'); // replace with your store's WSDL URL
$sessionId = $proxy->login('apiUser', 'apiKey'); // replace with your API user and key

$result = $proxy->catalogProductCustomOptionInfo($sessionId, '1');
var_dump($result);
```

#### Request Example SOAP V2 (WS-I Compliance Mode)

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl');
$sessionId = $proxy->login((object)['username' => 'apiUser', 'apiKey' => 'apiKey']);

$result = $proxy->catalogProductCustomOptionInfo((object)['sessionId' => $sessionId->result, 'optionId' => '1']);
var_dump($result->result);
```

#### Response Example SOAP V1

```php
array
  'title' => string 'model' (length=5)
  'type' => string 'drop_down' (length=9)
  'is_require' => string '1' (length=1)
  'sort_order' => string '0' (length=1)
  'additional_fields' =>
    array
      0 =>
        array
          'value_id' => string '1' (length=1)
          'title' => string 'monoblock' (length=9)
          'price' => string '139.9900' (length=8)
          'price_type' => string 'fixed' (length=5)
          'sku' => string 'monoblock' (length=9)
          'sort_order' => string '0' (length=1)
      1 =>
        array
          'value_id' => string '2' (length=1)
          'title' => string 'slider' (length=6)
          'price' => string '239.9900' (length=8)
          'price_type' => string 'fixed' (length=5)
          'sku' => string 'slider' (length=6)
          'sort_order' => string '0' (length=1)
```

## Remove

### Method

- `product_custom_option.remove` (SOAP V1)
- `catalogProductCustomOptionRemove` (SOAP V2)

Allows you to remove a custom option from the product.

### Arguments

| Type   | Name      | Description      |
|--------|-----------|------------------|
| string | sessionId | Session ID       |
| string | optionId  | Custom option ID |

### Return

| Type    | Description                          |
|---------|--------------------------------------|
| boolean | True if the custom option is removed |

### Faults

| Fault Code | Fault Message                                                     |
|------------|-------------------------------------------------------------------|
| 105        | Option with requested id does not exist.                          |
| 107        | Error while deleting an option. Details are in the error message. |

### Examples

#### Request Example SOAP V1

```php
$proxy = new SoapClient('https://mahohost/api/soap/?wsdl');
$sessionId = $proxy->login('apiUser', 'apiKey');
$optionId  = 1; // Existing option ID

$result = $proxy->call(
    $sessionId,
    'product_custom_option.remove',
    [
        $optionId
    ]
);
```

#### Request Example SOAP V2

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl'); // replace with your store's WSDL URL
$sessionId = $proxy->login('apiUser', 'apiKey'); // replace with your API user and key

$result = $proxy->catalogProductCustomOptionRemove($sessionId, '1');
var_dump($result);
```

#### Request Example SOAP V2 (WS-I Compliance Mode)

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl');
$sessionId = $proxy->login((object)['username' => 'apiUser', 'apiKey' => 'apiKey']);

$result = $proxy->catalogProductCustomOptionRemove((object)['sessionId' => $sessionId->result, 'optionId' => '1']);
var_dump($result->result);
```
