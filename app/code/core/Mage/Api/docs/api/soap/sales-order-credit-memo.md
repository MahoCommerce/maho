---
description: Operate with order credit memos over the Maho SOAP API - list, info, create, comment, and cancel.
---

# Sales Order Credit Memo

!!! warning "Legacy API"
    This is Maho's legacy SOAP/XML-RPC API, inherited from Magento 1 and kept for backward compatibility. New integrations should use the modern [REST & GraphQL API (v2)](../v2/index.md) instead.

## Introduction

Allows you to operate with credit memos for orders.

### Resource

- `sales_order_creditmemo`

### Alias

- `order_creditmemo`

### Methods

- `sales_order_creditmemo.list` - Retrieve the list of credit memos by filters.
- `sales_order_creditmemo.info` - Retrieve the credit memo information.
- `sales_order_creditmemo.create` - Create a new credit memo for order.
- `sales_order_creditmemo.addComment` - Add a new comment to the credit memo.
- `sales_order_creditmemo.cancel` - Cancel the credit memo.

### Faults

| Fault Code | Fault Message                                                                        |
|------------|--------------------------------------------------------------------------------------|
| 100        | Requested credit memo does not exist.                                                |
| 101        | Invalid filter given. Details in error message.                                      |
| 102        | Invalid data given. Details in error message.                                        |
| 103        | Requested order does not exist.                                                      |
| 104        | Credit memo status not changed.                                                      |
| 105        | Money can not be refunded to the store credit account as order was created by guest. |
| 106        | Credit memo for requested order can not be created.                                  |

### Example

```php
$proxy = new SoapClient('https://mahohost/api/soap/?wsdl');
$sessionId = $proxy->login('apiUser', 'apiKey');

// Create creditmemo
$orderIncrementId = '100000683'; // increment id of the invoiced order
$data = [
    'qtys' => [
        '712' => 1
    ],
    'shipping_amount' => 3,
    'adjustment_positive' => 0.7,
    'adjustment_negative' => 0.06
];
$creditmemoIncrementId = $proxy->call($sessionId, 'order_creditmemo.create', [$orderIncrementId, $data]);
echo $creditmemoIncrementId . "<br>";

// Add comment to created creditmemo
$commentText = "Credit memo comment successfully added";
$isCommentAdded = $proxy->call($sessionId, 'order_creditmemo.addComment', [$creditmemoIncrementId, $commentText, true]);

// Retrieve information about created creditmemo
$creditmemoInfo = $proxy->call($sessionId, 'order_creditmemo.info', [$creditmemoIncrementId]);
print_r($creditmemoInfo);

// Retrieve the list of creditmemos by filter
$filter = [
    'increment_id' => [
        'or' => [
            [
                'from' => '100000617',
                'to' => '100000619',
            ],
            [
                'from' => $creditmemoIncrementId,
                'to' => null,
            ]
        ]
    ]
];
$creditmemoList = $proxy->call($sessionId, 'order_creditmemo.list', [$filter]);
print_r($creditmemoList);
```

## List

### Method

- `order_creditmemo.list` (SOAP V1)
- `salesOrderCreditmemoList` (SOAP V2)

Allows you to retrieve the list of credit memos by filters.

### Arguments

| Type              | Name      | Description        |
|-------------------|-----------|--------------------|
| string            | sessionId | Session ID         |
| associative array | filters   | Filters (optional) |

### Return

| Type  | Name   | Description                         |
|-------|--------|-------------------------------------|
| array | result | Array of salesOrderCreditmemoEntity |

### Content `salesOrderCreditmemoEntity`

