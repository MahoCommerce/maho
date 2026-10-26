---
description: Manage the values of selectable product custom options over the Maho SOAP API - add, list, info, update, and remove.
---

# Catalog Product Custom Option Value

!!! warning "Legacy API"
    This is Maho's legacy SOAP/XML-RPC API, inherited from Magento 1 and kept for backward compatibility. New integrations should use the modern [REST & GraphQL API (v2)](../v2/index.md) instead.

## Introduction

### Resource

- `catalog_product_custom_option_value`

### Alias

- `product_custom_option_value`

### Methods

- `product_custom_option_value.add` - Add a new custom option value to a selectable custom option.
- `product_custom_option_value.list` - Retrieve the list of product custom option values.
- `product_custom_option_value.info` - Retrieve full information about the specified product custom option value.
- `product_custom_option_value.update` - Update the custom option value.
- `product_custom_option_value.remove` - Remove the custom option value.

### Faults

| Fault Code | Fault Message                                                           |
|------------|-------------------------------------------------------------------------|
| 101        | Option value with requested id does not exist.                          |
| 102        | Error while adding an option value. Details are in the error message.   |
| 103        | Option with requested id does not exist.                                |
| 104        | Invalid option type.                                                    |
| 105        | Store with requested code/id does not exist.                            |
| 106        | Can not delete option.                                                  |
| 107        | Error while updating an option value. Details are in the error message. |
| 108        | Title field is required.                                                |
| 109        | Option should have at least one value. Can not delete last value.       |

## Add

### Method

- `product_custom_option_value.add` (SOAP V1)
- `catalogProductCustomOptionValueAdd` (SOAP V2)

Allows you to add a new custom option value to a custom option.
Note that the custom option value can be added only to the option with the **Select** input type.

### Arguments

| Type   | Name      | Description                                 |
|--------|-----------|---------------------------------------------|
| string | sessionId | Session ID                                  |
| string | optionId  | Option ID                                   |
| array  | data      | Array of catalogProductCustomOptionValueAdd |
| string | store     | Store view ID or code (optional)            |

### Return

| Type    | Description                              |
|---------|------------------------------------------|
| boolean | True if the custom option value is added |

### Content `catalogProductCustomOptionValueAdd`

| Type   | Name       | Description                                                                                       |
|--------|------------|---------------------------------------------------------------------------------------------------|
| string | title      | Custom option value title                                                                         |
| string | price      | Custom option value price                                                                         |
| string | price_type | Type of the custom option value price. Can have one of the following values: 'fixed' or 'percent' |
| string | sku        | Custom option value row SKU                                                                       |
| string | sort_order | Custom option value sort order                                                                    |

### Faults

| Fault Code | Fault Message                                                         |
|------------|-----------------------------------------------------------------------|
| 101        | Option value with requested id does not exist.                        |
| 102        | Error while adding an option value. Details are in the error message. |
| 104        | Invalid option type.                                                  |

### Examples

#### Request Example SOAP V1

```php
$proxy = new SoapClient('https://mahohost/api/soap/?wsdl');
$sessionId = $proxy->login('apiUser', 'apiKey');

$productOptionId = 1; // Existing product option ID

// Add custom option value
$customOptionValue = [
    'title' => 'Some value text 1',
    'price' => 10.00,
    'price_type' => 'fixed',
    'sku' => 'custom_text_option_sku',
    'sort_order' => 0
];

$resultCustomOptionValueAdd = $proxy->call(
    $sessionId,
    'product_custom_option_value.add',
    [
        $productOptionId,
        [$customOptionValue]
    ]
);
```

#### Request Example SOAP V2

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl');
$sessionId = $proxy->login('apiUser', 'apiKey');

$result = $proxy->catalogProductCustomOptionValueAdd(
    $sessionId,
    '10',
    [
        [
            'title' => 'value',
            'price' => '99.99',
            'price_type' => 'fixed',
            'sku' => 'sku',
            'sort_order' => '1'
        ]
    ]
);
var_dump($result);
```

#### Request Example SOAP V2 (WS-I Compliance Mode)

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl');
$sessionId = $proxy->login((object)['username' => 'apiUser', 'apiKey' => 'apiKey']);

$result = $proxy->catalogProductCustomOptionValueAdd(
    (object)[
        'sessionId' => $sessionId->result,
        'optionId' => '10',
        'data' => [
            [
                'title' => 'value',
                'price' => '99.99',
                'price_type' => 'fixed',
                'sku' => 'sku',
                'sort_order' => '1'
            ]
        ]
    ]
);
var_dump($result->result);
```

## List

### Method

- `product_custom_option_value.list` (SOAP V1)
- `catalogProductCustomOptionValueList` (SOAP V2)

Allows you to retrieve the list of product custom option values.
Note that the method is available only for the option **Select** input type.

### Arguments

| Type   | Name      | Description                      |
|--------|-----------|----------------------------------|
| string | sessionId | Session ID                       |
| string | optionId  | Option ID                        |
| string | store     | Store view ID or code (optional) |

### Returns

