---
description: Manage order shipments over the Maho SOAP API - list, info, create, comment, and handle tracking numbers.
---

# Sales Order Shipment

!!! warning "Legacy API"
    This is Maho's legacy SOAP/XML-RPC API, inherited from Magento 1 and kept for backward compatibility. New integrations should use the modern [REST & GraphQL API (v2)](../v2/index.md) instead.

## Introduction

Allows you to manage shipments and tracking numbers.

### Methods

- `sales_order_shipment.list` - Retrieve the list of shipments using filters.
- `sales_order_shipment.info` - Retrieve information about the shipment.
- `sales_order_shipment.create` - Create a new shipment for an order.
- `sales_order_shipment.addComment` - Add a new comment to a shipment.
- `sales_order_shipment.addTrack` - Add a new tracking number to a shipment.
- `sales_order_shipment.removeTrack` - Remove tracking number from a shipment.
- `sales_order_shipment.getCarriers` - Retrieve the list of allowed carriers for an order.

### Faults

| Fault Code | Fault Message                                    |
|------------|--------------------------------------------------|
| 100        | Requested shipment not exists.                   |
| 101        | Invalid filters given. Details in error message. |
| 102        | Invalid data given. Details in error message.    |
| 103        | Requested order not exists.                      |
| 104        | Requested tracking not exists.                   |
| 105        | Tracking not deleted. Details in error message.  |

### Example - Working With Shipments

```php
$proxy = new SoapClient('https://mahohost/api/soap/?wsdl');
$sessionId = $proxy->login('apiUser', 'apiKey');

$notShippedOrderId = '100000003';

// Create new shipment
$newShipmentId = $proxy->call($sessionId, 'sales_order_shipment.create', [$notShippedOrderId, [], 'Shipment Created', true, true]);

// View new shipment
$shipment = $proxy->call($sessionId, 'sales_order_shipment.info', $newShipmentId);

var_dump($shipment);

// Get allowed carriers for shipping
$allowedCarriers = $proxy->call($sessionId, 'sales_order_shipment.getCarriers', $notShippedOrderId);

end($allowedCarriers);

$chosenCarrier = key($allowedCarriers);

var_dump($allowedCarriers);
var_dump($chosenCarrier);

// Add tracking
$newTrackId = $proxy->call($sessionId, 'sales_order_shipment.addTrack', [$newShipmentId, $chosenCarrier, 'My Track', rand(5000, 9000)]);

$shipment = $proxy->call($sessionId, 'sales_order_shipment.info', $newShipmentId);

var_dump($shipment);
```

## List

### Method

- `sales_order_shipment.list` (SOAP V1)
- `salesOrderShipmentList` (SOAP V2)

Allows you to retrieve the list of order shipments. Additional filters can be applied.

### Alias

- `order_shipment.list`

### Arguments

| Type   | Name      | Description                                |
|--------|-----------|--------------------------------------------|
| string | sessionId | Session ID                                 |
| array  | filters   | Array of filters for the list of shipments |

### Returns

| Type  | Name   | Description                       |
|-------|--------|-----------------------------------|
| array | result | Array of salesOrderShipmentEntity |

### Content `salesOrderShipmentEntity`

| Type   | Name         | Description                     |
|--------|--------------|---------------------------------|
| string | increment_id | Increment ID                    |
| string | created_at   | Date of shipment creation       |
| string | total_qty    | Total quantity of items to ship |
| string | shipment_id  | Shipment ID                     |

### Examples

#### Request Example SOAP V1

```php
$client = new SoapClient('https://mahohost/api/soap/?wsdl');
$session = $client->login('apiUser', 'apiKey');

$result = $client->call($session, 'sales_order_shipment.list');
var_dump($result);

// When the session can be closed
$client->endSession($session);
```

#### Request Example SOAP V2 (List of All Shipments)

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl'); // replace with your store's WSDL URL
$sessionId = $proxy->login('apiUser', 'apiKey'); // replace with your API user and key

$result = $proxy->salesOrderShipmentList($sessionId);
var_dump($result);
```

#### Request Example SOAP V2 (Complex Filter)

```php
$client = new SoapClient('https://mahohost/api/v2_soap/?wsdl');
$session = $client->login('apiUser', 'apiKey');

$complexFilter = [
    'complex_filter' => [
        [
            'key' => 'created_at',
            'value' => ['key' => 'in', 'value' => '2012-03-30 12:54:46']
        ]
    ]
];
$result = $client->salesOrderShipmentList($session, $complexFilter);
var_dump($result);
```

#### Request Example SOAP V2 (WS-I Compliance Mode)

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl'); 
$sessionId = $proxy->login((object)['username' => 'apiUser', 'apiKey' => 'apiKey']); 
 
$result = $proxy->salesOrderShipmentList((object)['sessionId' => $sessionId->result]);   
var_dump($result->result);
```

