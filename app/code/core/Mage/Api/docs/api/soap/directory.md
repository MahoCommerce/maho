---
description: Retrieve the list of countries and regions configured in Maho over the SOAP API.
---

# Directory

!!! warning "Legacy API"
    This is Maho's legacy SOAP/XML-RPC API, inherited from Magento 1 and kept for backward compatibility. New integrations should use the modern [REST & GraphQL API (v2)](../v2/index.md) instead.

## Country List

### Method

- `directory_country.list` (SOAP V1)
- `directoryCountryList` (SOAP V2)

Retrieve the list of countries from Maho.

### Alias

- `country.list`

### Arguments

| Type   | Name      | Description |
|--------|-----------|-------------|
| string | sessionId | Session ID  |

### Returns

| Type  | Name      | Description                        |
|-------|-----------|------------------------------------|
| array | countries | An array of directoryCountryEntity |

### Content `directoryCountryEntity`

| Type   | Name       | Description                   |
|--------|------------|-------------------------------|
| string | country_id | ID of the retrieved country   |
| string | iso2_code  | ISO 2-alpha code              |
| string | iso3_code  | ISO 3-alpha code              |
| string | name       | Name of the retrieved country |

### Examples

#### Request Example SOAP V1

```php
$proxy = new SoapClient('https://mahohost/api/soap/?wsdl');
$sessionId = $proxy->login('apiUser', 'apiKey');

$countries = $proxy->call($sessionId, 'country.list');
var_dump($countries); // Countries list
```

#### Request Example SOAP V2

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl'); // replace with your store's WSDL URL
$sessionId = $proxy->login('apiUser', 'apiKey'); // replace with your API user and key

$result = $proxy->directoryCountryList($sessionId);
var_dump($result);
```

#### Request Example SOAP V2 (WS-I Compliance Mode)

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl'); 
$sessionId = $proxy->login((object)['username' => 'apiUser', 'apiKey' => 'apiKey']); 
 
$result = $proxy->directoryCountryList((object)['sessionId' => $sessionId->result]);   
var_dump($result->result);
```

#### Response Example SOAP V1

```php
array
  0 =>
    array
      'country_id' => string 'AD' (length=2)
      'iso2_code' => string 'AD' (length=2)
      'iso3_code' => string 'AND' (length=3)
      'name' => string 'Andorra' (length=7)
  1 =>
    array
      'country_id' => string 'AE' (length=2)
      'iso2_code' => string 'AE' (length=2)
      'iso3_code' => string 'ARE' (length=3)
      'name' => string 'United Arab Emirates' (length=20)
  2 =>
    array
      'country_id' => string 'AF' (length=2)
      'iso2_code' => string 'AF' (length=2)
      'iso3_code' => string 'AFG' (length=3)
      'name' => string 'Afghanistan' (length=11)
```

## Region List

### Method

- `directory_region.list` (SOAP V1)
- `directoryRegionList` (SOAP V2)

Retrieve the list of regions in the specified country.

### Aliases

- `region.list`

### Arguments

| Type   | Name      | Description                  |
|--------|-----------|------------------------------|
| string | sessionId | Session ID                   |
| string | country   | Country code in ISO2 or ISO3 |

### Returns

| Type  | Name                       | Description                       |
|-------|----------------------------|-----------------------------------|
| array | directoryRegionEntityArray | An array of directoryRegionEntity |

### Content `directoryRegionEntity`

| Type   | Name      | Description        |
|--------|-----------|--------------------|
| string | region_id | ID of the region   |
| string | code      | Region code        |
| string | name      | Name of the region |

### Faults

| Fault Code | Fault Message       |
|------------|---------------------|
| 101        | Country not exists. |

### Examples

#### Request Example SOAP V1

```php
$proxy = new SoapClient('https://mahohost/api/soap/?wsdl');
$sessionId = $proxy->login('apiUser', 'apiKey');
$regions = $proxy->call($sessionId, 'region.list', 'US');

var_dump($regions); // Region list for USA.
```

#### Request Example SOAP V2

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl'); 
$sessionId = $proxy->login('apiUser', 'apiKey'); 

$result = $proxy->directoryRegionList($sessionId, 'US');
var_dump($result);
```

#### Request Example SOAP V2 (WS-I Compliance Mode)

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl'); 
$sessionId = $proxy->login((object)['username' => 'apiUser', 'apiKey' => 'apiKey']); 
 
$result = $proxy->directoryRegionList((object)['sessionId' => $sessionId->result, 'country' => 'US']);   
var_dump($result->result);
```

#### Response Example SOAP V1

```php
array
  0 =>
    array
      'region_id' => string '1' (length=1)
      'code' => string 'AL' (length=2)
      'name' => string 'Alabama' (length=7)
  1 =>
    array
      'region_id' => string '2' (length=1)
      'code' => string 'AK' (length=2)
      'name' => string 'Alaska' (length=6)
  2 =>
    array
      'region_id' => string '3' (length=1)
      'code' => string 'AS' (length=2)
      'name' => string 'American Samoa' (length=14)
  3 =>
    array
      'region_id' => string '4' (length=1)
      'code' => string 'AZ' (length=2)
      'name' => string 'Arizona' (length=7)
```
