---
description: Create, retrieve, update, and delete customers over the Maho SOAP API.
---

# Customer

!!! warning "Legacy API"
    This is Maho's legacy SOAP/XML-RPC API, inherited from Magento 1 and kept for backward compatibility. New integrations should use the modern [REST & GraphQL API (v2)](../v2/index.md) instead.

## Introduction

Allows you to create, retrieve, update, and delete data about customers.

### Methods

- `customer.list` - Retrieve the list of customers.
- `customer.create` - Create a new customer.
- `customer.info` - Retrieve the customer data.
- `customer.update` - Update the customer data.
- `customer.delete` - Delete a required customer.

### Faults

| Fault Code | Fault Message                                        |
|------------|------------------------------------------------------|
| 100        | Invalid customer data. Details in error message.     |
| 101        | Invalid filters specified. Details in error message. |
| 102        | Customer does not exist.                             |
| 103        | Customer not deleted. Details in error message.      |

### Examples - View, Create, Update and Delete a Customer

```php
$proxy = new SoapClient('https://mahohost/api/soap/?wsdl');

$newCustomer = [
    'firstname' => 'First',
    'lastname' => 'Last',
    'email' => 'test@example.com',
    'password_hash' => md5('password'),
    // The password hash can be either regular or salted md5:
    // $hash = md5($password);
    // $hash = md5($salt . $password) . ':' . $salt;
    // Both variants are valid.
    'store_id'   => 0,
    'website_id' => 0
];

$newCustomerId = $proxy->call($sessionId, 'customer.create', [$newCustomer]);

// Get new customer info
mp($proxy->call($sessionId, 'customer.info', $newCustomerId));

// Update customer
$update = ['firstname' => 'Changed Firstname'];
$proxy->call($sessionId, 'customer.update', [$newCustomerId, $update]);

mp($proxy->call($sessionId, 'customer.info', $newCustomerId));

// Delete customer
$proxy->call($sessionId, 'customer.delete', $newCustomerId);
```

## List

### Method

- `customer.list` (SOAP V1)
- `customerCustomerList` (SOAP V2)

Allows you to retrieve the list of customers.

### Arguments

| Type   | Name      | Description                                        |
|--------|-----------|----------------------------------------------------|
| string | sessionId | Session ID                                         |
| array  | filters   | Array of filters by customer attributes (optional) |

### Returns

| Type  | Name      | Description                     |
|-------|-----------|---------------------------------|
| array | storeView | Array of customerCustomerEntity |

### Content `customerCustomerEntity`

| Type    | Name          | Description                           |
|---------|---------------|---------------------------------------|
| int     | customer_id   | ID of the customer                    |
| string  | created_at    | Date when the customer was created    |
| string  | updated_at    | Date of when the customer was updated |
| string  | increment_id  | Increment ID                          |
| int     | store_id      | Store ID                              |
| int     | website_id    | Website ID                            |
| string  | created_in    | Created in                            |
| string  | email         | Customer email                        |
| string  | firstname     | Customer first name                   |
| string  | middlename    | Customer middle name                  |
| string  | lastname      | Customer last name                    |
| int     | group_id      | Group ID                              |
| string  | prefix        | Customer prefix                       |
| string  | suffix        | Customer suffix                       |
| string  | dob           | Customer date of birth                |
| string  | taxvat        | Taxvat value                          |
| boolean | confirmation  | Confirmation flag                     |
| string  | password_hash | Password hash                         |

**Note**: The `password_hash` parameter will only match exactly with the same MD5 and salt
as was used when Maho stored the value.
If you try to match with an unsalted MD5 hash or any salt other than what Maho used, it will not match.
This is just a straight string comparison.

### Examples

#### Request Example SOAP V1

```php
$client = new SoapClient('https://mahohost/api/soap/?wsdl');
$session = $client->login('apiUser', 'apiKey');

$result = $client->call($session, 'customer.list');
mp($result);

// When the session can be closed
$client->endSession($session);
```

#### Request Example SOAP V2 (List of All Customers)

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl'); // replace with your store's WSDL URL
$sessionId = $proxy->login('apiUser', 'apiKey'); // replace with your API user and key

$result = $proxy->customerCustomerList($sessionId);
mp($result);
```

#### Request Example SOAP V2 (Complex Filter)

```php
$client = new SoapClient('https://mahohost/api/v2_soap/?wsdl');
$session = $client->login('apiUser', 'apiKey');