#### Response Example SOAP V1

```php
array
  0 =>
    array
      'increment_id' => string '200000001' (length=9)
      'created_at' => string '2012-03-30 12:35:29' (length=19)
      'total_qty' => string '2.0000' (length=6)
      'shipment_id' => null
  1 =>
    array
      'increment_id' => string '200000002' (length=9)
      'created_at' => string '2012-03-30 12:54:46' (length=19)
      'total_qty' => string '1.0000' (length=6)
      'shipment_id' => null
```

## Info

### Method

- `sales_order_shipment.info` (SOAP V1)
- `salesOrderShipmentInfo` (SOAP V2)

Allows you to retrieve the shipment information.

### Alias

- `order_shipment.info`

### Arguments

| Type   | Name                | Description                 |
|--------|---------------------|-----------------------------|
| string | sessionId           | Session ID                  |
| string | shipmentIncrementId | Order shipment increment ID |

### Returns

| Type   | Name   | Description                       |
|--------|--------|-----------------------------------|
| array  | result | Array of salesOrderShipmentEntity |

### Content `salesOrderShipmentEntity`

| Type   | Name                | Description                              |
|--------|---------------------|------------------------------------------|
| string | increment_id        | Shipment increment ID                    |
| string | store_id            | Store ID                                 |
| string | created_at          | Date of shipment creation                |
| string | updated_at          | Date of shipment updating                |
| string | shipping_address_id | Shipping address ID                      |
| string | order_id            | Order ID                                 |
| string | total_qty           | Total quantity of items to ship          |
| string | shipment_id         | Shipment ID                              |
| array  | items               | Array of salesOrderShipmentItemEntity    |
| array  | tracks              | Array of salesOrderShipmentTrackEntity   |
| array  | comments            | Array of salesOrderShipmentCommentEntity |

### Content `salesOrderShipmentItemEntity`

| Type   | Name          | Description        |
|--------|---------------|--------------------|
| string | parent_id     | Parent ID          |
| string | sku           | Shipment item SKU  |
| string | name          | Shipment item name |
| string | order_item_id | Order item ID      |
| string | product_id    | Product ID         |
| string | weight        | Weight             |
| string | price         | Price              |
| string | qty           | Quantity of items  |
| string | item_id       | Item ID            |

### Content `salesOrderShipmentTrackEntity`

| Type   | Name         | Description                      |
|--------|--------------|----------------------------------|
| string | parent_id    | Parent ID                        |
| string | created_at   | Date of tracking number creation |
| string | updated_at   | Date of tracking number updating |
| string | carrier_code | Carrier code                     |
| string | title        | Track title                      |
| string | number       | Tracking number                  |
| string | order_id     | Order ID                         |
| string | track_id     | Track ID                         |

### Content `salesOrderShipmentCommentEntity`

| Type   | Name                 | Description                              |
|--------|----------------------|------------------------------------------|
| string | parent_id            | Parent ID                                |
| string | created_at           | Date of comment creation                 |
| string | comment              | Shipment comment text                    |
| string | is_customer_notified | Defines whether the customer is notified |
| string | comment_id           | Comment ID                               |

### Examples

#### Request Example SOAP V1

```php
$client = new SoapClient('https://mahohost/api/soap/?wsdl');
$session = $client->login('apiUser', 'apiKey');

$result = $client->call($session, 'sales_order_shipment.info', '200000003');
var_dump($result);
```

#### Request Example SOAP V2

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl'); // replace with your store's WSDL URL
$sessionId = $proxy->login('apiUser', 'apiKey'); // replace with your API user and key

$result = $proxy->salesOrderShipmentInfo($sessionId, '200000003');
var_dump($result);
```

#### Request Example SOAP V2 (WS-I Compliance Mode)

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl'); 
$sessionId = $proxy->login((object)['username' => 'apiUser', 'apiKey' => 'apiKey']); 
 
$result = $proxy->salesOrderShipmentInfo((object)['sessionId' => $sessionId->result, 'shipmentIncrementId' => '200000003']);   
var_dump($result->result);
```

## Create

### Method

- `sales_order_shipment.create` (SOAP V1)
- `salesOrderShipmentCreate` (SOAP V2)

Allows you to create a new shipment for an order.

### Alias

- `order_shipment.create`

### Arguments

| Type   | Name             | Description                              |
|--------|------------------|------------------------------------------|
| string | sessionId        | Session ID                               |
| string | orderIncrementId | Order increment ID                       |
| array  | itemsQty         | Array of orderItemIdQty (optional)       |
| string | comment          | Shipment comment (optional)              |
| int    | email            | Send email flag (optional)               |
| int    | includeComment   | Include comment in email flag (optional) |

### Returns

| Type   | Name                | Description           |
|--------|---------------------|-----------------------|
| string | shipmentIncrementId | Shipment increment ID |