| Type   | Name                           | Description                                               |
|--------|--------------------------------|-----------------------------------------------------------|
| string | updated_at                     | Date of updating                                          |
| string | created_at                     | Date of creation                                          |
| string | increment_id                   | Increment ID                                              |
| string | transaction_id                 | Transaction ID                                            |
| string | global_currency_code           | Global currency code                                      |
| string | base_currency_code             | Base currency code                                        |
| string | order_currency_code            | Order currency code                                       |
| string | store_currency_code            | Store currency code                                       |
| string | cybersource_token              | Cybersource token                                         |
| string | invoice_id                     | ID of the invoice for which the credit memo was created   |
| string | billing_address_id             | Billing address ID                                        |
| string | shipping_address_id            | Shipping address ID                                       |
| string | state                          | State                                                     |
| string | creditmemo_status              | Credit memo status                                        |
| string | email_sent                     | Defines whether the email is sent                         |
| string | order_id                       | ID of the order for which the credit memo was created     |
| string | tax_amount                     | Tax amount                                                |
| string | shipping_tax_amount            | Shipping tax amount                                       |
| string | base_tax_amount                | Base tax amount                                           |
| string | base_adjustment_positive       | Adjustment refund amount (using base currency)            |
| string | base_grand_total               | Base grand total                                          |
| string | adjustment                     | Adjustment                                                |
| string | subtotal                       | Subtotal                                                  |
| string | discount_amount                | Discount amount                                           |
| string | base_subtotal                  | Base subtotal                                             |
| string | base_adjustment                | Base adjustment                                           |
| string | base_to_global_rate            | Base to global rate                                       |
| string | store_to_base_rate             | Store to base rate                                        |
| string | base_shipping_amount           | Base shipping amount                                      |
| string | adjustment_negative            | Adjustment fee amount                                     |
| string | subtotal_incl_tax              | Subtotal including tax                                    |
| string | shipping_amount                | Shipping amount                                           |
| string | base_subtotal_incl_tax         | Base subtotal including tax                               |
| string | base_adjustment_negative       | Adjustment fee amount (using base currency)               |
| string | grand_total                    | Grand total                                               |
| string | base_discount_amount           | Base discount amount                                      |
| string | base_to_order_rate             | Base to order rate                                        |
| string | store_to_order_rate            | Store to order rate                                       |
| string | base_shipping_tax_amount       | Base shipping tax amount                                  |
| string | adjustment_positive            | Adjustment refund amount                                  |
| string | store_id                       | Store ID                                                  |
| string | hidden_tax_amount              | Hidden tax amount                                         |
| string | base_hidden_tax_amount         | Base hidden tax amount                                    |
| string | shipping_hidden_tax_amount     | Shipping hidden tax amount                                |
| string | base_shipping_hidden_tax_amnt  | Base shipping hidden tax amount                           |
| string | shipping_incl_tax              | Shipping including tax                                    |
| string | base_shipping_incl_tax         | Base shipping including tax                               |
| string | base_customer_balance_amount   | Base customer balance amount                              |
| string | customer_balance_amount        | Customer balance amount                                   |
| string | bs_customer_bal_total_refunded | Refunded base customer balance amount                     |
| string | customer_bal_total_refunded    | Customer balance total refunded                           |
| string | base_gift_cards_amount         | Base gift cards amount                                    |
| string | gift_cards_amount              | Gift cards amount                                         |
| string | gw_base_price                  | Gift wrapping price refunded amount (using base currency) |
| string | gw_price                       | Gift wrapping price refunded amount                       |
| string | gw_items_base_price            | Gift wrapping items base price                            |
| string | gw_items_price                 | Gift wrapping items price                                 |
| string | gw_card_base_price             | Gift wrapping card base price                             |
| string | gw_card_price                  | Gift wrapping card price                                  |
| string | gw_base_tax_amount             | Gift wrapping tax amount refunded (using base currency)   |
| string | gw_tax_amount                  | Gift wrapping tax amount refunded                         |
| string | gw_items_base_tax_amount       | Gift wrapping items base tax amount                       |
| string | gw_items_tax_amount            | Gift wrapping items tax amount                            |
| string | gw_card_base_tax_amount        | Gift wrapping card base tax amount                        |
| string | gw_card_tax_amount             | Gift wrapping card tax amount                             |
| string | base_reward_currency_amount    | Base reward currency amount                               |
| string | reward_currency_amount         | Reward currency amount                                    |
| string | reward_points_balance          | Reward points balance                                     |
| string | reward_points_balance_refund   | Reward points balance refund                              |
| string | creditmemo_id                  | Credit memo ID                                            |
| array  | items                          | Array of salesOrderCreditmemoItemEntity                   |
| array  | comments                       | Array of salesOrderCreditmemoCommentEntity                |

### Content `salesOrderCreditmemoItemEntity`

| Type   | Name                             | Description                                             |
|--------|----------------------------------|---------------------------------------------------------|
| string | item_id                          | Credit memo item ID                                     |
| string | parent_id                        | Parent ID                                               |
| string | weee_tax_applied_row_amount      | Applied fixed product tax row amount                    |
| string | base_price                       | Base price                                              |
| string | base_weee_tax_row_disposition    | Fixed product tax row disposition (in base currency)    |
| string | tax_amount                       | Tax amount                                              |
| string | base_weee_tax_applied_amount     | Applied fixed product tax amount (in base currency)     |
| string | weee_tax_row_disposition         | Fixed product tax row disposition                       |
| string | base_row_total                   | Base row total                                          |
| string | discount_amount                  | Discount amount                                         |
| string | row_total                        | Row total                                               |
| string | weee_tax_applied_amount          | Applied fixed product tax amount                        |
| string | base_discount_amount             | Base discount amount                                    |
| string | base_weee_tax_disposition        | Fixed product tax disposition (in base currency)        |
| string | price_incl_tax                   | Price including tax                                     |
| string | base_tax_amount                  | Base tax amount                                         |
| string | weee_tax_disposition             | Fixed product tax disposition                           |
| string | base_price_incl_tax              | Base price including tax                                |
| string | qty                              | Quantity                                                |
| string | base_cost                        | Base cost                                               |
| string | base_weee_tax_applied_row_amount | Applied fixed product tax row amount (in base currency) |
| string | price                            | Price                                                   |
| string | base_row_total_incl_tax          | Base row total including tax                            |
| string | row_total_incl_tax               | Row total including tax                                 |
| string | product_id                       | Product ID                                              |
| string | order_item_id                    | Order item ID                                           |
| string | additional_data                  | Additional data                                         |
| string | description                      | Description                                             |
| string | weee_tax_applied                 | Applied fixed product tax                               |
| string | sku                              | Item SKU                                                |
| string | name                             | Name                                                    |
| string | hidden_tax_amount                | Hidden tax amount                                       |
| string | base_hidden_tax_amount           | Base hidden tax amount                                  |