$complexFilter = [
    'complex_filter' => [
        [
            'key' => 'group_id',
            'value' => ['key' => 'in', 'value' => '1,3']
        ]
    ]
];
$result = $client->customerCustomerList($session, $complexFilter);
mp($result);
```

#### Request Example SOAP V2 (WS-I Compliance Mode)

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl');
$sessionId = $proxy->login((object)['username' => 'apiUser', 'apiKey' => 'apiKey']);

$result = $proxy->customerCustomerList((object)['sessionId' => $sessionId->result, 'filters' => null]);
mp($result->result);
```

#### Response Example SOAP V1

```php
array
  0 =>
    array
      'customer_id' => string '2' (length=1)
      'created_at' => string '2012-03-29 12:37:23' (length=19)
      'updated_at' => string '2012-04-03 11:20:18' (length=19)
      'store_id' => string '2' (length=1)
      'website_id' => string '2' (length=1)
      'created_in' => string 'English' (length=7)
      'default_billing' => string '3' (length=1)
      'default_shipping' => string '3' (length=1)
      'disable_auto_group_change' => string '0' (length=1)
      'email' => string 'test@example.com' (length=16)
      'firstname' => string 'John' (length=4)
      'group_id' => string '1' (length=1)
      'lastname' => string 'Doe' (length=3)
      'password_hash' => string 'cccfb3ecf54c9644a34106783148eff2:sp' (length=35)
      'rp_token' => string '15433dd072f1f4e5aae83231b93f72d0' (length=32)
      'rp_token_created_at' => string '2012-03-30 15:10:31' (length=19)
  1 =>
    array
      'customer_id' => string '4' (length=1)
      'created_at' => string '2012-04-03 11:21:15' (length=19)
      'updated_at' => string '2012-04-03 11:22:57' (length=19)
      'store_id' => string '0' (length=1)
      'website_id' => string '2' (length=1)
      'created_in' => string 'Admin' (length=5)
      'default_billing' => string '8' (length=1)
      'default_shipping' => string '8' (length=1)
      'disable_auto_group_change' => string '0' (length=1)
      'email' => string 'shon@example.com' (length=16)
      'firstname' => string 'Shon' (length=4)
      'group_id' => string '1' (length=1)
      'lastname' => string 'McMiland' (length=8)
      'password_hash' => string '5670581cabba4e2189e5edee99ed0c86:5q' (length=35)
```

## Create

### Method

- `customer.create` (SOAP V1)
- `customerCustomerCreate` (SOAP V2)

Create a new customer.

### Arguments

| Type   | Name         | Description                             |
|--------|--------------|-----------------------------------------|
| string | sessionId    | Session ID                              |
| array  | customerData | Array of customerCustomerEntityToCreate |

### Returns

| Type | Name   | Description                |
|------|--------|----------------------------|
| int  | result | ID of the created customer |

### Content `customerCustomerEntityToCreate`

| Type   | Name       | Description                                      |
|--------|------------|--------------------------------------------------|
| string | email      | Customer email                                   |
| string | firstname  | Customer first name                              |
| string | lastname   | Customer last name                               |
| string | password   | Customer password                                |
| int    | website_id | Website ID                                       |
| int    | store_id   | Store ID                                         |
| int    | group_id   | Group ID                                         |
| string | prefix     | Customer prefix (optional)                       |
| string | suffix     | Customer suffix (optional)                       |
| string | dob        | Customer date of birth (optional)                |
| string | taxvat     | Customer tax/VAT number (optional)               |
| int    | gender     | Customer gender: 1 - Male, 2 - Female (optional) |
| string | middlename | Customer middle name/initial (optional)          |

### Examples

#### Request Example SOAP V1

```php
$client = new SoapClient('https://mahohost/api/soap/?wsdl');
$session = $client->login('apiUser', 'apiKey');

$result = $client->call(
    $session,
    'customer.create',
    [
        [
            'email' => 'mail@example.org',
            'firstname' => 'Dough',
            'lastname' => 'Deeks',
            'password' => 'password',
            'website_id' => 1,
            'store_id' => 1,
            'group_id' => 1
        ]
    ]
);
mp($result);

// When the session can be closed
$client->endSession($session);
```

#### Request Example SOAP V2