### Content `orderItemIdQty`

| Type   | Name          | Description                     |
|--------|---------------|---------------------------------|
| int    | order_item_id | Order item ID                   |
| double | qty           | Quantity of items to be shipped |

**Notes**: The array of `orderItemQty` is used for partial shipment.
To create shipment for all order items, you do not need to specify these attributes.

### Examples

#### Request Example SOAP V1

```php
$proxy = new SoapClient('https://mahohost/api/soap/?wsdl');
$session = $proxy->login('apiUser', 'apiKey');

$orderIncrementId = '200000006';
$orderItemId = 3;
$qty = 5;
$itemsQty = [
	$orderItemId => $qty,
];

$result = $proxy->call(
    $session,
    'order_shipment.create',
    [
        $orderIncrementId,
        $itemsQty
    ]
);

var_dump($result);
```

#### Request Example SOAP V2

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl');
$sessionId = $proxy->login('apiUser', 'apiKey');

$itemsQty = [
    [
        'order_item_id' => 3,
        'qty' => 3
    ],
    [
        'order_item_id' => 4,
        'qty' => 5
    ]
];

$result = $proxy->salesOrderShipmentCreate($sessionId, '200000006', $itemsQty, 'shipment comment');
var_dump($result);
```

#### Request Example SOAP V2 (WS-I Compliance Mode)

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl'); 
$sessionId = $proxy->login((object)['username' => 'apiUser', 'apiKey' => 'apiKey']); 

$itemsQty = [
    [
        'order_item_id' => 3,
        'qty' => 3
    ],
    [
        'order_item_id' => 4,
        'qty' => 5
    ]
];
 
$result = $proxy->salesOrderShipmentCreate(
    (object)[
        'sessionId' => $sessionId->result,
        'orderIncrementId' => '200000006',
        'itemsQty' => $itemsQty,
        'comment' => 'shipment comment',
        'email' => null,
        'includeComment' => null
    ]
);   
    
var_dump($result->result);
```

## AddComment

### Method

- `sales_order_shipment.addComment` (SOAP V1)
- `salesOrderShipmentAddComment` (SOAP V2)

Allows you to add a new comment to the order shipment.

### Alias

- `order_shipment.addComment`

### Arguments

| Type   | Name                | Description                              |
|--------|---------------------|------------------------------------------|
| string | sessionId           | Session ID                               |
| string | shipmentIncrementId | Shipment increment ID                    |
| string | comment             | Shipment comment (optional)              |
| string | email               | Send email flag (optional)               |
| string | includeInEmail      | Include comment in email flag (optional) |

### Returns

| Type       | Description                                            |
|------------|--------------------------------------------------------|
| booleanint | True (1) if the comment is added to the order shipment |

### Examples

#### Request Example SOAP V1

```php
$client = new SoapClient('https://mahohost/api/soap/?wsdl');
$session = $client->login('apiUser', 'apiKey');

$result = $client->call($session, 'sales_order_shipment.addComment', ['shipmentIncrementId' => '200000002', 'comment' => 'comment for the shipment', 'email' => null]);
var_dump($result);

// When the session can be closed
$client->endSession($session);
```

#### Request Example SOAP V2

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl'); // replace with your store's WSDL URL
$sessionId = $proxy->login('apiUser', 'apiKey'); // replace with your API user and key

$result = $proxy->salesOrderShipmentAddComment($sessionId, '200000002');
var_dump($result);
```

#### Request Example SOAP V2 (WS- I Compliance Mode)

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl'); 
$sessionId = $proxy->login((object)['username' => 'apiUser', 'apiKey' => 'apiKey']); 
 
$result = $proxy->salesOrderShipmentAddComment((object)['sessionId' => $sessionId->result, 'shipmentIncrementId' => '200000002', 'comment' => 'comment for the shipment', 'email' => null, 'includeInEmail' => null]);   
var_dump($result->result);
```

## AddTrack

### Method

- `sales_order_shipment.addTrack` (SOAP V1)
- `salesOrderShipmentAddTrack` (SOAP V2)

Allows you to add a new tracking number to the order shipment.

### Aliases

- `order_shipment.addTrack`

### Arguments

| Type   | Name                | Description                                     |
|--------|---------------------|-------------------------------------------------|
| string | sessionId           | Session ID                                      |
| string | shipmentIncrementId | Shipment increment ID                           |
| string | carrier             | Carrier code (ups, usps, dhl, fedex, or dhlint) |
| string | title               | Tracking title                                  |
| string | trackNumber         | Tracking number                                 |

### Returns

| Type | Description        |
|------|--------------------|
| int  | Tracking number ID |

### Examples

#### Request Example SOAP V1

