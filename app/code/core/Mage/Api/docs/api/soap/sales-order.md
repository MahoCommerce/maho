---
description: Manage sales orders over the Maho SOAP API - list, retrieve info, add comments, hold, unhold, and cancel orders.
---

# Sales Order

!!! warning "Legacy API"
    This is Maho's legacy SOAP/XML-RPC API, inherited from Magento 1 and kept for backward compatibility. New integrations should use the modern [REST & GraphQL API (v2)](../v2/index.md) instead.

## Introduction

Allows you to manage orders.

### Resource Name

- `sales_order`

### Alias

- `order`

### Methods

- `sales_order.list` - Retrieve the list of orders using filters.
- `sales_order.info` - Retrieve the order information.
- `sales_order.addComment` - Add a comment to an order.
- `sales_order.hold` - Hold an order.
- `sales_order.unhold` - Unhold an order.
- `sales_order.cancel` - Cancel an order.

### Faults

| Fault Code | Fault Message                                       |
|------------|-----------------------------------------------------|
| 100        | Requested order not exists.                         |
| 101        | Invalid filters given. Details in error message.    |
| 102        | Invalid data given. Details in error message.       |
| 103        | Order status not changed. Details in error message. |

### Example - Working With Orders

```php
$proxy = new SoapClient('https://mahohost/api/soap/?wsdl');
$sessionId = $proxy->login('apiUser', 'apiKey');

// Getting list of orders created by John Doe
var_dump($proxy->call($sessionId, 'sales_order.list', [['customer_firstname' => ['eq' => 'John'], 'customer_lastname' => ['eq' => 'Doe']]]));

// Get order info 100000003
var_dump($proxy->call($sessionId, 'sales_order.info', '100000003'));

// Hold order 100000003
$proxy->call($sessionId, 'sales_order.hold', '100000003');

// Unhold order 100000003
$proxy->call($sessionId, 'sales_order.unhold', '100000003');

// Hold order and add comment 100000003
$proxy->call($sessionId, 'sales_order.addComment', ['100000003', 'holded', 'Your order is holded.',  true]);

// Unhold order and add comment 100000003
$proxy->call($sessionId, 'sales_order.addComment', ['100000003', 'pending', 'Your order is pending.', true]);

// Get order info 100000003
var_dump($proxy->call($sessionId, 'sales_order.info', '100000003'));
```

## List

### Method

- `sales_order.list` (SOAP V1)
- `salesOrderList` (SOAP V2)

Allows you to retrieve the list of orders. Additional filters can be applied.

### Aliases

- `order.list`
- `salesOrderList` (SOAP V2 method name)

### Arguments

| Type   | Name      | Description                                              |
|--------|-----------|----------------------------------------------------------|
| string | sessionId | Session ID                                               |
| array  | filters   | Array of filters for the list of sales orders (optional) |

### Returns

| Type  | name   | Description               |
|-------|--------|---------------------------|
| array | result | Array of salesOrderEntity |

### Content `salesOrderEntity`

