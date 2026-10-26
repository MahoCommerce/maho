---
description: Manage customer addresses over the Maho SOAP API - list, create, retrieve, update, and delete.
---

# Customer Address

!!! warning "Legacy API"
    This is Maho's legacy SOAP/XML-RPC API, inherited from Magento 1 and kept for backward compatibility. New integrations should use the modern [REST & GraphQL API (v2)](../v2/index.md) instead.

## Introduction

### Methods

- `customer_address.list` - Retrieve the list of customer addresses.
- `customer_address.create` - Create a new address for a customer.
- `customer_address.info` - Retrieve the specified customer address.
- `customer_address.update` - Update the customer address.
- `customer_address.delete` - Delete the customer address.

### Faults

| Fault Code | Fault Message                                   |
|------------|-------------------------------------------------|
| 100        | Invalid address data. Details in error message. |
| 101        | Customer not exists.                            |
| 102        | Address not exists.                             |
| 103        | Address not deleted. Details in error message.  |

## Example - Working With Customer Address

```php
$proxy = new SoapClient('https://mahohost/api/?wsdl');
$sessionId = $proxy->login('apiUser', 'apiKey');

// Create new customer
$newCustomer = [
    'firstname' => 'First',
    'lastname' => 'Last',
    'email' => 'test@example.com',
    'password' => 'password',
    'store_id' => 0,
    'website_id' => 0
];

$newCustomerId = $proxy->call($sessionId, 'customer.create', [$newCustomer]);

// Create new customer address
$newCustomerAddress = [
    'firstname' => 'First',
    'lastname' => 'Last',
    'country_id' => 'USA',
    'region_id' => '43',
    'region' => 'New York',
    'city' => 'New York',
    'street' => ['street1', 'street2'],
    'telephone' => '5555-555',
    'postcode' => 10021,
    'is_default_billing' => true,
    'is_default_shipping' => true
];

$newAddressId = $proxy->call($sessionId, 'customer_address.create', [$newCustomerId, $newCustomerAddress]);

var_dump($proxy->call($sessionId, 'customer_address.list', $newCustomerId));

//Update customer address
$proxy->call($sessionId, 'customer_address.update', [$newAddressId, ['firstname' => 'Changed Firstname']]);

var_dump($proxy->call($sessionId, 'customer_address.list', $newCustomerId));

// Delete customer address
$proxy->call($sessionId, 'customer_address.delete', $newAddressId);

var_dump($proxy->call($sessionId, 'customer_address.list', $newCustomerId));
```

## List

### Method

- `customer_address.list` (SOAP V1)
- `customerAddressList` (SOAP V2)

Retrieve the list of customer addresses.

### Arguments

| Type   | Name       | Description |
|--------|------------|-------------|
| string | sessionId  | Session ID  |
| int    | customerId | Customer ID |

### Returns

| Type  | Name   | Description                    |
|-------|--------|--------------------------------|
| array | result | Array of customerAddressEntity |

### Content `customerAddressEntity`

| Type    | Name                | Description                                         |
|---------|---------------------|-----------------------------------------------------|
| int     | customer_address_id | ID of the customer address                          |
| string  | created_at          | Date when the address was created                   |
| string  | updated_at          | Date when the address was updated                   |
| string  | increment_id        | Increment ID                                        |
| string  | city                | City                                                |
| string  | company             | Name of the company                                 |
| string  | country_id          | ID of the country                                   |
| string  | fax                 | Fax                                                 |
| string  | firstname           | Customer first name                                 |
| string  | lastname            | Customer last name                                  |
| string  | middlename          | Customer middle name                                |
| string  | postcode            | Customer postcode                                   |
| string  | prefix              | Customer prefix                                     |
| string  | region              | Name of the region                                  |
| int     | region_id           | Region ID                                           |
| string  | street              | Name of the street                                  |
| string  | suffix              | Customer suffix                                     |
| string  | telephone           | Telephone number                                    |
| boolean | is_default_billing  | True if the address is the default one for billing  |
| boolean | is_default_shipping | True if the address is the default one for shipping |

### Examples

#### Request Example SOAP V1

```php
$client = new SoapClient('https://mahohost/api/soap/?wsdl');
$session = $client->login('apiUser', 'apiKey');

$result = $client->call($session, 'customer_address.list', '2');
var_dump($result);

// When the session can be closed
$client->endSession($session);
```

#### Request Example SOAP V2

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl'); // replace with your store's WSDL URL
$sessionId = $proxy->login('apiUser', 'apiKey'); // replace with your API user and key