### Content `salesOrderCreditmemoCommentEntity`

| Type   | Name                 | Description                                            |
|--------|----------------------|--------------------------------------------------------|
| string | parent_id            | Parent ID                                              |
| string | created_at           | Date of creation                                       |
| string | comment              | Comment data                                           |
| string | is_customer_notified | Defines whether the customer is notified               |
| string | comment_id           | Comment ID                                             |
| string | is_visible_on_front  | Defines whether the comment is visible on the frontend |

### Faults

| Fault Code | Fault Description                               |
|------------|-------------------------------------------------|
| 101        | Invalid filter given. Details in error message. |

### Examples

#### Request Example SOAP V1

```php
$proxy = new SoapClient('https://mahohost/api/soap/?wsdl');
$sessionId = $proxy->login('apiUser', 'apiKey');
$filter = [
    'order_id' => [
        'or' => [
            [
                'from' => '673',
                'to' => '674',
            ],
            [
                'from' => '677',
                'to' => null,
            ]
        ]
    ],
    'increment_id' => [
        'or' => [
            [
                'from' => '100000617',
                'to' => '100000619',
            ],
            [
                'from' => '100000619',
                'to' => null,
            ]
        ]
    ]
];
$creditmemoList = $proxy->call($sessionId, 'order_creditmemo.list', [$filter]);
```

#### Request Example SOAP V2

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl'); // replace with your store's WSDL URL
$sessionId = $proxy->login('apiUser', 'apiKey'); // replace with your API user and key

$result = $proxy->salesOrderCreditmemoList($sessionId, '200000001');
var_dump($result);
```

#### Request Example SOAP V2 (Complex Filter)

```php
$client = new SoapClient('https://mahohost/api/v2_soap/?wsdl');
$session = $client->login('apiUser', 'apiKey');

$complexFilter = [
    'complex_filter' => [
        [
            'key' => 'state',
            'value' => ['key' => 'in', 'value' => '2,3']
        ]
    ]
];
$result = $client->salesOrderCreditmemoList($session, $complexFilter);
var_dump($result);
```

#### Request Example SOAP V2 (WS-I Compliance Mode)

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl'); 
$sessionId = $proxy->login((object)['username' => 'apiUser', 'apiKey' => 'apiKey']); 
 
$result = $proxy->salesOrderCreditmemoList((object)['sessionId' => $sessionId->result]);   
var_dump($result->result);
```

#### Response Example SOAP V1