```php
$client = new SoapClient('https://mahohost/api/v2_soap/?wsdl');
$session = $client->login('apiUser', 'apiKey');

$result = $client->customerCustomerCreate(
    $session,
    [
        'email' => 'customer-mail@example.org',
        'firstname' => 'Dough',
        'lastname' => 'Deeks',
        'password' => 'password',
        'website_id' => 1,
        'store_id' => 1,
        'group_id' => 1
    ]
);
mp($result);
```

#### Request Example SOAP V2 (WS-I Compliance Mode)

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl'); 
$sessionId = $proxy->login((object)['username' => 'apiUser', 'apiKey' => 'apiKey']); 
 
$result = $proxy->customerCustomerCreate(
    (object)[
        'sessionId' => $sessionId->result,
        'customerData' => (object)[
            'email' => 'customer-mail@example.org',
            'firstname' => 'John',
            'lastname' => 'Dou',
            'password' => '123123',
            'website_id' => '0',
            'group_id' => '1'
        ]
    ]
);   
mp($result->result);
```

## Info

### Method

- `customer.info` (SOAP V1)
- `customerCustomerInfo` (SOAP V2)

Retrieve information about the specified customer.

### Arguments

| Type          | Name       | Description                 |
|---------------|------------|-----------------------------|
| string        | sessionId  | Session ID                  |
| int           | customerId | ID of the required customer |
| ArrayOfString | attributes | Array of attributes         |

**Note:** Only specified attributes will be returned.
The `customer_id` value is always returned.

### Returns

| Type  | Name         | Description                     |
|-------|--------------|---------------------------------|
| array | customerInfo | Array of customerCustomerEntity |

### Content `customerCustomerEntity`

| Type    | Name                | Description                            |
|---------|---------------------|----------------------------------------|
| int     | customer_id         | ID of the customer                     |
| string  | created_at          | Date when the customer was created     |
| string  | updated_at          | Date when the customer was updated     |
| string  | increment_id        | Increment ID                           |
| int     | store_id            | Store ID                               |
| int     | website_id          | Website ID                             |
| string  | created_in          | Store view the customer was created in |
| string  | email               | Customer email                         |
| string  | firstname           | Customer first name                    |
| string  | middlename          | Customer middle name                   |
| string  | lastname            | Customer last name                     |
| int     | group_id            | Customer group ID                      |
| string  | prefix              | Customer prefix                        |
| string  | suffix              | Customer suffix                        |
| string  | dob                 | Customer date of birth                 |
| string  | taxvat              | Tax/VAT number                         |
| boolean | confirmation        | Confirmation flag                      |
| string  | password_hash       | Password hash                          |
| string  | rp_token            | Reset password token                   |
| string  | rp_token_created_at | Date when the password was reset       |

### Examples

#### Request Example SOAP V1

```php
$client = new SoapClient('https://mahohost/api/soap/?wsdl');
$session = $client->login('apiUser', 'apiKey');

$result = $client->call($session, 'customer.info', '2');
var_dump($result);

// When the session can be closed
$client->endSession($session);
```

#### Request Example SOAP V2

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl'); // replace with your store's WSDL URL
$sessionId = $proxy->login('apiUser', 'apiKey'); // replace with your API user and key

$result = $proxy->customerCustomerInfo($sessionId, '2');
var_dump($result);
```

#### Request Example SOAP V2 (WS-I Compliance Mode)

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl');
$sessionId = $proxy->login((object)['username' => 'apiUser', 'apiKey' => 'apiKey']);

$result = $proxy->customerCustomerInfo((object)['sessionId' => $sessionId->result, 'customerId' => '2']);
var_dump($result->result);
```

#### Response Example SOAP V1

```php
array
  'customer_id' => string '2' (length=1)
  'created_at' => string '2012-03-29 12:37:23' (length=19)
  'updated_at' => string '2012-03-30 12:59:21' (length=19)
  'increment_id' => null
  'store_id' => string '2' (length=1)
  'website_id' => string '2' (length=1)
  'confirmation' => null
  'created_in' => string 'English' (length=7)
  'default_billing' => null
  'default_shipping' => string '2' (length=1)
  'disable_auto_group_change' => string '0' (length=1)
  'dob' => null
  'email' => string 'john@example.com' (length=16)
  'firstname' => string 'johny' (length=5)
  'gender' => null
  'group_id' => string '1' (length=1)
  'lastname' => string 'doe' (length=3)
  'middlename' => null
  'password_hash' => string 'cccfb3ecf54c9644a34106783148eff2:sp' (length=35)
  'prefix' => null
  'rp_token' => string '15433dd072f1f4e5aae83231b93f72d0' (length=32)
  'rp_token_created_at' => string '2012-03-30 15:10:31' (length=19)
  'suffix' => null
  'taxvat' => null