$result = $proxy->customerAddressList($sessionId, '2');
var_dump($result);
```

#### Request Example SOAP V2 (WS-I Compliance Mode)

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl'); 
$sessionId = $proxy->login((object)['username' => 'apiUser', 'apiKey' => 'apiKey']); 
 
$result = $proxy->customerAddressList((object)['sessionId' => $sessionId->result, 'customerId' => '2']);   
var_dump($result->result);
```

#### Response Example SOAP V1

```php
array
  0 =>
    array
      'customer_address_id' => string '2' (length=1)
      'created_at' => string '2012-03-29 13:20:08' (length=19)
      'updated_at' => string '2012-03-29 13:39:29' (length=19)
      'city' => string 'Las Vegas' (length=9)
      'country_id' => string 'US' (length=2)
      'firstname' => string 'johny' (length=5)
      'lastname' => string 'doe' (length=3)
      'postcode' => string '89032' (length=5)
      'region' => string 'Nevada' (length=6)
      'region_id' => string '39' (length=2)
      'street' => string '3406 Hiney Road' (length=15)
      'telephone' => string '702-283-9556' (length=12)
      'is_default_billing' => boolean false
      'is_default_shipping' => boolean true
  1 =>
    array
      'customer_address_id' => string '3' (length=1)
      'created_at' => string '2012-03-29 13:39:29' (length=19)
      'updated_at' => string '2012-03-29 13:39:29' (length=19)
      'city' => string 'Corpus Christi' (length=14)
      'country_id' => string 'US' (length=2)
      'firstname' => string 'johny' (length=5)
      'lastname' => string 'doe' (length=3)
      'postcode' => string '78476' (length=5)
      'region' => string 'Texas' (length=5)
      'region_id' => string '57' (length=2)
      'street' => string '3672 Boone Street' (length=17)
      'telephone' => string '361-280-8488' (length=12)
      'vat_id' => string 'GB999 9999 73' (length=13)
      'is_default_billing' => boolean false
      'is_default_shipping' => boolean false
```

## Create

### Method

- `customer_address.create` (SOAP V1)
- `customerAddressCreate` (SOAP V2)

Create a new address for the customer.

### Arguments

| Type   | Name        | Description                          |
|--------|-------------|--------------------------------------|
| string | sessionId   | Session ID                           |
| int    | customerId  | Customer ID                          |
| array  | addressdata | Array of customerAddressEntityCreate |

### Returns

| Type | Name   | Description                        |
|------|--------|------------------------------------|
| int  | result | ID of the created customer address |

### Content `customerAddressEntityCreate`

| Type          | Name                | Description                                         |
|---------------|---------------------|-----------------------------------------------------|
| string        | city                | Name of the city                                    |
| string        | company             | Name of the company                                 |
| string        | country_id          | Country ID                                          |
| string        | fax                 | Fax                                                 |
| string        | firstname           | Customer first name                                 |
| string        | lastname            | Customer last name                                  |
| string        | middlename          | Customer middle name                                |
| string        | postcode            | Postcode                                            |
| string        | prefix              | Customer prefix                                     |
| int           | region_id           | ID of the region                                    |
| string        | region              | Name of the region                                  |
| ArrayOfString | street              | Array of street addresses                           |
| string        | suffix              | Customer suffix                                     |
| string        | telephone           | Telephone number                                    |
| boolean       | is_default_billing  | True if the address is the default one for billing  |
| boolean       | is_default_shipping | True if the address is the default one for shipping |

**Note**: If you want to leave any address fields empty, specify them as empty ones in the request body.

### Examples

#### Request Example SOAP V1

```php
$client = new SoapClient('https://mahohost/api/soap/?wsdl');
$session = $client->login('apiUser', 'apiKey');

$result = $client->call(
    $session,
    'customer_address.create',
    [
        'customerId' => 2,
        'addressdata' => [
            'firstname' => 'John',
            'lastname' => 'Doe',
            'street' => ['Street line 1', 'Street line 2'],
            'city' => 'Weaverville',
            'country_id' => 'US',
            'region' => 'Texas',
            'region_id' => 3,
            'postcode' => '96093',
            'telephone' => '530-623-2513',
            'is_default_billing' => false,
            'is_default_shipping' => false
        ]
    ]
);
var_dump($result);
```

#### Request Example SOAP V2

```php
$client = new SoapClient('https://mahohost/api/v2_soap/?wsdl');
$session = $client->login('apiUser', 'apiKey');

$result = $client->customerAddressCreate(
    $session,
    '2',
    [
        'firstname' => 'John',
        'lastname' => 'Doe',
        'street' => ['Street line 1', 'Street line 2'],
        'city' => 'Weaverville',
        'country_id' => 'US',
        'region' => 'Texas',
        'region_id' => 3,
        'postcode' => '96093',
        'telephone' => '530-623-2513',
        'is_default_billing' => false,
        'is_default_shipping' => false
    ]
);
var_dump($result);
```