```php
[
    0 => [
        'store_id' => '1',
        'adjustment_positive' => null,
        'base_shipping_tax_amount' => '0.0000',
        'store_to_order_rate' => '1.0000',
        'base_discount_amount' => '0.0000',
        'base_to_order_rate' => '1.0000',
        'grand_total' => '60.0000',
        'base_adjustment_negative' => null,
        'base_subtotal_incl_tax' => '55.0000',
        'shipping_amount' => '5.0000',
        'subtotal_incl_tax' => '55.0000',
        'adjustment_negative' => null,
        'base_shipping_amount' => '5.0000',
        'store_to_base_rate' => '1.0000',
        'base_to_global_rate' => '1.0000',
        'base_adjustment' => '0.0000',
        'base_subtotal' => '55.0000',
        'discount_amount' => '0.0000',
        'subtotal' => '55.0000',
        'adjustment' => '0.0000',
        'base_grand_total' => '60.0000',
        'base_adjustment_positive' => null,
        'base_tax_amount' => '0.0000',
        'shipping_tax_amount' => '0.0000',
        'tax_amount' => '0.0000',
        'order_id' => '674',
        'email_sent' => null,
        'creditmemo_status' => null,
        'state' => '2',
        'shipping_address_id' => '1348',
        'billing_address_id' => '1347',
        'invoice_id' => null,
        'cybersource_token' => null,
        'store_currency_code' => 'USD',
        'order_currency_code' => 'USD',
        'base_currency_code' => 'USD',
        'global_currency_code' => 'USD',
        'transaction_id' => null,
        'increment_id' => '100000617',
        'created_at' => '2011-05-26 10:49:45',
        'updated_at' => '2011-05-26 10:49:45',
        'hidden_tax_amount' => '0.0000',
        'base_hidden_tax_amount' => '0.0000',
        'shipping_hidden_tax_amount' => null,
        'base_shipping_hidden_tax_amnt' => null,
        'shipping_incl_tax' => '5.0000',
        'base_shipping_incl_tax' => '5.0000',
        'base_customer_balance_amount' => null,
        'customer_balance_amount' => null,
        'bs_customer_bal_total_refunded' => '0.0000',
        'customer_bal_total_refunded' => '0.0000',
        'base_gift_cards_amount' => null,
        'gift_cards_amount' => null,
        'gw_base_price' => null,
        'gw_price' => null,
        'gw_items_base_price' => null,
        'gw_items_price' => null,
        'gw_card_base_price' => null,
        'gw_card_price' => null,
        'gw_base_tax_amount' => null,
        'gw_tax_amount' => null,
        'gw_items_base_tax_amount' => null,
        'gw_items_tax_amount' => null,
        'gw_card_base_tax_amount' => null,
        'gw_card_tax_amount' => null,
        'base_reward_currency_amount' => null,
        'reward_currency_amount' => null,
        'reward_points_balance' => null,
        'reward_points_balance_refund' => null,
        'creditmemo_id' => '617',
    ],
    1 => [
        'store_id' => '1',
        'adjustment_positive' => null,
        ...
        'creditmemo_id' => '620',
    ]
];
```

## Info

### Method

- `order_creditmemo.info` (SOAP V1)
- `salesOrderCreditmemoInfo` (SOAP V2)

Allows you to retrieve full information about the specified credit memo.

### Arguments

| Type   | Name                  | Description              |
|--------|-----------------------|--------------------------|
| string | sessionId             | Session ID               |
| string | creditmemoIncrementId | Credit memo increment ID |

### Return

| Type  | Name   | Description                         |
|-------|--------|-------------------------------------|
| array | result | Array of salesOrderCreditmemoEntity |

### Content `salesOrderCreditmemoEntity`

| Type   | Name                           | Description                                               |
|--------|--------------------------------|-----------------------------------------------------------|
| string | updated_at                     | Date of updating                                          |
| string | created_at                     | Date of creation                                          |
| string | increment_id                   | Increment ID                                              |
| string | transaction_id                 | Transaction ID                                            |
| string | global_currency_code           | Global currency code                                      |
| string | base_currency_code             | Base currency code                                        |
| string | order_currency_code            | Order currency code                                       |
| string | store_currency_code            | Store currency code                                       |
| string | cybersource_token              | Cybersource token                                         |
| string | invoice_id                     | ID of the invoice for which the credit memo was created   |
| string | billing_address_id             | Billing address ID                                        |
| string | shipping_address_id            | Shipping address ID                                       |
| string | state                          | State                                                     |
| string | creditmemo_status              | Credit memo status                                        |
| string | email_sent                     | Defines whether the email is sent                         |
| string | order_id                       | ID of the order for which the credit memo was created     |
| string | tax_amount                     | Tax amount                                                |
| string | shipping_tax_amount            | Shipping tax amount                                       |
| string | base_tax_amount                | Base tax amount                                           |
| string | base_adjustment_positive       | Adjustment refund amount (using base currency)            |
| string | base_grand_total               | Base grand total                                          |
| string | adjustment                     | Adjustment                                                |
| string | subtotal                       | Subtotal                                                  |
| string | discount_amount                | Discount amount                                           |
| string | base_subtotal                  | Base subtotal                                             |
| string | base_adjustment                | Base adjustment                                           |
| string | base_to_global_rate            | Base to global rate                                       |
| string | store_to_base_rate             | Store to base rate                                        |
| string | base_shipping_amount           | Base shipping amount                                      |
| string | adjustment_negative            | Adjustment fee amount                                     |
| string | subtotal_incl_tax              | Subtotal including tax                                    |
| string | shipping_amount                | Shipping amount                                           |
| string | base_subtotal_incl_tax         | Base subtotal including tax                               |
| string | base_adjustment_negative       | Adjustment fee amount (using base currency)               |
| string | grand_total                    | Grand total                                               |
| string | base_discount_amount           | Base discount amount                                      |
| string | base_to_order_rate             | Base to order rate                                        |
| string | store_to_order_rate            | Store to order rate                                       |
| string | base_shipping_tax_amount       | Base shipping tax amount                                  |
| string | adjustment_positive            | Adjustment refund amount                                  |
| string | store_id                       | Store ID                                                  |
| string | hidden_tax_amount              | Hidden tax amount                                         |
| string | base_hidden_tax_amount         | Base hidden tax amount                                    |
| string | shipping_hidden_tax_amount     | Shipping hidden tax amount                                |
| string | base_shipping_hidden_tax_amnt  | Base shipping hidden tax amount                           |
| string | shipping_incl_tax              | Shipping including tax                                    |
| string | base_shipping_incl_tax         | Base shipping including tax                               |
| string | base_customer_balance_amount   | Base customer balance amount                              |
| string | customer_balance_amount        | Customer balance amount                                   |
| string | bs_customer_bal_total_refunded | Refunded base customer balance amount                     |
| string | customer_bal_total_refunded    | Customer balance total refunded                           |
| string | base_gift_cards_amount         | Base gift cards amount                                    |
| string | gift_cards_amount              | Gift cards amount                                         |
| string | gw_base_price                  | Gift wrapping price refunded amount (using base currency) |
| string | gw_price                       | Gift wrapping price refunded amount                       |
| string | gw_items_base_price            | Gift wrapping items base price                            |
| string | gw_items_price                 | Gift wrapping items price                                 |
| string | gw_card_base_price             | Gift wrapping card base price                             |
| string | gw_card_price                  | Gift wrapping card price                                  |
| string | gw_base_tax_amount             | Gift wrapping tax amount refunded (using base currency)   |
| string | gw_tax_amount                  | Gift wrapping tax amount refunded                         |
| string | gw_items_base_tax_amount       | Gift wrapping items base tax amount                       |
| string | gw_items_tax_amount            | Gift wrapping items tax amount                            |
| string | gw_card_base_tax_amount        | Gift wrapping card base tax amount                        |
| string | gw_card_tax_amount             | Gift wrapping card tax amount                             |
| string | base_reward_currency_amount    | Base reward currency amount                               |
| string | reward_currency_amount         | Reward currency amount                                    |
| string | reward_points_balance          | Reward points balance                                     |
| string | reward_points_balance_refund   | Reward points balance refund                              |
| string | creditmemo_id                  | Credit memo ID                                            |
| array  | items                          | Array of salesOrderCreditmemoItemEntity                   |
| array  | comments                       | Array of salesOrderCreditmemoCommentEntity                |