```

## Update

### Method

- `customer.update` (SOAP V1)
- `customerCustomerUpdate` (SOAP V2)

Update information about the required customer.
Note that you need to pass only those arguments which you want to be updated.

### Arguments

| Type   | Name         | Description                             |
|--------|--------------|-----------------------------------------|
| string | sessionId    | Session ID                              |
| int    | customerId   | Customer ID                             |
| array  | customerData | Array of customerCustomerEntityToCreate |

### Returns

| Type    | Description                     |
|---------|---------------------------------|
| boolean | True if the customer is updated |

### Content `customerCustomerEntityToCreate`

| Type   | Name        | Description                           |
|--------|-------------|---------------------------------------|
| int    | customer_id | Customer ID                           |
| string | email       | Customer email                        |
| string | firstname   | Customer first name                   |
| string | lastname    | Customer last name                    |
| string | password    | Customer password                     |
| int    | group_id    | Group ID                              |
| string | prefix      | Customer prefix                       |
| string | suffix      | Customer suffix                       |
| string | dob         | Customer date of birth                |
| string | taxvat      | Customer tax/VAT number               |
| int    | gender      | Customer gender: 1 - Male, 2 - Female |
| string | middlename  | Customer middle name/initial          |

### Examples

#### Request Example SOAP V1

```php
$client = new SoapClient('https://mahohost/api/soap/?wsdl');
$session = $client->login('apiUser', 'apiKey');

$result = $client->call(
    $session,
    'customer.update',
    [
        'customerId' => '2',
        'customerData' => [
            'firstname' => 'John',
            'lastname' => 'Doe',
            'email' => 'test@example.com',
            'password' => 'john22'
        ]
    ]
);
var_dump($result);

// When the session can be closed
$client->endSession($session);
```

#### Request Example SOAP V2

```php
$client = new SoapClient('https://mahohost/api/v2_soap/?wsdl');
$session = $client->login('apiUser', 'apiKey');

$result = $client->customerCustomerUpdate(
    $session,
    '2',
    [
        'email' => 'customer-mail@example.org',
        'firstname' => 'Dough',
        'lastname' => 'Deekson',
        'password' => 'password',
        'website_id' => 1,
        'store_id' => 1,
        'group_id' => 1
    ]
);
var_dump($result);
```

#### Request Example SOAP V2 (WS-I Compliance Mode)

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl'); 
$sessionId = $proxy->login((object)['username' => 'apiUser', 'apiKey' => 'apiKey']); 
 
$result = $proxy->customerCustomerUpdate(
    (object)[
        'sessionId' => $sessionId->result,
        'customerId' => '2',
        'customerData' => (object)[
            'email' => 'customer-mail@example.org',
            'firstname' => 'Dough',
            'lastname' => 'Deekson'
        ]
    ]
);   
var_dump($result->result);
```

## Delete

### Method

- `customer.delete` (SOAP V1)
- `customerCustomerDelete` (SOAP V2)

Delete the required customer.

### Arguments

| Type   | Name       | Description |
|--------|------------|-------------|
| string | sessionId  | Session ID  |
| int    | customerId | Customer ID |

### Returns

| Type    | Description                     |
|---------|---------------------------------|
| boolean | True if the customer is deleted |

### Examples

#### Request Example SOAP V1

```php
$client = new SoapClient('https://mahohost/api/soap/?wsdl');
$session = $client->login('apiUser', 'apiKey');

$result = $client->call($session, 'customer.delete', '2');
var_dump($result);

// When the session can be closed
$client->endSession($session);
```

#### Request Example SOAP V2

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl'); // replace with your store's WSDL URL
$sessionId = $proxy->login('apiUser', 'apiKey'); // replace with your API user and key

$result = $proxy->customerCustomerDelete($sessionId, '2');
var_dump($result);
```

#### Request Example SOAP V2 (WS-I Compliance Mode)

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl'); 
$sessionId = $proxy->login((object)['username' => 'apiUser', 'apiKey' => 'apiKey']); 
 
$result = $proxy->customerCustomerDelete((object)['sessionId' => $sessionId->result, 'customerId' => '2']);   
var_dump($result->result);
```