| Type   | Name                        | Description                                    |
|--------|-----------------------------|------------------------------------------------|
| string | increment_id                | Increment ID                                   |
| string | parent_id                   | Parent ID                                      |
| string | store_id                    | Store ID                                       |
| string | created_at                  | Date of creation                               |
| string | updated_at                  | Date of updating                               |
| string | is_active                   | Defines whether the order is active            |
| string | customer_id                 | Customer ID                                    |
| string | tax_amount                  | Tax amount                                     |
| string | shipping_amount             | Shipping amount                                |
| string | discount_amount             | Discount amount                                |
| string | subtotal                    | Subtotal sum                                   |
| string | grand_total                 | Grand total sum                                |
| string | total_paid                  | Total paid                                     |
| string | total_refunded              | Total refunded                                 |
| string | total_qty_ordered           | Total quantity ordered                         |
| string | total_canceled              | Total canceled                                 |
| string | total_invoiced              | Total invoiced                                 |
| string | total_online_refunded       | Total online refunded                          |
| string | total_offline_refunded      | Total offline refunded                         |
| string | base_tax_amount             | Base tax amount                                |
| string | base_shipping_amount        | Base shipping amount                           |
| string | base_discount_amount        | Base discount amount                           |
| string | base_subtotal               | Base subtotal                                  |
| string | base_grand_total            | Base grand total                               |
| string | base_total_paid             | Base total paid                                |
| string | base_total_refunded         | Base total refunded                            |
| string | base_total_qty_ordered      | Base total quantity ordered                    |
| string | base_total_canceled         | Base total canceled                            |
| string | base_total_invoiced         | Base total invoiced                            |
| string | base_total_online_refunded  | Base total online refunded                     |
| string | base_total_offline_refunded | Base total offline refunded                    |
| string | billing_address_id          | Billing address ID                             |
| string | billing_firstname           | First name in the billing address              |
| string | billing_lastname            | Last name in the billing address               |
| string | shipping_address_id         | Shipping address ID                            |
| string | shipping_firstname          | First name in the shipping address             |
| string | shipping_lastname           | Last name in the shipping address              |
| string | billing_name                | Billing name                                   |
| string | shipping_name               | Shipping name                                  |
| string | store_to_base_rate          | Store to base rate                             |
| string | store_to_order_rate         | Store to order rate                            |
| string | base_to_global_rate         | Base to global rate                            |
| string | base_to_order_rate          | Base to order rate                             |
| string | weight                      | Weight                                         |
| string | store_name                  | Store name                                     |
| string | remote_ip                   | Remote IP                                      |
| string | status                      | Order status                                   |
| string | state                       | Order state                                    |
| string | applied_rule_ids            | Applied rule IDs                               |
| string | global_currency_code        | Global currency code                           |
| string | base_currency_code          | Base currency code                             |
| string | store_currency_code         | Store currency code                            |
| string | order_currency_code         | Order currency code                            |
| string | shipping_method             | Shipping method                                |
| string | shipping_description        | Shipping description                           |
| string | customer_email              | Email address of the customer                  |
| string | customer_firstname          | Customer first name                            |
| string | customer_lastname           | Customer last name                             |
| string | quote_id                    | Shopping cart ID                               |
| string | is_virtual                  | Defines whether the product is a virtual one   |
| string | customer_group_id           | Customer group ID                              |
| string | customer_note_notify        | Customer notification                          |
| string | customer_is_guest           | Defines whether the customer is a guest        |
| string | email_sent                  | Defines whether the email notification is sent |
| string | order_id                    | Order ID                                       |
| string | gift_message_id             | Gift message ID                                |
| string | gift_message                | Gift message                                   |

### Examples

#### Request Example SOAP V1

```php
$client = new SoapClient('https://mahohost/api/soap/?wsdl');
$session = $client->login('apiUser', 'apiKey');

$result = $client->call($session, 'order.list');
var_dump($result);
```

#### Request Example SOAP V2

```php
$params = [[
            'filter' => [
                [
                    'key' => 'status',
                    'value' => 'pending'
                ],
                [
                    'key' => 'created_at',
                    'value' => '2001-11-25 12:12:07',
                ]
            ],
            'complex_filter' => [
                [
                    'key' => 'order_id',
                    'value' => [
                        'key' => 'in',
                        'value' => '12,23'
                    ],
                ],
                [
                    'key' => 'protect_code',
                    'value' => [
                        'key' => 'eq',
                        'value' => 'ebb2a0'
                    ],
                ],
            ]
        ]];

$result = $client->__call('salesOrderList', $params);
```

#### Request Example SOAP V2 (Simple Filter)

```php
$client = new SoapClient('https://mahohost/api/v2_soap/?wsdl');
$session = $client->login('apiUser', 'apiKey');

$filter = ['filter' => [['key' => 'status', 'value' => 'closed']]];
$result = $client->salesOrderList($session, $filter);
var_dump($result);
```

#### Request Example SOAP V2 (WS-I Compliance Mode)

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl'); 
$sessionId = $proxy->login((object)['username' => 'apiUser', 'apiKey' => 'apiKey']); 
 