### Content `salesOrderCreditmemoItemEntity`

| Type   | Name                             | Description                                             |
|--------|----------------------------------|---------------------------------------------------------|
| string | item_id                          | Credit memo item ID                                     |
| string | parent_id                        | Parent ID                                               |
| string | weee_tax_applied_row_amount      | Applied fixed product tax row amount                    |
| string | base_price                       | Base price                                              |
| string | base_weee_tax_row_disposition    | Fixed product tax row disposition (in base currency)    |
| string | tax_amount                       | Tax amount                                              |
| string | base_weee_tax_applied_amount     | Applied fixed product tax amount (in base currency)     |
| string | weee_tax_row_disposition         | Fixed product tax row disposition                       |
| string | base_row_total                   | Base row total                                          |
| string | discount_amount                  | Discount amount                                         |
| string | row_total                        | Row total                                               |
| string | weee_tax_applied_amount          | Applied fixed product tax amount                        |
| string | base_discount_amount             | Base discount amount                                    |
| string | base_weee_tax_disposition        | Fixed product tax disposition (in base currency)        |
| string | price_incl_tax                   | Price including tax                                     |
| string | base_tax_amount                  | Base tax amount                                         |
| string | weee_tax_disposition             | Fixed product tax disposition                           |
| string | base_price_incl_tax              | Base price including tax                                |
| string | qty                              | Quantity                                                |
| string | base_cost                        | Base cost                                               |
| string | base_weee_tax_applied_row_amount | Applied fixed product tax row amount (in base currency) |
| string | price                            | Price                                                   |
| string | base_row_total_incl_tax          | Base row total including tax                            |
| string | row_total_incl_tax               | Row total including tax                                 |
| string | product_id                       | Product ID                                              |
| string | order_item_id                    | Order item ID                                           |
| string | additional_data                  | Additional data                                         |
| string | description                      | Description                                             |
| string | weee_tax_applied                 | Applied fixed product tax                               |
| string | sku                              | Item SKU                                                |
| string | name                             | Name                                                    |
| string | hidden_tax_amount                | Hidden tax amount                                       |
| string | base_hidden_tax_amount           | Base hidden tax amount                                  |

## Content `salesOrderCreditmemoCommentEntity`

| Type   | Name                 | Description                                            |
|--------|----------------------|--------------------------------------------------------|
| string | parent_id            | Parent ID                                              |
| string | created_at           | Date of creation                                       |
| string | comment              | Comment data                                           |
| string | is_customer_notified | Defines whether the customer is notified               |
| string | comment_id           | Comment ID                                             |
| string | is_visible_on_front  | Defines whether the comment is visible on the frontend |

### Faults

| Fault Code | Fault Description                     |
|------------|---------------------------------------|
| 100        | Requested credit memo does not exist. |