| Type  | Name   | Description                                  |
|-------|--------|----------------------------------------------|
| array | result | Array of catalogProductCustomOptionValueList |

### Content `catalogProductCustomOptionValueListEntity`

| Type   | Name       | Description                                       |
|--------|------------|---------------------------------------------------|
| string | value_id   | Custom option value ID                            |
| string | title      | Custom option value title                         |
| string | price      | Option value price                                |
| string | price_type | Price type. Possible values: 'fixed' or 'percent' |
| string | sku        | Custom option value SKU                           |
| string | sort_order | Option value sort order (optional)                |

### Faults

| Fault Code | Fault Message                                                         |
|------------|-----------------------------------------------------------------------|
| 101        | Provided data is invalid.                                             |
| 102        | Error while adding an option value. Details are in the error message. |

### Examples

#### Request Example SOAP V1

```php
$client = new SoapClient('https://mahohost/api/soap/?wsdl');
$session = $client->login('apiUser', 'apiKey');

$result = $client->call($session, 'product_custom_option_value.list', '3');
var_dump($result);

// When the session can be closed
$client->endSession($session);
```

#### Request Example SOAP V2

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl'); // replace with your store's WSDL URL
$sessionId = $proxy->login('apiUser', 'apiKey'); // replace with your API user and key

$result = $proxy->catalogProductCustomOptionValueList($sessionId, '3');
var_dump($result);
```

#### Request Example SOAP V2 (WS-I Compliance Mode)

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl');
$sessionId = $proxy->login((object)['username' => 'apiUser', 'apiKey' => 'apiKey']);

$result = $proxy->catalogProductCustomOptionValueList(
    (object)[
        'sessionId' => $sessionId->result,
        'optionId' => '3'
    ]
);
var_dump($result->result);
```

#### Response Example SOAP V1

```php
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

## Info

### Method

- `product_custom_option_value.info` (SOAP V1)
- `catalogProductCustomOptionValueInfo` (SOAP V2)

Allows you to retrieve full information about the specified product custom option value.

### Arguments

| Type   | Name      | Description                      |
|--------|-----------|----------------------------------|
| string | sessionId | Session ID                       |
| string | valueId   | Value ID                         |
| string | store     | Store view ID or code (optional) |

### Return

| Type  | Name   | Description                                        |
|-------|--------|----------------------------------------------------|
| array | result | Array of catalogProductCustomOptionValueInfoEntity |

### Content `catalogProductCustomOptionValueInfoEntity`

| Type   | Name               | Description                                                              |
|--------|--------------------|--------------------------------------------------------------------------|
| string | value_id           | Option value ID                                                          |
| string | option_id          | Option ID                                                                |
| string | sku                | Custom option value row SKU                                              |
| string | sort_order         | Option value sort order                                                  |
| string | default_price      | Option value default price                                               |
| string | default_price_type | Default price type. Possible values are as follows: 'fixed' or 'percent' |
| string | store_price        | Option value store price                                                 |
| string | store_price_type   | Store price type. Possible values are as follows: 'fixed' or 'percent'   |
| string | price              | Option value price                                                       |
| string | price_type         | Price type. Possible values are as follows: 'fixed' or 'percent'         |
| string | default_title      | Option value default title                                               |
| string | store_title        | Option value store title                                                 |
| string | title              | Option value title                                                       |

### Faults

| Fault Code | Fault Message                                  |
|------------|------------------------------------------------|
| 101        | Option value with requested id does not exist. |

### Examples

#### Request Example SOAP V1

```php
$client = new SoapClient('https://mahohost/api/soap/?wsdl');
$session = $client->login('apiUser', 'apiKey');

$result = $client->call($session, 'product_custom_option_value.info', '5');
var_dump($result);
```

#### Request Example SOAP V2

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl'); // replace with your store's WSDL URL
$sessionId = $proxy->login('apiUser', 'apiKey'); // replace with your API user and key

$result = $proxy->catalogProductCustomOptionValueInfo($sessionId, '5');
var_dump($result);
```

#### Request Example SOAP V2 (WS-I Compliance Mode)

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl');
$sessionId = $proxy->login((object)['username' => 'apiUser', 'apiKey' => 'apiKey']);

$result = $proxy->catalogProductCustomOptionValueInfo(
    (object)[
        'sessionId' => $sessionId->result,
        'valueId' => '5'
    ]
);
var_dump($result->result);
```

#### Response Example SOAP V1

```php
array
  'option_id' => string '5' (length=1)
  'sku' => string 'slider' (length=6)
  'sort_order' => string '0' (length=1)
  'default_title' => string 'slider' (length=6)
  'store_title' => string 'slider' (length=6)
  'title' => string 'slider' (length=6)
  'default_price' => string '239.9900' (length=8)
  'default_price_type' => string 'fixed' (length=5)
  'store_price' => string '239.9900' (length=8)
  'store_price_type' => string 'fixed' (length=5)
  'price' => string '239.9900' (length=8)
  'price_type' => string 'fixed' (length=5)
  'value_id' => string '2' (length=1)