```php
$client = new SoapClient('https://mahohost/api/soap/?wsdl');
$session = $client->login('apiUser', 'apiKey');

$result = $client->call($session, 'sales_order_shipment.addTrack', ['shipmentIncrementId' => '200000002', 'carrier' => 'ups', 'title' => 'tracking title', 'trackNumber' => '123123']);
var_dump($result);

// When the session can be closed
$client->endSession($session);
```

#### Request Example SOAP V2

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl'); // replace with your store's WSDL URL
$sessionId = $proxy->login('apiUser', 'apiKey'); // replace with your API user and key

$result = $proxy->salesOrderShipmentAddTrack($sessionId, '200000002', 'ups', 'tracking title', '123123');
var_dump($result);
```

#### Request Example SOAP V2 (WS-I Compliance Mode)

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl'); 
$sessionId = $proxy->login((object)['username' => 'apiUser', 'apiKey' => 'apiKey']); 
 
$result = $proxy->salesOrderShipmentAddTrack((object)['sessionId' => $sessionId->result, 'shipmentIncrementId' => '200000002', 'carrier' => 'ups', 'title' => 'tracking title', 'trackNumber' => '123123']);   
var_dump($result->result);
```

## RemoveTrack

### Method

- `sales_order_shipment.removeTrack` (SOAP V1)
- `salesOrderShipmentRemoveTrack` (SOAP V2)

Allows you to remove a tracking number from the order shipment.

### Alias

- `order_shipment.removeTrack`

### Arguments

| Type   | Name                | Description           |
|--------|---------------------|-----------------------|
| string | sessionId           | Session ID            |
| string | shipmentIncrementId | Shipment increment ID |
| string | trackId             | Track ID              |

### Returns

| Type       | Description                                                  |
|------------|--------------------------------------------------------------|
| booleanint | True (1) if the tracking number is removed from the shipment |

### Examples

#### Request Example SOAP V1

```php
$client = new SoapClient('https://mahohost/api/soap/?wsdl');
$session = $client->login('apiUser', 'apiKey');

$result = $client->call($session, 'sales_order_shipment.removeTrack', ['shipmentIncrementId' => '200000002', 'trackId' => '2']);
var_dump($result);

// When the session can be closed
$client->endSession($session);
```

#### Request Example SOAP V2

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl'); // replace with your store's WSDL URL
$sessionId = $proxy->login('apiUser', 'apiKey'); // replace with your API user and key

$result = $proxy->salesOrderShipmentRemoveTrack($sessionId, '200000002', '2');
var_dump($result);
```

#### Request Example SOAP V2 (WS-I Compliance Mode)

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl'); 
$sessionId = $proxy->login((object)['username' => 'apiUser', 'apiKey' => 'apiKey']); 
 
$result = $proxy->salesOrderShipmentRemoveTrack((object)['sessionId' => $sessionId->result, 'shipmentIncrementId' => '200000002', 'trackId' => '2']);   
var_dump($result->result);
```

## GetCarriers

### Method

- `sales_order_shipment.getCarriers` (SOAP V1)
- `salesOrderShipmentGetCarriers` (SOAP V2)

Allows you to retrieve the list of allowed carriers for an order.

### Alias

- `order_shipment.getCarriers`

### Arguments

| Type   | Name             | Description        |
|--------|------------------|--------------------|
| string | sessionId        | Session ID         |
| string | orderIncrementId | Order increment ID |

### Returns

| Type             | Name   | Description       |
|------------------|--------|-------------------|
| associativeArray | result | Array of carriers |

### Examples

#### Request Example SOAP V1

```php
$client = new SoapClient('https://mahohost/api/soap/?wsdl');
$session = $client->login('apiUser', 'apiKey');

$result = $client->call($session, 'sales_order_shipment.getCarriers', '200000010');
var_dump($result);

// When the session can be closed
$client->endSession($session);
```

#### Request Example SOAP V2

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl'); // replace with your store's WSDL URL
$sessionId = $proxy->login('apiUser', 'apiKey'); // replace with your API user and key

$result = $proxy->salesOrderShipmentGetCarriers($sessionId, '200000010');
var_dump($result);
```

#### Request Example SOAP V2 (WS-I Compliance Mode)

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl'); 
$sessionId = $proxy->login((object)['username' => 'apiUser', 'apiKey' => 'apiKey']); 
 
$result = $proxy->salesOrderShipmentGetCarriers(
    (object)['sessionId' => $sessionId->result, 'orderIncrementId' => '200000010']
);   
var_dump($result->result);
```

#### Response Example SOAP V1

```php
array
  'custom' => string 'Custom Value' (length=12)
  'dhl' => string 'DHL (Deprecated)' (length=16)
  'fedex' => string 'Federal Express' (length=15)
  'ups' => string 'United Parcel Service' (length=21)
  'usps' => string 'United States Postal Service' (length=28)
  'dhlint' => string 'DHL' (length=3)
```