### Examples

#### Request Example SOAP V1

```php
$client = new SoapClient('https://mahohost/api/soap/?wsdl');
$session = $client->login('apiUser', 'apiKey');

$result = $client->call($session, 'order_creditmemo.info', '200000001');
var_dump($result);
```

#### Request Example SOAP V2

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl'); // replace with your store's WSDL URL
$sessionId = $proxy->login('apiUser', 'apiKey'); // replace with your API user and key

$result = $proxy->salesOrderCreditmemoInfo($sessionId, '200000001');
var_dump($result);
```

#### Request Example SOAP V2 (WS-I Compliance Mode)

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl'); 
$sessionId = $proxy->login((object)['username' => 'apiUser', 'apiKey' => 'apiKey']); 
 
$result = $proxy->salesOrderCreditmemoInfo((object)['sessionId' => $sessionId->result, 'creditmemoIncrementId' => '200000001']);   
var_dump($result->result);
```

#### Response Example SOAP V1

```php
[
    'store_id' => '1',
    'adjustment_positive' => null,
    'base_shipping_tax_amount' => '0.0000',
    'store_to_order_rate' => '1.0000',
    'base_discount_amount' => '0.0000',
    'base_to_order_rate' => '1.0000',
    'grand_total' => '90.0000',
    'base_adjustment_negative' => null,
    'base_subtotal_incl_tax' => '75.0000',
    'shipping_amount' => '15.0000',
    'subtotal_incl_tax' => '75.0000',
    'adjustment_negative' => null,
    'base_shipping_amount' => '15.0000',
    'store_to_base_rate' => '1.0000',
    'base_to_global_rate' => '1.0000',
    'base_adjustment' => '0.0000',
    'base_subtotal' => '75.0000',
    'discount_amount' => '0.0000',
    'subtotal' => '75.0000',
    'adjustment' => '0.0000',
    'base_grand_total' => '90.0000',
    'base_adjustment_positive' => null,
    'base_tax_amount' => '0.0000',
    'shipping_tax_amount' => '0.0000',
    'tax_amount' => '0.0000',
    'order_id' => '744',
    'email_sent' => null,
    'creditmemo_status' => null,
    'state' => '2',
    'shipping_address_id' => '1488',
    'billing_address_id' => '1487',
    'invoice_id' => null,
    'cybersource_token' => null,
    'store_currency_code' => 'USD',
    'order_currency_code' => 'USD',
    'base_currency_code' => 'USD',
    'global_currency_code' => 'USD',
    'transaction_id' => null,
    'increment_id' => '100000684',
    'created_at' => '2011-05-27 10:53:03',
    'updated_at' => '2011-05-27 10:53:03',
    'hidden_tax_amount' => '0.0000',
    'base_hidden_tax_amount' => '0.0000',
    'shipping_hidden_tax_amount' => null,
    'base_shipping_hidden_tax_amnt' => null,
    'shipping_incl_tax' => '15.0000',
    'base_shipping_incl_tax' => '15.0000',
    'base_customer_balance_amount' => null,
    'customer_balance_amount' => null,
    'bs_customer_bal_total_refunded' => '0.0000',
    'customer_bal_total_refunded' => '0.0000',
    'base_gift_cards_amount' => null,
    'gift_cards_amount' => null,
    'gw_base_price' => null,
    'gw_price' => null,
    'gw_items_base_price' => null,
    'gw_items_price' => null,
    'gw_card_base_price' => null,
    'gw_card_price' => null,
    'gw_base_tax_amount' => null,
    'gw_tax_amount' => null,
    'gw_items_base_tax_amount' => null,
    'gw_items_tax_amount' => null,
    'gw_card_base_tax_amount' => null,
    'gw_card_tax_amount' => null,
    'base_reward_currency_amount' => null,
    'reward_currency_amount' => null,
    'reward_points_balance' => null,
    'reward_points_balance_refund' => null,
    'base_customer_balance_total_refunded' => '0.0000',
    'customer_balance_total_refunded' => '0.0000',
    'gw_printed_card_base_price' => null,
    'gw_printed_card_price' => null,
    'gw_printed_card_base_tax_amount' => null,
    'gw_printed_card_tax_amount' => null,
    'reward_points_balance_to_refund' => null,
    'creditmemo_id' => '684',
    'order_increment_id' => '100000744',
    'items' => [
        0 => [
            'parent_id' => '684',
            'weee_tax_applied_row_amount' => '0.0000',
            'base_price' => '55.0000',
            'base_weee_tax_row_disposition' => '0.0000',
            'tax_amount' => '0.0000',
            'base_weee_tax_applied_amount' => '0.0000',
            'weee_tax_row_disposition' => '0.0000',
            'base_row_total' => '55.0000',
            'discount_amount' => null,
            'row_total' => '55.0000',
            'weee_tax_applied_amount' => '0.0000',
            'base_discount_amount' => null,
            'base_weee_tax_disposition' => '0.0000',
            'price_incl_tax' => '55.0000',
            'base_tax_amount' => '0.0000',
            'weee_tax_disposition' => '0.0000',
            'base_price_incl_tax' => '55.0000',
            'qty' => '1.0000',
            'base_cost' => null,
            'base_weee_tax_applied_row_amnt' => null,
            'price' => '55.0000',
            'base_row_total_incl_tax' => '55.0000',
            'row_total_incl_tax' => '55.0000',
            'product_id' => '20',
            'order_item_id' => '775',
            'additional_data' => null,
            'description' => null,
            'weee_tax_applied' => 'a:0:{}',
            'sku' => 'test_product_sku',
            'name' => 'Test Product',
            'hidden_tax_amount' => '0.0000',
            'base_hidden_tax_amount' => '0.0000',
            'item_id' => '708'
        ],
        1 => [
            'parent_id' => '684',
            'weee_tax_applied_row_amount' => '0.0000',
            'base_price' => '10.0000',
            'base_weee_tax_row_disposition' => '0.0000',
            'tax_amount' => '0.0000',
            'base_weee_tax_applied_amount' => '0.0000',
            'weee_tax_row_disposition' => '0.0000',
            'base_row_total' => '20.0000',
            'discount_amount' => null,
            'row_total' => '20.0000',
            'weee_tax_applied_amount' => '0.0000',
            'base_discount_amount' => null,
            'base_weee_tax_disposition' => '0.0000',
            'price_incl_tax' => '10.0000',
            'base_tax_amount' => '0.0000',
            'weee_tax_disposition' => '0.0000',
            'base_price_incl_tax' => '10.0000',
            'qty' => '2.0000',
            'base_cost' => null,
            'base_weee_tax_applied_row_amnt' => null,
            'price' => '10.0000',
            'base_row_total_incl_tax' => '20.0000',
            'row_total_incl_tax' => '20.0000',
            'product_id' => '21',
            'order_item_id' => '776',
            'additional_data' => null,
            'description' => null,
            'weee_tax_applied' => 'a:0:{}',
            'sku' => 'test_product_sku_2',
            'name' => 'Test 2',
            'hidden_tax_amount' => '0.0000',
            'base_hidden_tax_amount' => '0.0000',
            'item_id' => '709'
        ]
    ],
    'comments' => [
        0 => [
            'parent_id' => '684',
            'is_customer_notified' => '0',
            'is_visible_on_front' => '0',
            'comment' => 'Test CreditMemo successfully created',
            'created_at' => '2011-05-27 10:53:03',
            'comment_id' => '118'
        ],
        1 => [
            'parent_id' => '684',
            'is_customer_notified' => '0',
            'is_visible_on_front' => '0',
            'comment' => 'Test CreditMemo comment successfully added',
            'created_at' => '2011-05-27 10:53:03',
            'comment_id' => '119'
        ]
    ]
];
```