#### Request Example SOAP V2 (WS-I Compliance Mode)

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl'); 
$sessionId = $proxy->login((object)['username' => 'apiUser', 'apiKey' => 'apiKey']); 
$result = $proxy->customerAddressCreate(
    (object)[
        'sessionId' => $sessionId->result,
        'customerId' => '2',
        'addressData' => (
            (object)[
                'city' => 'Weaverville',
                'country_id' => 'US',
                'postcode' => '96093',
                'region' => 'Texas',
                'street' => ['Street line 1', 'Street line 2'],
                'telephone' => '847-431-7700',
                'lastname' => 'Doe',
                'firstname' => 'John',
                'is_default_billing' => true
            ]
        )
    ]
);   
var_dump($result->result);
```

## Info

### Method

- `customer_address.info` (SOAP V1)
- `customerAddressInfo` (SOAP V2)

Retrieve information about the required customer address.

### Arguments

| Type   | Name      | Description |
|--------|-----------|-------------|
| string | sessionId | Session ID  |
| int    | addressId | Address ID  |

### Returns

| Type  | Name | Description                        |
|-------|------|------------------------------------|
| array | info | Array of customerAddressEntityItem |

### Content `customerAddressEntityItem`

| Type    | Name                | Description                                         |
|---------|---------------------|-----------------------------------------------------|
| int     | customer_address_id | ID of the customer address                          |
| string  | created_at          | Date when the address was created                   |
| string  | updated_at          | Date when the address was updated                   |
| string  | increment_id        | Increment ID                                        |
| string  | city                | name of the city                                    |
| string  | company             | Name of the company                                 |
| string  | country_id          | ID of the country                                   |
| string  | fax                 | Fax                                                 |
| string  | firstname           | Customer first name                                 |
| string  | lastname            | Customer last name                                  |
| string  | middlename          | Customer middle name                                |
| string  | postcode            | Customer postcode                                   |
| string  | prefix              | Customer prefix                                     |
| string  | region              | Name of the region                                  |
| int     | region_id           | Region ID                                           |
| string  | street              | Name of the street                                  |
| string  | suffix              | Customer suffix                                     |
| string  | telephone           | Telephone number                                    |
| boolean | is_default_billing  | True if the address is the default one for billing  |
| boolean | is_default_shipping | True if the address is the default one for shipping |

### Examples

#### Request Example SOAP V1

```php
$client = new SoapClient('https://mahohost/api/soap/?wsdl');
$session = $client->login('apiUser', 'apiKey');

$result = $client->call($session, 'customer_address.info', '2');
var_dump($result);

// When the session can be closed
$client->endSession($session);
```

#### Request Example SOAP V2

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl'); // replace with your store's WSDL URL
$sessionId = $proxy->login('apiUser', 'apiKey'); // replace with your API user and key

$result = $proxy->customerAddressInfo($sessionId, '2');
var_dump($result);
```

#### Request Example SOAP V2 (WS-I Compliance Mode)

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl'); 
$sessionId = $proxy->login((object)['username' => 'apiUser', 'apiKey' => 'apiKey']); 
 
$result = $proxy->customerAddressInfo(
    (object)['sessionId' => $sessionId->result, 'addressId' => '2']
);   
var_dump($result->result);
```

#### Response Example SOAP V1

```php
array
  'customer_address_id' => string '2' (length=1)
  'created_at' => string '2012-03-29 13:20:08' (length=19)
  'updated_at' => string '2012-03-29 13:20:08' (length=19)
  'increment_id' => null
  'city' => string 'Las Vegas' (length=9)
  'company' => null
  'country_id' => string 'US' (length=2)
  'fax' => null
  'firstname' => string 'johny' (length=5)
  'lastname' => string 'doe' (length=3)
  'middlename' => null
  'postcode' => string '89032' (length=5)
  'prefix' => null
  'region' => string 'Nevada' (length=6)
  'region_id' => string '39' (length=2)
  'street' => string '3406 Hiney Road' (length=15)
  'suffix' => null
  'telephone' => string '702-283-9556' (length=12)
  'vat_id' => null
  'vat_is_valid' => null
  'vat_request_date' => null
  'vat_request_id' => null
  'vat_request_success' => null
  'is_default_billing' => boolean false
  'is_default_shipping' => boolean true