$result = $proxy->salesOrderList((object)['sessionId' => $sessionId->result, 'filters' => null]);   
var_dump($result->result);
```

#### SOAP "v2" XML Request

```xml
<?xml version="1.0" encoding="UTF-8"?>
<SOAP-ENV:Envelope xmlns:SOAP-ENV="https://schemas.xmlsoap.org/soap/envelope/" xmlns:ns1="urn:Maho"
                   xmlns:xsd="https://www.w3.org/2001/XMLSchema" xmlns:xsi="https://www.w3.org/2001/XMLSchema-instance"
                   xmlns:SOAP-ENC="https://schemas.xmlsoap.org/soap/encoding/"
                   SOAP-ENV:encodingStyle="https://schemas.xmlsoap.org/soap/encoding/">
    <SOAP-ENV:Body>
        <ns1:salesOrderList>
            <sessionId xsi:type="xsd:string">abbc417256a3ffb93d130a77a2fd3665</sessionId>
            <filters xsi:type="ns1:filters">
                <filter SOAP-ENC:arrayType="ns1:associativeEntity[2]" xsi:type="ns1:associativeArray">
                    <item xsi:type="ns1:associativeEntity">
                        <key xsi:type="xsd:string">status</key>
                        <value xsi:type="xsd:string">pending</value>
                    </item>
                    <item xsi:type="ns1:associativeEntity">
                        <key xsi:type="xsd:string">created_at</key>
                        <value xsi:type="xsd:string">2011-11-29 15:41:11</value>
                    </item>
                </filter>
                <complex_filter SOAP-ENC:arrayType="ns1:complexFilter[2]" xsi:type="ns1:complexFilterArray">
                    <item xsi:type="ns1:complexFilter">
                        <key xsi:type="xsd:string">order_id</key>
                        <value xsi:type="ns1:associativeEntity">
                            <key xsi:type="xsd:string">in</key>
                            <value xsi:type="xsd:string">Array</value>
                        </value>
                    </item>
                    <item xsi:type="ns1:complexFilter">
                        <key xsi:type="xsd:string">protect_code</key>
                        <value xsi:type="ns1:associativeEntity">
                            <key xsi:type="xsd:string">in</key>
                            <value xsi:type="xsd:string">a4ffa8</value>
                        </value>
                    </item>
                </complex_filter>
            </filters>
        </ns1:salesOrderList>
    </SOAP-ENV:Body>