## Create

### Method

- `order_creditmemo.create` (SOAP V1)
- `salesOrderCreditmemoCreate` (SOAP V2)

Allows you to create a new credit memo for the invoiced order.
Comments can be added and an email notification can be sent to the user email.

### Arguments

| Type   | Name                      | Description                                                           |
|--------|---------------------------|-----------------------------------------------------------------------|
| string | sessionId                 | Session ID                                                            |
| string | orderIncrementId          | Order increment ID                                                    |
| array  | creditmemoData            | Array of salesOrderCreditmemoData (optional)                          |
| string | comment                   | Comment text (optional)                                               |
| int    | notifyCustomer            | Notify customer by email flag (optional)                              |
| int    | includeComment            | Include comment text into an email notification (optional)            |
| string | refundToStoreCreditAmount | Payment amount to be refunded to the customer store credit (optional) |

### Return

| Type   | Name   | Description                      |
|--------|--------|----------------------------------|
| string | result | Created credit memo increment ID |

### Content `salesOrderCreditmemoData`

| Type   | Name                | Description                         |
|--------|---------------------|-------------------------------------|
| array  | qtys                | Array of orderItemIdQty             |
| double | shipping_amount     | Refund shipping amount (optional)   |
| double | adjustment_positive | Adjustment refund amount (optional) |
| double | adjustment_negative | Adjustment fee amount (optional)    |

### Content `orderItemIdQty`

| Type   | Name          | Description                   |
|--------|---------------|-------------------------------|
| int    | order_item_id | Order item ID to be refunded  |
| double | qty           | Items quantity to be refunded |

### Faults

| Fault Code | Fault Message                                                                        |
|------------|--------------------------------------------------------------------------------------|
| 102        | Invalid data given. Details in error message.                                        |
| 103        | Requested order does not exist.                                                      |
| 105        | Money can not be refunded to the store credit account as order was created by guest. |
| 106        | Credit memo for requested order can not be created.                                  |