```

## Update

### Method

- `customer_address.update` (SOAP V1)
- `customerAddressUpdate` (SOAP V2)

Update address data of the required customer.

### Arguments

| Type   | Name        | Description                          |
|--------|-------------|--------------------------------------|
| string | sessionId   | Session ID                           |
| int    | addressId   | Address ID                           |
| array  | addressdata | Array of customerAddressEntityCreate |

### Returns

| Type    | Description                             |
|---------|-----------------------------------------|
| boolean | True if the customer address is updated |

### Content `customerAddressEntityCreate`

| Type          | Name                | Description                                         |
|---------------|---------------------|-----------------------------------------------------|
| string        | city                | Name of the city                                    |
| string        | company             | Name of the company                                 |
| string        | country_id          | Country ID                                          |
| string        | fax                 | Fax                                                 |
| string        | firstname           | Customer first name                                 |
| string        | lastname            | Customer last name                                  |
| string        | middlename          | Customer middle name                                |
| string        | postcode            | Postcode                                            |
| string        | prefix              | Customer prefix                                     |
| int           | region_id           | ID of the region                                    |
| string        | region              | Name of the region                                  |
| ArrayOfString | street              | Array of streets                                    |
| string        | suffix              | Customer suffix                                     |
| string        | telephone           | Telephone number                                    |
| boolean       | is_default_billing  | True if the address is the default one for billing  |
| boolean       | is_default_shipping | True if the address is the default one for shipping |

### Examples

#### Request Example SOAP V1

```php
$client = new SoapClient('https://mahohost/api/soap/?wsdl');
$session = $client->login('apiUser', 'apiKey');

$result = $client->call(
    $session,
    'customer_address.update',
    [
        'addressId' => 8,
        'addressdata' => [
            'firstname' => 'John',
            'lastname' => 'Doe',
            'street' => ['Street line 1', 'Street line 2'],
            'city' => 'Weaverville',
            'country_id' => 'US',
            'region' => 'Texas',
            'region_id' => 3,
            'postcode' => '96093',
            'telephone' => '530-623-2513',
            'is_default_billing' => true
            'is_default_shipping' => false
        ]
    ]
);
var_dump($result);
```

#### Request Example SOAP V2

```php
$client = new SoapClient('https://mahohost/api/v2_soap/?wsdl');
$session = $client->login('apiUser', 'apiKey');

$result = $client->customerAddressUpdate(
    $session,
    '8',
    [
        'firstname' => 'John',
        'lastname' => 'Doe',
        'street' => ['Street line 1', 'Street line 2'],
        'city' => 'Weaverville',
        'country_id' => 'US',
        'region' => 'Texas',
        'region_id' => 3,
        'postcode' => '96093',
        'telephone' => '530-623-2513',
        'is_default_billing' => false,
        'is_default_shipping' => false
    ]
);
var_dump($result);
```

#### Request Example SOAP V2 (WS-I Compliance Mode)

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl'); 
$sessionId = $proxy->login((object)['username' => 'apiUser', 'apiKey' => 'apiKey']); 
 
$result = $proxy->customerAddressUpdate(
    (object)[
        'sessionId' => $sessionId->result,
        'addressId' => '8',
        'addressData' => (
            (object)[
                'firstname' => 'John', 
                'lastname' => 'Doe', 
                'street' => ['Street line 1', 'Street line 2'], 
                'city' => 'Weaverville', 
                'country_id' => 'US', 
                'region' => 'Texas', 
                'region_id' => 3, 
                'postcode' => '96093', 
                'telephone' => '530-623-2513', 
                'is_default_billing' => true, 
                'is_default_shipping' => true
            ]
        )
    ]
);   
var_dump($result->result);
```

## Delete

### Method

- `customer_address.delete` (SOAP V1)
- `customerAddressDelete` (SOAP V2)

Delete the required customer address.

### Arguments

| Type   | Name      | Description |
|--------|-----------|-------------|
| string | sessionId | Session ID  |
| int    | addressId | Address ID  |

### Returns

| Type    | Description                             |
|---------|-----------------------------------------|
| boolean | True if the customer address is deleted |

### Examples

#### Request Example SOAP V1

```php
$client = new SoapClient('https://mahohost/api/soap/?wsdl');
$session = $client->login('apiUser', 'apiKey');

$result = $client->call($session, 'customer_address.delete', '4');
var_dump($result);
```

#### Request Example SOAP V2

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl'); // replace with your store's WSDL URL
$sessionId = $proxy->login('apiUser', 'apiKey'); // replace with your API user and key

$result = $proxy->customerAddressDelete($sessionId, '4');
var_dump($result);
```

#### Request Example SOAP V2 (WS-I Compliance Mode)

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl'); 
$sessionId = $proxy->login((object)['username' => 'apiUser', 'apiKey' => 'apiKey']); 
 
$result = $proxy->customerAddressDelete((object)['sessionId' => $sessionId->result, 'addressId' => '4']);   
var_dump($result->result);
```