```

## Update

### Method

- `product_custom_option_value.update` (SOAP V1)
- `catalogProductCustomOptionValueUpdate` (SOAP V2)

Allows you to update the product custom option value.

### Arguments

| Type   | Name      | Description                                          |
|--------|-----------|------------------------------------------------------|
| string | sessionId | Session ID                                           |
| string | valueId   | Value ID                                             |
| array  | data      | Array of catalogProductCustomOptionValueUpdateEntity |
| string | storeId   | Store view ID or code (optional)                     |

### Return

| Type    | Description                                |
|---------|--------------------------------------------|
| boolean | True if the custom option value is updated |

### Content `catalogProductCustomOptionValueUpdateEntity`

| Type   | Name       | Description                                                      |
|--------|------------|------------------------------------------------------------------|
| string | title      | Option value title                                               |
| string | price      | Option value price                                               |
| string | price_type | Price type. Possible values are as follows: 'fixed' or 'percent' |
| string | sku        | Custom option value row SKU                                      |
| string | sort_order | Custom option value sort order                                   |

### Faults

| Fault Code | Fault Message                                                           |
|------------|-------------------------------------------------------------------------|
| 101        | Option value with requested id does not exist.                          |
| 103        | Option with requested id does not exist.                                |
| 104        | Invalid option type.                                                    |
| 107        | Error while updating an option value. Details are in the error message. |
| 108        | Title field is required.                                                |

### Examples

#### Request Example SOAP V1

```php
$proxy = new SoapClient('https://mahohost/api/soap/?wsdl');
$sessionId = $proxy->login('apiUser', 'apiKey');

$productOptionId = 1;// Existing option ID

// Get last value from option values list
$optionValues = $proxy->call($sessionId, 'product_custom_option_value.list', [$productOptionId]);
$optionValue = reset($optionValues);
$valueId = $optionValue['value_id'];
// Update custom option value
$customOptionValue = [
    'title' => 'new title',
    'price' => 12.00,
    'price_type' => 'percent',
    'sku' => 'custom_text_option_2',
    'sort_order' => 2
];
$resultCustomOptionValueUpdate = $proxy->call(
    $sessionId,
    'product_custom_option_value.update',
    [
         $valueId,
         $customOptionValue
    ]
);
```

#### Request Example SOAP V2

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl');
$sessionId = $proxy->login('apiUser', 'apiKey');

$result = $proxy->catalogProductCustomOptionValueUpdate(
    $sessionId,
    '2',
    [
        'title' => 'value',
        'price' => '20',
        'price_type' => 'fixed',
        'sku' => 'sku'
    ]
);
var_dump($result);
```

#### Request Example SOAP V2 (WS-I Compliance Mode)

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl');
$sessionId = $proxy->login((object)['username' => 'apiUser', 'apiKey' => 'apiKey']);

$result = $proxy->catalogProductCustomOptionValueUpdate(
    (object)[
        'sessionId' => $sessionId->result,
        'valueId' => '2',
        'data' => (object)[
            'title' => 'value',
            'sku' => 'sku',
            'price' => '199',
            'price_type' => 'percent'
        ]
    ]
);
var_dump($result->result);
```

## Remove

### Method

- `product_custom_option_value.remove` (SOAP V1)
- `catalogProductCustomOptionValueRemove` (SOAP V2)

Allows you to remove the custom option value from a product.

### Arguments

| Type   | Name      | Description            |
|--------|-----------|------------------------|
| string | sessionId | Session ID             |
| string | valueId   | Custom option value ID |

### Return

| Type       | Description                                    |
|------------|------------------------------------------------|
| booleanint | True (1) if the custom option value is removed |

### Faults

| Fault Code | Fault Message                                                     |
|------------|-------------------------------------------------------------------|
| 103        | Option with requested id does not exist.                          |
| 106        | Can not delete option.                                            |
| 109        | Option should have at least one value. Can not delete last value. |

### Examples

#### Request Example SOAP V1

```php
$proxy = new SoapClient('https://mahohost/api/soap/?wsdl');
$sessionId = $proxy->login('apiUser', 'apiKey');

$productOptionId = 4; // Existing option ID
// Get last value from option values list
$optionValues = $proxy->call($sessionId, 'product_custom_option_value.list', [$productOptionId]);
$optionValue = reset($optionValues);
$valueId = $optionValue['value_id'];

$result = $proxy->call(
    $sessionId,
    'product_custom_option_value.remove',
    [
         $valueId
    ]
);
```

#### Request Example SOAP V2

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl'); // replace with your store's WSDL URL
$sessionId = $proxy->login('apiUser', 'apiKey'); // replace with your API user and key

$result = $proxy->catalogProductCustomOptionValueRemove($sessionId, '4');
var_dump($result);
```

#### Request Example SOAP V2 (WS-I Compliance Mode)

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl');
$sessionId = $proxy->login((object)['username' => 'apiUser', 'apiKey' => 'apiKey']);

$result = $proxy->catalogProductCustomOptionValueRemove(
    (object)[
        'sessionId' => $sessionId->result,
        'valueId' => '4'
    ]
);
var_dump($result->result);
```