### Examples

#### Request Example SOAP V1

```php
$client = new SoapClient('https://mahohost/api/soap/?wsdl');
$session = $client->login('apiUser', 'apiKey');

$result = $client->call($session, 'order_creditmemo.create', '200000010');
var_dump($result);
```

#### Request Example SOAP V2

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl'); // replace with your store's WSDL URL
$sessionId = $proxy->login('apiUser', 'apiKey'); // replace with your API user and key

$result = $proxy->salesOrderCreditmemoCreate($sessionId, '200000010');
var_dump($result);
```

#### Request Example SOAP V2 (WS-I Compliance Mode)

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl'); 
$sessionId = $proxy->login((object)['username' => 'apiUser', 'apiKey' => 'apiKey']); 
 
$result = $proxy->salesOrderCreditmemoCreate((object)['sessionId' => $sessionId->result, 'creditmemoIncrementId' => '200000010', 
'creditmemoData' => [
    'qtys' => [
    'order_item_id' => 3,
    'qty' => '1'],
    'shipping_amount' => null,
    'adjustment_positive' => '0',
    'adjustment_negative' => null],
    'comment' => 'comment for credit memo',
    'notifyCustomer' => null,
    'includeComment' => 1,
    'refundToStoreCreditAmount' => '1'
]);   
var_dump($result->result);
```

## AddComment

### Method

- `order_creditmemo.addComment` (SOAP V1)
- `salesOrderCreditmemoAddComment` (SOAP V2)

Allows you to add a new comment to an existing credit memo.
Email notification can be sent to the user email.

### Arguments

| Type   | Name                  | Description                                                 |
|--------|-----------------------|-------------------------------------------------------------|
| string | sessionId             | Session ID                                                  |
| string | creditmemoIncrementId | Credit memo increment ID                                    |
| string | comment               | Comment text (optional)                                     |
| int    | notifyCustomer        | Notify customer by email flag (optional)                    |
| int    | includeComment        | Include comment text into the email notification (optional) |

### Return

| Type       | Description                                         |
|------------|-----------------------------------------------------|
| booleanint | True (1) if the comment is added to the credit memo |

### Faults

| Fault Code | Fault Message                                 |
|------------|-----------------------------------------------|
| 100        | Requested credit memo does not exist.         |
| 102        | Invalid data given. Details in error message. |

### Examples

#### Request Example SOAP V1

```php
$proxy = new SoapClient('https://mahohost/api/soap/?wsdl');
$sessionId = $proxy->login('apiUser', 'apiKey');

$creditmemoIncrementId = '200000001'; // increment id of existing credit memo
$commentText = "Credit memo comment successfully added";

$isCommentAdded = $proxy->call($sessionId, 'order_creditmemo.addComment', [$creditmemoIncrementId, $commentText, true]);
```

#### Request Example SOAP V2

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl'); // replace with your store's WSDL URL
$sessionId = $proxy->login('apiUser', 'apiKey'); // replace with your API user and key

$result = $proxy->salesOrderCreditmemoAddComment($sessionId, '200000001');
var_dump($result);
```

#### Request Example SOAP V2 (WS-I Compliance Mode)

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl'); 
$sessionId = $proxy->login((object)['username' => 'apiUser', 'apiKey' => 'apiKey']); 
 
$result = $proxy->salesOrderCreditmemoAddComment((object)['sessionId' => $sessionId->result, 'creditmemoIncrementId' => '200000001', 'comment' => 'credit memo comment', 'notifyCustomer' => 1, 'includeComment' => 1]);   
var_dump($result->result);
```

## Cancel

### Method

- `order_creditmemo.cancel` (SOAP V1)
- `salesOrderCreditmemoCancel` (SOAP V2)

Allows you to cancel an existing credit memo.

### Arguments

| Type   | Name                  | Description              |
|--------|-----------------------|--------------------------|
| string | sessionId             | Session ID               |
| string | creditmemoIncrementId | Credit memo increment ID |

### Return

| Type   | Name   | Description                         |
|--------|--------|-------------------------------------|
| string | result | Result of canceling the credit memo |

### Faults

| Fault Code | Fault Message                         |
|------------|---------------------------------------|
| 100        | Requested credit memo does not exist. |
| 104        | Credit memo status not changed.       |

### Examples

#### Request Example SOAP V1

```php
$proxy = new SoapClient('https://mahohost/api/soap/?wsdl');
$sessionId = $proxy->login('apiUser', 'apiKey');

$creditmemoIncrementId = '100000637'; // increment id of existing credit memo

$isCreditMemoCanceled = $proxy->call($sessionId, 'order_creditmemo.cancel', [$creditmemoIncrementId]);
```
