---
description: Retrieve information about a store view and the list of available store views over the Maho SOAP API.
---

# Store

!!! warning "Legacy API"
    This is Maho's legacy SOAP/XML-RPC API, inherited from Magento 1 and kept for backward compatibility. New integrations should use the modern [REST & GraphQL API (v2)](../v2/index.md) instead.

## Store Info

### Method

- `store.info` (SOAP V1)
- `storeInfo` (SOAP V2)

Allows you to retrieve information about the required store view.

### Arguments

| Type   | Name      | Description                      |
|--------|-----------|----------------------------------|
| string | sessionId | Session ID                       |
| string | storeId   | Store view ID or code (optional) |

### Return

| Type  | Name   | Description          |
|-------|--------|----------------------|
| array | result | Array of storeEntity |

### Content `storeEntity`

| Type   | Name       | Description                         |
|--------|------------|-------------------------------------|
| int    | store_id   | Store view ID                       |
| string | code       | Store view code                     |
| int    | website_id | Website ID                          |
| int    | group_id   | Group ID                            |
| string | name       | Store name                          |
| int    | sort_order | Store view sort order               |
| int    | is_active  | Defines whether the store is active |

### Faults

| Fault Code | Fault Message                   |
|------------|---------------------------------|
| 101        | Requested store view not found. |

### Examples

#### Request Example SOAP V1

```php
$client = new SoapClient('https://mahohost/api/soap/?wsdl');
$session = $client->login('apiUser', 'apiKey');

$result = $client->call($session, 'store.info', '2');
var_dump($result);
```

#### Request Example SOAP V2

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl'); // replace with your store's WSDL URL
$sessionId = $proxy->login('apiUser', 'apiKey'); // replace with your API user and key

$result = $proxy->storeInfo($sessionId, '2');
var_dump($result);
```

#### Request Example SOAP V2 (WS-I Compliance Mode)

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl'); 
$sessionId = $proxy->login((object)array('username' => 'apiUser', 'apiKey' => 'apiKey'));

$result = $proxy->storeInfo((object)array('sessionId' => $sessionId->result, 'storeId' => '2'));
var_dump($result->result);
```

#### Response Example SOAP V1

```php
array
  'store_id' => string '2' (length=1)
  'code' => string 'english' (length=7)
  'website_id' => string '2' (length=1)
  'group_id' => string '2' (length=1)
  'name' => string 'English' (length=7)
  'sort_order' => string '0' (length=1)
  'is_active' => string '1' (length=1)
```

## Store list

### Method

- `store.list` (SOAP V1)
- `storeList` (SOAP V2)

Allows you to retrieve the list of store views.

### Arguments

| Type   | Name      | Description |
|--------|-----------|-------------|
| string | sessionId | Session ID  |

### Return

| Type  | Name   | Description          |
|-------|--------|----------------------|
| array | result | Array of storeEntity |

### Content `storeEntity`

| Type   | Name       | Description                         |
|--------|------------|-------------------------------------|
| int    | store_id   | Store view ID                       |
| string | code       | Store view code                     |
| int    | website_id | Website ID                          |
| int    | group_id   | Group ID                            |
| string | name       | Store view name                     |
| int    | sort_order | Store view sort order               |
| int    | is_active  | Defines whether the store is active |

### Faults

_No Faults._

### Examples

#### Request Example SOAP V1

```php
$client = new SoapClient('https://mahohost/api/soap/?wsdl');
$session = $client->login('apiUser', 'apiKey');

$result = $client->call($session, 'store.list');
var_dump($result);
```

#### Request Example SOAP V2

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl'); // replace with your store's WSDL URL
$sessionId = $proxy->login('apiUser', 'apiKey'); // replace with your API user and key

$result = $proxy->storeList($sessionId);
var_dump($result);
```

#### Request Example SOAP V2 (WS-I Compliance Mode)

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl'); 
$sessionId = $proxy->login((object)['username' => 'apiUser', 'apiKey' => 'apiKey']);

$result = $proxy->storeList((object)['sessionId' => $sessionId->result]);
var_dump($result->result);
```

#### Response Example SOAP V1

```php
array
  0 =>
    array
      'store_id' => string '1' (length=1)
      'code' => string 'default' (length=7)
      'website_id' => string '1' (length=1)
      'group_id' => string '1' (length=1)
      'name' => string 'Default Store View' (length=18)
      'sort_order' => string '0' (length=1)
      'is_active' => string '1' (length=1)
  1 =>
    array
      'store_id' => string '2' (length=1)
      'code' => string 'english' (length=7)
      'website_id' => string '2' (length=1)
      'group_id' => string '2' (length=1)
      'name' => string 'English' (length=7)
      'sort_order' => string '0' (length=1)
      'is_active' => string '1' (length=1)
```