</SOAP-ENV:Envelope>
```

## Info

#### Method

- `sales_order.info` (SOAP V1)
- `salesOrderInfo` (SOAP V2)

Allows you to retrieve the required order information.

### Alias

- `order.info`

### Arguments

| Type   | Name             | Description        |
|--------|------------------|--------------------|
| string | sessionId        | Session ID         |
| string | orderIncrementId | Order increment ID |

### Returns

| Type  | Name   | Description               |
|-------|--------|---------------------------|
| array | result | Array of salesOrderEntity |

### Content `salesOrderEntity`

| Type   | Name                        | Description                                    |
|--------|-----------------------------|------------------------------------------------|
| string | increment_id                | Increment ID                                   |
| string | parent_id                   | Parent ID                                      |
| string | store_id                    | Store ID                                       |
| string | created_at                  | Date of creation                               |
| string | updated_at                  | Date of updating                               |
| string | is_active                   | Defines whether the order is active            |
| string | customer_id                 | Customer ID                                    |
| string | tax_amount                  | Tax amount                                     |
| string | shipping_amount             | Shipping amount                                |
| string | discount_amount             | Discount amount                                |
| string | subtotal                    | Subtotal sum                                   |
| string | grand_total                 | Grand total sum                                |
| string | total_paid                  | Total paid                                     |
| string | total_refunded              | Total refunded                                 |
| string | total_qty_ordered           | Total quantity ordered                         |
| string | total_canceled              | Total canceled                                 |
| string | total_invoiced              | Total invoiced                                 |
| string | total_online_refunded       | Total online refunded                          |
| string | total_offline_refunded      | Total offline refunded                         |
| string | base_tax_amount             | Base tax amount                                |
| string | base_shipping_amount        | Base shipping amount                           |
| string | base_discount_amount        | Base discount amount                           |
| string | base_subtotal               | Base subtotal                                  |
| string | base_grand_total            | Base grand total                               |
| string | base_total_paid             | Base total paid                                |
| string | base_total_refunded         | Base total refunded                            |
| string | base_total_qty_ordered      | Base total quantity ordered                    |
| string | base_total_canceled         | Base total canceled                            |
| string | base_total_invoiced         | Base total invoiced                            |
| string | base_total_online_refunded  | Base total online refunded                     |
| string | base_total_offline_refunded | Base total offline refunded                    |
| string | billing_address_id          | Billing address ID                             |
| string | billing_firstname           | First name in the billing address              |
| string | billing_lastname            | Last name in the billing address               |
| string | shipping_address_id         | Shipping address ID                            |
| string | shipping_firstname          | First name in the shipping address             |
| string | shipping_lastname           | Last name in the shipping address              |
| string | billing_name                | Billing name                                   |
| string | shipping_name               | Shipping name                                  |
| string | store_to_base_rate          | Store to base rate                             |
| string | store_to_order_rate         | Store to order rate                            |
| string | base_to_global_rate         | Base to global rate                            |
| string | base_to_order_rate          | Base to order rate                             |
| string | weight                      | Weight                                         |
| string | store_name                  | Store name                                     |
| string | remote_ip                   | Remote IP                                      |
| string | status                      | Order status                                   |
| string | state                       | Order state                                    |
| string | applied_rule_ids            | Applied rule IDs                               |
| string | global_currency_code        | Global currency code                           |
| string | base_currency_code          | Base currency code                             |
| string | store_currency_code         | Store currency code                            |
| string | order_currency_code         | Order currency code                            |
| string | shipping_method             | Shipping method                                |
| string | shipping_description        | Shipping description                           |
| string | customer_email              | Email address of the customer                  |
| string | customer_firstname          | Customer first name                            |
| string | customer_lastname           | Customer last name                             |
| string | quote_id                    | Shopping cart ID                               |
| string | is_virtual                  | Defines whether the product is a virtual one   |
| string | customer_group_id           | Customer group ID                              |
| string | customer_note_notify        | Customer notification                          |
| string | customer_is_guest           | Defines whether the customer is a guest        |
| string | email_sent                  | Defines whether the email notification is sent |
| string | order_id                    | Order ID                                       |
| string | gift_message_id             | Gift message ID                                |
| string | gift_message                | Gift message                                   |
| array  | shipping_address            | Array of salesOrderAddressEntity               |
| array  | billing_address             | Array of salesOrderAddressEntity               |
| array  | items                       | Array of salesOrderItemEntity                  |
| array  | payment                     | Array of salesOrderPaymentEntity               |
| array  | status_history              | Array of salesOrderStatusHistoryEntity         |

### Content `salesOrderAddressEntity`

| Type   | Name         | Description                           |
|--------|--------------|---------------------------------------|
| string | increment_id | Increment ID                          |
| string | parent_id    | Parent ID                             |
| string | created_at   | Date of creation                      |
| string | updated_at   | Date of updating                      |
| string | is_active    | Defines whether the address is active |
| string | address_type | Address type                          |
| string | firstname    | First name                            |
| string | lastname     | Last name                             |
| string | company      | Company name                          |
| string | street       | Street name                           |
| string | city         | City                                  |
| string | region       | Region                                |
| string | postcode     | Post code                             |
| string | country_id   | Country ID                            |
| string | telephone    | Telephone number                      |
| string | fax          | Fax number                            |
| string | region_id    | Region ID                             |
| string | address_id   | Address ID                            |

### Content `salesOrderItemEntity`

| Type   | Name                             | Description                                             |
|--------|----------------------------------|---------------------------------------------------------|
| string | item_id                          | Item ID                                                 |
| string | order_id                         | Order ID                                                |
| string | quote_item_id                    | Shopping cart item ID                                   |
| string | created_at                       | Date of creation                                        |
| string | updated_at                       | Date of updating                                        |
| string | product_id                       | Product ID                                              |
| string | product_type                     | Product type                                            |
| string | product_options                  | Product options                                         |
| string | weight                           | Weight                                                  |
| string | is_virtual                       | Defines whether the product is a virtual one            |
| string | sku                              | Product SKU                                             |
| string | name                             | Product name                                            |
| string | applied_rule_ids                 | Applied rule IDs                                        |
| string | free_shipping                    | Defines whether free shipping is applied                |
| string | is_qty_decimal                   | Defines whether the items quantity is decimal           |
| string | no_discount                      | Defines whether no discount is applied                  |
| string | qty_canceled                     | Items quantity canceled                                 |
| string | qty_invoiced                     | Items quantity invoiced                                 |
| string | qty_ordered                      | Items quantity ordered                                  |
| string | qty_refunded                     | Items quantity refunded                                 |
| string | qty_shipped                      | Items quantity shipped                                  |
| string | cost                             | Cost                                                    |
| string | price                            | Price                                                   |
| string | base_price                       | Base price                                              |
| string | original_price                   | Original price                                          |
| string | base_original_price              | Base original price                                     |
| string | tax_percent                      | Tax percent                                             |
| string | tax_amount                       | Tax amount                                              |
| string | base_tax_amount                  | Base tax amount                                         |
| string | tax_invoiced                     | Tax invoiced                                            |
| string | base_tax_invoiced                | Base tax invoiced                                       |
| string | discount_percent                 | Discount percent                                        |
| string | discount_amount                  | Discount amount                                         |
| string | base_discount_amount             | Base discount amount                                    |
| string | discount_invoiced                | Discount invoiced                                       |
| string | base_discount_invoiced           | Base discount invoiced                                  |
| string | amount_refunded                  | Amount refunded                                         |
| string | base_amount_refunded             | Base amount refunded                                    |
| string | row_total                        | Row total                                               |
| string | base_row_total                   | Base row total                                          |
| string | row_invoiced                     | Row invoiced                                            |
| string | base_row_invoiced                | Base row invoiced                                       |
| string | row_weight                       | Row weight                                              |
| string | gift_message_id                  | Gift message ID                                         |
| string | gift_message                     | Gift message                                            |
| string | gift_message_available           | Defines whether the gift message is available           |
| string | base_tax_before_discount         | Base tax before discount                                |
| string | tax_before_discount              | Tax before discount                                     |
| string | weee_tax_applied                 | Applied fixed product tax                               |
| string | weee_tax_applied_amount          | Applied fixed product tax amount                        |
| string | weee_tax_applied_row_amount      | Applied fixed product tax row amount                    |
| string | base_weee_tax_applied_amount     | Applied fixed product tax amount (in base currency)     |
| string | base_weee_tax_applied_row_amount | Applied fixed product tax row amount (in base currency) |
| string | weee_tax_disposition             | Fixed product tax disposition                           |
| string | weee_tax_row_disposition         | Fixed product tax row disposition                       |
| string | base_weee_tax_disposition        | Fixed product tax disposition (in base currency)        |
| string | base_weee_tax_row_disposition    | Fixed product tax row disposition (in base currency)    |

### Content `salesOrderPaymentEntity`

| Type   | Name                 | Description                           |
|--------|----------------------|---------------------------------------|
| string | increment_id         | Increment ID                          |
| string | parent_id            | Parent ID                             |
| string | created_at           | Date of creation                      |
| string | updated_at           | Date of updating                      |
| string | is_active            | Active flag                           |
| string | amount_ordered       | Amount ordered                        |
| string | shipping_amount      | Shipping amount                       |
| string | base_amount_ordered  | Base amount ordered                   |
| string | base_shipping_amount | Base shipping amount                  |
| string | method               | Payment method                        |
| string | po_number            | Purchase order number                 |
| string | cc_type              | Credit card type                      |
| string | cc_number_enc        | Credit card number                    |
| string | cc_last4             | Credit card last 4 digits             |
| string | cc_owner             | Credit card owner                     |
| string | cc_exp_month         | Credit card expiration month          |
| string | cc_exp_year          | Credit card expiration year           |
| string | cc_ss_start_month    | Credit card start month (Switch/Solo) |
| string | cc_ss_start_year     | Credit card start year (Switch/Solo)  |
| string | payment_id           | Payment ID                            |

### Content `salesOrderStatusHistoryEntity`

| Type   | Name                 | Description                              |
|--------|----------------------|------------------------------------------|
| string | increment_id         | Increment ID                             |
| string | parent_id            | Parent ID                                |
| string | created_at           | Date of creation                         |
| string | updated_at           | Date of updating                         |
| string | is_active            | Active flag                              |
| string | is_customer_notified | Defines whether the customer is notified |
| string | status               | Order status                             |
| string | comment              | Order comment                            |

### Examples

#### Request Example SOAP V1

```php
$client = new SoapClient('https://mahohost/api/soap/?wsdl');
$session = $client->login('apiUser', 'apiKey');

$result = $client->call($session, 'sales_order.info', 'orderIncrementId');
var_dump($result);

// When the session can be closed
$client->endSession($session);
```

#### Request Example SOAP V2

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl'); // replace with your store's WSDL URL
$sessionId = $proxy->login('apiUser', 'apiKey'); // replace with your API user and key

$result = $proxy->salesOrderInfo($sessionId, '200000006');
var_dump($result);
```

#### Request Example SOAP V2 (WS-I Compliance Mode)

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl'); 
$sessionId = $proxy->login((object)['username' => 'apiUser', 'apiKey' => 'apiKey']); 
 
$result = $proxy->salesOrderInfo((object)['sessionId' => $sessionId->result, 'orderIncrementId' => '200000006']);   
var_dump($result->result);
```

#### Response Example SOAP V1

```php
array
  'state' => string 'new' (length=3)
  'status' => string 'pending' (length=7)
  'coupon_code' => null
  'protect_code' => string 'defe18' (length=6)
  'shipping_description' => string 'Flat Rate - Fixed' (length=17)
  'is_virtual' => string '0' (length=1)
  'store_id' => string '2' (length=1)
  'customer_id' => string '2' (length=1)
  'base_discount_amount' => string '0.0000' (length=6)
  'base_discount_canceled' => null
  'base_discount_invoiced' => null
  'base_discount_refunded' => null
  'base_grand_total' => string '619.9700' (length=8)
  'base_shipping_amount' => string '10.0000' (length=7)
  'base_shipping_canceled' => null
  'base_shipping_invoiced' => null
  'base_shipping_refunded' => null
  'base_shipping_tax_amount' => string '0.0000' (length=6)
  'base_shipping_tax_refunded' => null
  'base_subtotal' => string '609.9700' (length=8)
  'base_subtotal_canceled' => null
  'base_subtotal_invoiced' => null
  'base_subtotal_refunded' => null
  'base_tax_amount' => string '0.0000' (length=6)
  'base_tax_canceled' => null
  'base_tax_invoiced' => null
  'base_tax_refunded' => null
  'base_to_global_rate' => string '1.0000' (length=6)
  'base_to_order_rate' => string '1.0000' (length=6)
  'base_total_canceled' => null
  'base_total_invoiced' => null
  'base_total_invoiced_cost' => null
  'base_total_offline_refunded' => null
  'base_total_online_refunded' => null
  'base_total_paid' => null
  'base_total_qty_ordered' => null
  'base_total_refunded' => null
  'discount_amount' => string '0.0000' (length=6)
  'discount_canceled' => null
  'discount_invoiced' => null
  'discount_refunded' => null
  'grand_total' => string '619.9700' (length=8)
  'shipping_amount' => string '10.0000' (length=7)
  'shipping_canceled' => null
  'shipping_invoiced' => null
  'shipping_refunded' => null
  'shipping_tax_amount' => string '0.0000' (length=6)
  'shipping_tax_refunded' => null
  'store_to_base_rate' => string '1.0000' (length=6)
  'store_to_order_rate' => string '1.0000' (length=6)
  'subtotal' => string '609.9700' (length=8)
  'subtotal_canceled' => null
  'subtotal_invoiced' => null
  'subtotal_refunded' => null
  'tax_amount' => string '0.0000' (length=6)
  'tax_canceled' => null
  'tax_invoiced' => null
  'tax_refunded' => null
  'total_canceled' => null
  'total_invoiced' => null
  'total_offline_refunded' => null
  'total_online_refunded' => null
  'total_paid' => null
  'total_qty_ordered' => string '2.0000' (length=6)
  'total_refunded' => null
  'can_ship_partially' => null
  'can_ship_partially_item' => null
  'customer_is_guest' => string '0' (length=1)
  'customer_note_notify' => string '1' (length=1)
  'billing_address_id' => string '1' (length=1)
  'customer_group_id' => string '1' (length=1)
  'edit_increment' => null
  'email_sent' => string '1' (length=1)
  'forced_shipment_with_invoice' => null
  'payment_auth_expiration' => null
  'quote_address_id' => null
  'quote_id' => string '1' (length=1)
  'shipping_address_id' => string '2' (length=1)
  'adjustment_negative' => null
  'adjustment_positive' => null
  'base_adjustment_negative' => null
  'base_adjustment_positive' => null
  'base_shipping_discount_amount' => string '0.0000' (length=6)
  'base_subtotal_incl_tax' => string '609.9700' (length=8)
  'base_total_due' => null
  'payment_authorization_amount' => null
  'shipping_discount_amount' => string '0.0000' (length=6)
  'subtotal_incl_tax' => string '609.9700' (length=8)
  'total_due' => null
  'weight' => string '1.2000' (length=6)
  'customer_dob' => null
  'increment_id' => string '200000001' (length=9)
  'applied_rule_ids' => null
  'base_currency_code' => string 'USD' (length=3)
  'customer_email' => string 'john@example.com' (length=16)
  'customer_firstname' => string 'johny' (length=5)
  'customer_lastname' => string 'doe' (length=3)
  'customer_middlename' => null
  'customer_prefix' => null
  'customer_suffix' => null
  'customer_taxvat' => null
  'discount_description' => null
  'ext_customer_id' => null
  'ext_order_id' => null
  'global_currency_code' => string 'USD' (length=3)
  'hold_before_state' => null
  'hold_before_status' => null
  'order_currency_code' => string 'USD' (length=3)
  'original_increment_id' => null
  'relation_child_id' => null
  'relation_child_real_id' => null
  'relation_parent_id' => null
  'relation_parent_real_id' => null
  'remote_ip' => string '127.0.0.1' (length=9)
  'shipping_method' => string 'flatrate_flatrate' (length=17)
  'store_currency_code' => string 'USD' (length=3)
  'store_name' => string 'website
English store
English' (length=29)
  'x_forwarded_for' => null
  'customer_note' => null
  'created_at' => string '2012-03-30 11:40:30' (length=19)
  'updated_at' => string '2012-03-30 11:40:32' (length=19)
  'total_item_count' => string '2' (length=1)
  'customer_gender' => null
  'hidden_tax_amount' => string '0.0000' (length=6)
  'base_hidden_tax_amount' => string '0.0000' (length=6)
  'shipping_hidden_tax_amount' => string '0.0000' (length=6)
  'base_shipping_hidden_tax_amnt' => string '0.0000' (length=6)
  'hidden_tax_invoiced' => null
  ...
```

## AddComment

### Method

- `sales_order.addComment` (SOAP V1)
- `salesOrderAddComment` (SOAP V2)

Allows you to add a new comment to the order.

### Alias

- `order.addComment`

### Arguments

| Type   | Name             | Description                              |
|--------|------------------|------------------------------------------|
| string | sessionId        | Session ID                               |
| string | orderIncrementId | Order increment ID                       |
| string | status           | Order status (pending, processing, etc.) |
| string | comment          | Order comment (optional)                 |
| string | notify           | Notification flag (optional)             |

### Returns

| Type       | Description                                   |
|------------|-----------------------------------------------|
| booleanint | True (1) if the comment is added to the order |

### Examples

#### Request Example SOAP V1

```php
$client = new SoapClient('https://mahohost/api/soap/?wsdl');
$session = $client->login('apiUser', 'apiKey');

$result = $client->call($session, 'sales_order.addComment', ['orderIncrementId' => '200000004', 'status' => 'processing']);
var_dump($result);

// When the session can be closed
$client->endSession($session);
```

#### Request Example SOAP V2

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl'); // replace with your store's WSDL URL
$sessionId = $proxy->login('apiUser', 'apiKey'); // replace with your API user and key

$result = $proxy->salesOrderAddComment($sessionId, '200000004', 'processing');
var_dump($result);
```

#### Request Example SOAP V2 (WS-I Compliance Mode)

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl'); 
$sessionId = $proxy->login((object)['username' => 'apiUser', 'apiKey' => 'apiKey']); 
 
$result = $proxy->salesOrderAddComment((object)['sessionId' => $sessionId->result, 'orderIncrementId' => '200000004', 'status' => 'processing', 'comment' => 'comment to the order', 'notify' => null]);   
var_dump($result->result);
```

## Hold

### Method

- `sales_order.hold` (SOAP V1)
- `salesOrderHold` (SOAP V2)

Allows you to place the required order on hold.

### Alias

- `order.hold`

### Arguments

| Type   | Name             | Description        |
|--------|------------------|--------------------|
| string | sessionId        | Session ID         |
| string | orderIncrementId | Order increment ID |

### Returns

| Type       | Description                             |
|------------|-----------------------------------------|
| booleanint | True (1) if the order is placed on hold |

### Examples

#### Request Example SOAP V1

```php
$client = new SoapClient('https://mahohost/api/soap/?wsdl');
$session = $client->login('apiUser', 'apiKey');

$result = $client->call($session, 'sales_order.hold', '200000006');
var_dump($result);

// When the session can be closed
$client->endSession($session);
```

#### Request Example SOAP V2

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl'); // replace with your store's WSDL URL
$sessionId = $proxy->login('apiUser', 'apiKey'); // replace with your API user and key

$result = $proxy->salesOrderHold($sessionId, '200000006');
var_dump($result);
```

#### Request Example SOAP V2 (WS-I Compliance Mode)

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl'); 
$sessionId = $proxy->login((object)['username' => 'apiUser', 'apiKey' => 'apiKey']); 
 
$result = $proxy->salesOrderHold((object)['sessionId' => $sessionId->result, 'orderIncrementId' => '200000006']);   
var_dump($result->result);
```

## Unhold

### Method

- `sales_order.unhold` (SOAP V1)
- `salesOrderUnhold` (SOAP V2)

Allows you to unhold the required order.

### Alias

- `order.unhold`

### Arguments

| Type   | Name             | Description        |
|--------|------------------|--------------------|
| string | sessionId        | Session ID         |
| string | orderIncrementId | Order increment ID |

### Returns

| Type       | Description                     |
|------------|---------------------------------|
| booleanint | True (1) if the order is unheld |

### Examples

#### Request Example SOAP V1

```php
$client = new SoapClient('https://mahohost/api/soap/?wsdl');
$session = $client->login('apiUser', 'apiKey');

$result = $client->call($session, 'sales_order.unhold', '200000006');
var_dump($result);
```

#### Request Example SOAP V2

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl'); 
$sessionId = $proxy->login('apiUser', 'apiKey'); 

$result = $proxy->salesOrderUnhold($sessionId, '200000006');
var_dump($result);
```

#### Request Example SOAP V2 (WS-I Compliance Mode)

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl'); 
$sessionId = $proxy->login((object)['username' => 'apiUser', 'apiKey' => 'apiKey']); 
 
$result = $proxy->salesOrderUnhold((object)['sessionId' => $sessionId->result, 'orderIncrementId' => '200000006']);   
var_dump($result->result);
```

## Cancel

### Method

- `sales_order.cancel` (SOAP V1)
- `salesOrderCancel` (SOAP V2)

Allows you to cancel the required order.

### Alias

- `order.cancel`

#### Arguments

| Type   | Name             | Description        |
|--------|------------------|--------------------|
| string | sessionId        | Session ID         |
| string | orderIncrementId | Order increment ID |

### Returns

| Type    | Description                   |
|---------|-------------------------------|
| boolean | True if the order is canceled |

### Examples

#### Request Example SOAP V1

```php
$client = new SoapClient('https://mahohost/api/soap/?wsdl');
$session = $client->login('apiUser', 'apiKey');

$result = $client->call($session, 'sales_order.cancel', '200000004');
var_dump($result);

// When the session can be closed
$client->endSession($session);
```

#### Request Example SOAP V2

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl'); // replace with your store's WSDL URL
$sessionId = $proxy->login('apiUser', 'apiKey'); // replace with your API user and key

$result = $proxy->salesOrderCancel($sessionId, '200000004');
var_dump($result);
```

#### Request Example SOAP V2 (WS-I Compliance Mode)

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl'); 
$sessionId = $proxy->login((object)['username' => 'apiUser', 'apiKey' => 'apiKey']); 
 
$result = $proxy->salesOrderCancel((object)['sessionId' => $sessionId->result, 'orderIncrementId' => '200000004']);   
var_dump($result->result);
```
