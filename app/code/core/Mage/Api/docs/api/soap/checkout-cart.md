---
description: Manage shopping carts over the Maho SOAP API - create a cart, retrieve its info and totals, get the license agreement, and place an order.
---

# Checkout Cart

!!! warning "Legacy API"
    This is Maho's legacy SOAP/XML-RPC API, inherited from Magento 1 and kept for backward compatibility. New integrations should use the modern [REST & GraphQL API (v2)](../v2/index.md) instead.

## Introduction

Allows you to manage shopping carts.

### Resource Name

- `cart`

### Methods

- `cart.create` - Create a blank shopping cart.
- `cart.order` - Create an order from a shopping cart.
- `cart.info` - Get full information about the current shopping cart.
- `cart.totals` - Get all available prices for items in shopping cart, using additional parameters.
- `cart.license` - Get website license agreement.

### Faults

| Fault Code | Fault Message                                                                |
|------------|------------------------------------------------------------------------------|
| 1001       | Can not make operation because store is not exists.                          |
| 1002       | Can not make operation because quote is not exists.                          |
| 1003       | Can not create a quote.                                                      |
| 1004       | Can not create a quote because quote with such identifier is already exists. |
| 1005       | You did not set all required agreements.                                     |
| 1006       | The checkout type is not valid. Select single checkout type.                 |
| 1007       | Checkout is not available for guest.                                         |
| 1008       | Can not create an order.                                                     |

### Example

The following example illustrates the work with shopping cart:
- creating a shopping cart
- setting customer and customer addresses
- adding products to the shopping cart
- updating products in the shopping cart
- removing products from the shopping cart
- getting the list of products/shipping methods/payment methods
- setting payment/shipping methods
- adding/removing coupon
- getting total prices/full information about shopping cart/list of licenses
- creating an order

```php
$proxy = new SoapClient('https://mahohost/api/soap/?wsdl');
$sessionId = $proxy->login('apiUser', 'apiKey');

// Create a quote, get quote identifier
$shoppingCartId = $proxy->call($sessionId, 'cart.create', ['maho_store']);

// Set customer (guest)
$customerAsGuest = [
    'firstname' => 'testFirstname',
    'lastname' => 'testLastName',
    'email' => 'testEmail',
    'website_id' => '0',
    'store_id' => '0',
    'mode' => 'guest'
];
$resultCustomerSet = $proxy->call($sessionId, 'cart_customer.set', [ $shoppingCartId, $customerAsGuest] );

// Set customer addresses
$addresses = [
    [
        'mode' => 'shipping',
        'firstname' => 'testFirstname',
        'lastname' => 'testLastname',
        'company' => 'testCompany',
        'street' => 'testStreet',
        'city' => 'testCity',
        'region' => 'testRegion',
        'postcode' => 'testPostcode',
        'country_id' => 'id',
        'telephone' => '0123456789',
        'fax' => '0123456789',
        'is_default_shipping' => 0,
        'is_default_billing' => 0
    ],
    [
        'mode' => 'billing',
        'firstname' => 'testFirstname',
        'lastname' => 'testLastname',
        'company' => 'testCompany',
        'street' => 'testStreet',
        'city' => 'testCity',
        'region' => 'testRegion',
        'postcode' => 'testPostcode',
        'country_id' => 'id',
        'telephone' => '0123456789',
        'fax' => '0123456789',
        'is_default_shipping' => 0,
        'is_default_billing' => 0
    ]
];
$resultCustomerAddresses = $proxy->call($sessionId, 'cart_customer.addresses', [$shoppingCartId, $addresses]);

// Add products to shopping cart
$products = [
    [
        'product_id' => '1',
        'qty' => 2
    ],
    [
        'sku' => 'testSKU',
        'quantity' => 4
    ]
];
$resultCartProductAdd = $proxy->call($sessionId, 'cart_product.add', [$shoppingCartId, $products]);

// Update product in shopping cart
$products = [
    [
        'product_id' => '1',
        'qty' => 5
    ],
];
$resultCartProductUpdate = $proxy->call($sessionId, 'cart_product.update', [$shoppingCartId, $products]);

// Remove products from shopping cart (by SKU)
$products = [
    [
        'sku' => 'testSKU'
    ],
];
$resultCartProductRemove = $proxy->call($sessionId, 'cart_product.remove', [$shoppingCartId, $products]);

// Get the list of products
$shoppingCartProducts = $proxy->call($sessionId, 'cart_product.list', [$shoppingCartId]);
print_r($shoppingCartProducts);

// Get the list of shipping methods
$resultShippingMethods = $proxy->call($sessionId, 'cart_shipping.list', [$shoppingCartId]);
print_r($resultShippingMethods);

// Set shipping method
$randShippingMethodIndex = rand(1, count($resultShippingMethods));
$shippingMethod = $resultShippingMethods[$randShippingMethodIndex]['code'];

$resultShippingMethod = $proxy->call($sessionId, 'cart_shipping.method', [$shoppingCartId, $shippingMethod]);

// Get the list of payment methods
$resultPaymentMethods = $proxy->call($sessionId, 'cart_payment.list', [$shoppingCartId]);
print_r($resultPaymentMethods);

// Set payment method
$paymentMethod = [
    'method' => 'checkmo'
];
$resultPaymentMethod = $proxy->call($sessionId, 'cart_payment.method', [$shoppingCartId, $paymentMethod]);

// Add coupon
$resultCartCouponRemove = $proxy->call($sessionId, 'cart_coupon.add', [$shoppingCartId, 'exampleCouponCode']);

// Remove coupon
$resultCartCouponRemove = $proxy->call($sessionId, 'cart_coupon.remove', [$shoppingCartId]);

// Get total prices
$shoppingCartTotals = $proxy->call($sessionId, 'cart.totals', [$shoppingCartId]);
print_r($shoppingCartTotals);

// Get full information about shopping cart
$shoppingCartInfo = $proxy->call($sessionId, 'cart.info', [$shoppingCartId]);
print_r($shoppingCartInfo);

// Get the list of licenses
$shoppingCartLicenses = $proxy->call($sessionId, 'cart.licenseAgreement', [$shoppingCartId]);
print_r( $shoppingCartLicences );

// Check if license is existed
$licenseForOrderCreation = null;
if (count($shoppingCartLicenses)) {
    $licenseForOrderCreation = [];
    foreach ($shoppingCartLicenses as $license) {
        $licenseForOrderCreation[] = $license['agreement_id'];
    }
}

// Create order
$resultOrderCreation = $proxy->call($sessionId, 'cart.order', [$shoppingCartId, null, $licenseForOrderCreation]);
```

## Create

### Method

- `cart.create` (SOAP V1)
- `shoppingCartCreate` (SOAP V2)

Allows you to create an empty shopping cart.

### Arguments

| Type   | Name      | Description                      |
|--------|-----------|----------------------------------|
| string | sessionId | Session ID                       |
| string | storeId   | Store view ID or code (optional) |

### Returns

| Type | Description                           |
|------|---------------------------------------|
| int  | ID of the created empty shopping cart |

### Faults

_No Faults._

### Examples

#### Request Example SOAP V1

```php
$proxy = new SoapClient('https://mahohost/api/soap/?wsdl');
$sessionId = $proxy->login('apiUser', 'apiKey');

$shoppingCartIncrementId = $proxy->call($sessionId, 'cart.create', ['maho_store']);
```

#### Request Example SOAP V2

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl'); // replace with your store's WSDL URL
$sessionId = $proxy->login('apiUser', 'apiKey'); // replace with your API user and key

$result = $proxy->shoppingCartCreate($sessionId, '3');
var_dump($result);
```

#### Request Example SOAP V2 (WS-I Compliance Mode)

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl');
$sessionId = $proxy->login((object)['username' => 'apiUser', 'apiKey' => 'apiKey']);

$result = $proxy->shoppingCartCreate((object)['sessionId' => $sessionId->result, 'store' => '3']);   
var_dump($result->result);
```

## Order

### Method

- `cart.order` (SOAP V1)
- `shoppingCartOrder` (SOAP V2)

Allows you to create an order from a shopping cart (quote).  
Before placing the order, you need to add the customer, customer address, shipping and payment methods.

### Arguments

| Type          | Name      | Description                      |
|---------------|-----------|----------------------------------|
| string        | sessionId | Session ID                       |
| int           | quoteId   | Shopping Cart ID (quote ID)      |
| string        | storeId   | Store view ID or code (optional) |
| ArrayOfString | licenses  | Website license ID (optional)    |

### Return

| Type   | Name   | Description                 |
|--------|--------|-----------------------------|
| string | result | Result of creating an order |

### Faults

_No Faults._

### Examples

#### Request Example SOAP V1

```php
$proxy = new SoapClient('https://mahohost/api/soap/?wsdl');
$sessionId = $proxy->login('apiUser', 'apiKey');

$shoppingCartIncrementId = $proxy->call($sessionId, 'cart.create', ['maho_store']);

$resultOrderCreation = $proxy->call(
  $sessionId,
  'cart.order',
  [
    $shoppingCartId
  ]
);
```

#### Request Example SOAP V2

```php
/**
 * Example of order creation
 * Preconditions are as follows:
 * 1. Create customer
 * 2. Create a simple product */

$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl');
$sessionId = $proxy->login('apiUser', 'apiKey');
$cartId = $proxy->shoppingCartCreate($sessionId, 1);
// Load the customer list and select the first customer from the list
$customerList = $proxy->customerCustomerList($sessionId, []);
$customer = (array) $customerList[0];
$customer['mode'] = 'customer';
$proxy->shoppingCartCustomerSet($sessionId, $cartId, $customer);
// Load the product list and select the first product from the list
$productList = $proxy->catalogProductList($sessionId);
$product = (array) $productList[0];
$product['qty'] = 1;
$proxy->shoppingCartProductAdd($sessionId, $cartId, [$product]);
$addresses = [
    [
        'mode' => 'shipping',
        'firstname' => $customer['firstname'],
        'lastname' => $customer['lastname'],
        'street' => 'street address',
        'city' => 'city',
        'region' => 'region',
        'telephone' => 'phone number',
        'postcode' => 'postcode',
        'country_id' => 'country ID',
        'is_default_shipping' => 0,
        'is_default_billing' => 0
    ],
    [
        'mode' => 'billing',
        'firstname' => $customer['firstname'],
        'lastname' => $customer['lastname'],
        'street' => 'street address',
        'city' => 'city',
        'region' => 'region',
        'telephone' => 'phone number',
        'postcode' => 'postcode',
        'country_id' => 'country ID',
        'is_default_shipping' => 0,
        'is_default_billing' => 0
    ],
];
// Add customer address
$proxy->shoppingCartCustomerAddresses($sessionId, $cartId, $addresses);
// Add shipping method
$proxy->shoppingCartShippingMethod($sessionId, $cartId, 'flatrate_flatrate');
$paymentMethod = [
    'po_number' => null,
    'method' => 'checkmo',
    'cc_cid' => null,
    'cc_owner' => null,
    'cc_number' => null,
    'cc_type' => null,
    'cc_exp_year' => null,
    'cc_exp_month' => null
];
// Add payment method
$proxy->shoppingCartPaymentMethod($sessionId, $cartId, $paymentMethod);
// Place the order
$orderId = $proxy->shoppingCartOrder($sessionId, $cartId, null, null);
```

## Info

### Method

- `cart.info` (SOAP V1)
- `shoppingCartInfo` (SOAP V2)

Allows you to retrieve full information about the shopping cart (quote).

### Arguments

| Type   | Name      | Description                      |
|--------|-----------|----------------------------------|
| string | sessionId | Session ID                       |
| int    | quoteId   | Shopping cart ID (quote ID)      |
| string | store     | Store view ID or code (optional) |

### Return

| Type  | Name   | Description                     |
|-------|--------|---------------------------------|
| array | result | Array of shoppingCartInfoEntity |

### Content `shoppingCartInfoEntity`

| Type   | Name                              | Description                                  |
|--------|-----------------------------------|----------------------------------------------|
| string | store_id                          | Store ID                                     |
| string | created_at                        | Date of creation                             |
| string | updated_at                        | Date of updating                             |
| string | converted_at                      | Date of conversion                           |
| int    | quote_id                          | Quote ID                                     |
| int    | is_active                         | Active flag                                  |
| int    | is_virtual                        | Defines whether the product is a virtual one |
| int    | is_multi_shipping                 | Defines whether multi shipping is available  |
| double | items_count                       | Items quantity                               |
| double | items_qty                         | Total items quantity                         |
| string | orig_order_id                     | Original order ID                            |
| string | store_to_base_rate                | Store to base rate                           |
| string | store_to_quote_rate               | Store to quote rate                          |
| string | base_currency_code                | Base currency code                           |
| string | store_currency_code               | Store currency code                          |
| string | quote_currency_code               | Quote currency code                          |
| string | grand_total                       | Grand total                                  |
| string | base_grand_total                  | Base grand total                             |
| string | checkout_method                   | Checkout method                              |
| string | customer_id                       | Customer ID                                  |
| string | customer_tax_class_id             | Customer tax class ID                        |
| int    | customer_group_id                 | Customer group ID                            |
| string | customer_email                    | Customer email address                       |
| string | customer_prefix                   | Customer prefix                              |
| string | customer_firstname                | Customer first name                          |
| string | customer_middlename               | Customer middle name                         |
| string | customer_lastname                 | Customer last name                           |
| string | customer_suffix                   | Customer suffix                              |
| string | customer_note                     | Customer note                                |
| string | customer_note_notify              | Customer notification flag                   |
| string | customer_is_guest                 | Defines whether the customer is a guest      |
| string | applied_rule_ids                  | Applied rule IDs                             |
| string | reserved_order_id                 | Reserved order ID                            |
| string | password_hash                     | Password hash                                |
| string | coupon_code                       | Coupon code                                  |
| string | global_currency_code              | Global currency code                         |
| double | base_to_global_rate               | Base to global rate                          |
| double | base_to_quote_rate                | Base to quote rate                           |
| string | customer_taxvat                   | Customer taxvat value                        |
| string | customer_gender                   | Customer gender                              |
| double | subtotal                          | Subtotal                                     |
| double | base_subtotal                     | Base subtotal                                |
| double | subtotal_with_discount            | Subtotal with discount                       |
| double | base_subtotal_with_discount       | Base subtotal with discount                  |
| string | ext_shipping_info                 |                                              |
| string | gift_message_id                   | Gift message ID                              |
| string | gift_message                      | Gift message                                 |
| double | customer_balance_amount_used      | Used customer balance amount                 |
| double | base_customer_balance_amount_used | Used base customer balance amount            |
| string | use_customer_balance              | Defines whether to use the customer balance  |
| string | gift_cards_amount                 | Gift cards amount                            |
| string | base_gift_cards_amount            | Base gift cards amount                       |
| string | gift_cards_amount_used            | Used gift cards amount                       |
| string | use_reward_points                 | Defines whether to use reward points         |
| string | reward_points_balance             | Reward points balance                        |
| string | base_reward_currency_amount       | Base reward currency amount                  |
| string | reward_currency_amount            | Reward currency amount                       |
| array  | shipping_address                  | Array of shoppingCartAddressEntity           |
| array  | billing_address                   | Array of shoppingCartAddressEntity           |
| array  | items                             | Array of shoppingCartItemEntity              |
| array  | payment                           | Array of shoppingCartPaymentEntity           |

### Content `shoppingCartAddressEntity`

| Type   | Name                 | Description                                                |
|--------|----------------------|------------------------------------------------------------|
| string | address_id           | Shopping cart address ID                                   |
| string | created_at           | Date of creation                                           |
| string | updated_at           | Date of updating                                           |
| string | customer_id          | Customer ID                                                |
| int    | save_in_address_book | Defines whether to save the address in the address book    |
| string | customer_address_id  | Customer address ID                                        |
| string | address_type         | Address type                                               |
| string | email                | Email address                                              |
| string | prefix               | Customer prefix                                            |
| string | firstname            | Customer first name                                        |
| string | middlename           | Customer middle name                                       |
| string | lastname             | Customer last name                                         |
| string | suffix               | Customer suffix                                            |
| string | company              | Company name                                               |
| string | street               | Street                                                     |
| string | city                 | City                                                       |
| string | region               | Region                                                     |
| string | region_id            | Region ID                                                  |
| string | postcode             | Postcode                                                   |
| string | country_id           | Country ID                                                 |
| string | telephone            | Telephone number                                           |
| string | fax                  | Fax                                                        |
| int    | same_as_billing      | Defines whether the address is the same as the billing one |
| int    | free_shipping        | Defines whether free shipping is used                      |
| string | shipping_method      | Shipping method                                            |
| string | shipping_description | Shipping description                                       |
| double | weight               | Weight                                                     |

### Content `shoppingCartItemEntity`

| Type   | Name                             | Description                                           |
|--------|----------------------------------|-------------------------------------------------------|
| string | item_id                          | Cart item ID                                          |
| string | created_at                       | Date of creation                                      |
| string | updated_at                       | Date of updating                                      |
| string | product_id                       | Product ID                                            |
| string | store_id                         | Store ID                                              |
| string | parent_item_id                   | Parent item ID                                        |
| int    | is_virtual                       | Defines whether the product is a virtual one          |
| string | sku                              | Product SKU                                           |
| string | name                             | Product name                                          |
| string | description                      | Description                                           |
| string | applied_rule_ids                 | Applied rule IDs                                      |
| string | additional_data                  | Additional data                                       |
| string | free_shipping                    | Free shipping                                         |
| string | is_qty_decimal                   | Defines whether the quantity is decimal               |
| string | no_discount                      | Defines whether no discount is applied                |
| double | weight                           | Weight                                                |
| double | qty                              | Quantity                                              |
| double | price                            | Price                                                 |
| double | base_price                       | Base price                                            |
| double | custom_price                     | Custom price                                          |
| double | discount_percent                 | Discount percent                                      |
| double | discount_amount                  | Discount amount                                       |
| double | base_discount_amount             | Base discount amount                                  |
| double | tax_percent                      | Tax percent                                           |
| double | tax_amount                       | Tax amount                                            |
| double | base_tax_amount                  | Base tax amount                                       |
| double | row_total                        | Row total                                             |
| double | base_row_total                   | Base row total                                        |
| double | row_total_with_discount          | Row total with discount                               |
| double | row_weight                       | Row weight                                            |
| string | product_type                     | Product type                                          |
| double | base_tax_before_discount         | Base tax before discount                              |
| double | tax_before_discount              | Tax before discount                                   |
| double | original_custom_price            | Original custom price                                 |
| double | base_cost                        | Base cost                                             |
| double | price_incl_tax                   | Price including tax                                   |
| double | base_price_incl_tax              | Base price including tax                              |
| double | row_total_incl_tax               | Row total including tax                               |
| double | base_row_total_incl_tax          | Base row total including tax                          |
| string | gift_message_id                  | Gift message ID                                       |
| string | gift_message                     | Gift message                                          |
| string | gift_message_available           | Defines whether the gift message is available         |
| double | weee_tax_applied                 | Applied fix product tax                               |
| double | weee_tax_applied_amount          | Applied fix product tax amount                        |
| double | weee_tax_applied_row_amount      | Applied fix product tax row amount                    |
| double | base_weee_tax_applied_amount     | Applied fix product tax amount (in base currency)     |
| double | base_weee_tax_applied_row_amount | Applied fix product tax row amount (in base currency) |
| double | weee_tax_disposition             | Fixed product tax disposition                         |
| double | weee_tax_row_disposition         | Fixed product tax row disposition                     |
| double | base_weee_tax_disposition        | Fixed product tax disposition (in base currency)      |
| double | base_weee_tax_row_disposition    | Fixed product tax row disposition (in base currency)  |
| string | tax_class_id                     | Tax class ID                                          |

### Content `shoppingCartPaymentEntity`

| Type   | Name                   | Description                            |
|--------|------------------------|----------------------------------------|
| string | payment_id             | Payment ID                             |
| string | created_at             | Date of creation                       |
| string | updated_at             | Date of updating                       |
| string | method                 | Payment method                         |
| string | cc_type                | Credit card type                       |
| string | cc_number_enc          | Credit card number                     |
| string | cc_last4               | Last four digits on the credit card    |
| string | cc_cid_enc             | Credit card CID                        |
| string | cc_owner               | Credit card owner                      |
| string | cc_exp_month           | Credit card expiration month           |
| string | cc_exp_year            | Credit card expiration year            |
| string | cc_ss_owner            | Credit card owner (Switch/Solo)        |
| string | cc_ss_start_month      | Credit card start month (Switch/Solo)  |
| string | cc_ss_start_year       | Credit card start year (Switch/Solo)   |
| string | cc_ss_issue            | Credit card issue number (Switch/Solo) |
| string | po_number              | Purchase order number                  |
| string | additional_data        | Additional data                        |
| string | additional_information | Additional information                 |

### Examples

#### Request Example SOAP V1

```php
$client = new SoapClient('https://mahohost/api/soap/?wsdl');
$session = $client->login('apiUser', 'apiKey');

$result = $client->call($session, 'cart.info', '15');
var_dump($result);
```

#### Request Example SOAP V2

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl'); // replace with your store's WSDL URL
$sessionId = $proxy->login('apiUser', 'apiKey'); // replace with your API user and key

$result = $proxy->shoppingCartInfo($sessionId, '15');
var_dump($result);
```

#### Request Example SOAP V2 (WS-I Compliance Mode)

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl'); 
$sessionId = $proxy->login((object)['username' => 'apiUser', 'apiKey' => 'apiKey']); 
 
$result = $proxy->shoppingCartInfo((object)['sessionId' => $sessionId->result, 'quoteId' => '15']);   
var_dump($result->result);
```

## Totals

### Method

- `cart.totals` (SOAP V1)
- `shoppingCartTotals` (SOAP V2)

Allows you to retrieve total prices for a shopping cart (quote).

### Arguments

| Type   | Name      | Description                         |
|--------|-----------|-------------------------------------|
| string | sessionId | Session ID                          |
| int    | quoteId   | Shopping cart ID (quote identifier) |
| string | store     | Store view ID or code (optional)    |

### Return

| Type  | Name   | Description                       |
|-------|--------|-----------------------------------|
| array | result | Array of shoppingCartTotalsEntity |

### Content `shoppingCartTotalsEntity`

| Type   | Name   | Description  |
|--------|--------|--------------|
| string | title  | Title        |
| float  | amount | Total amount |

### Examples

#### Request Example SOAP V1

```php
$client = new SoapClient('https://mahohost/api/soap/?wsdl');
$session = $client->login('apiUser', 'apiKey');

$result = $client->call($session, 'cart.totals', '15');
var_dump($result);
```

#### Request Example SOAP V2

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl'); // replace with your store's WSDL URL
$sessionId = $proxy->login('apiUser', 'apiKey'); // replace with your API user and key

$result = $proxy->shoppingCartTotals($sessionId, '15');
var_dump($result);
```

#### Request Example SOAP V2 (WS-I Compliance Mode)

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl'); 
$sessionId = $proxy->login((object)['username' => 'apiUser', 'apiKey' => 'apiKey']); 
 
$result = $proxy->shoppingCartTotals((object)['sessionId' => $sessionId->result, 'quoteId' => 15]);   
var_dump($result->result);
```

#### Response Example SOAP V1

```php
array
  0 =>
    array
      'title' => string 'Subtotal' (length=8)
      'amount' => float 388.69
  1 =>
    array
      'title' => string '0 Reward points' (length=15)
      'amount' => float 0
  2 =>
    array
      'title' => string 'Gift Cards' (length=10)
      'amount' => float 0
  3 =>
    array
      'title' => string 'Store Credit' (length=12)
      'amount' => float 0
  4 =>
    array
      'title' => string 'Grand Total' (length=11)
      'amount' => float 388.69
  5 =>
    array
      'title' => null
      'amount' => null
```

## License

### Method

- `cart.license` (SOAP V1)
- `shoppingCartLicense` (SOAP V2)

### Alias

- `cart.license`

Allows you to retrieve the website license agreement for the quote according to the website (store).

### Arguments

| Type   | Name      | Description                         |
|--------|-----------|-------------------------------------|
| string | sessionId | Session ID                          |
| int    | quoteId   | Shopping cart ID (quote identifier) |
| string | store     | Store view ID or code (optional)    |

### Return

| Type  | Name   | Description                        |
|-------|--------|------------------------------------|
| array | result | Array of shoppingCartLicenseEntity |

### Content `shoppingCartLicenseEntity`

| Type   | Name         | Description                           |
|--------|--------------|---------------------------------------|
| string | agreement_id | License agreement ID                  |
| string | name         | License name                          |
| string | content      | License content                       |
| int    | is_active    | Defines whether the license is active |
| int    | is_html      | Defines whether the license is HTML   |

### Faults

_No Faults._

### Examples

#### Request Example SOAP V1

```php
$proxy = new SoapClient('https://mahohost/api/soap/?wsdl');
$sessionId = $proxy->login('apiUser', 'apiKey');

$result = $client->call($session, 'cart.license', '15');
var_dump($result);
```

#### Request Example SOAP V2

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl'); // replace with your store's WSDL URL
$sessionId = $proxy->login('apiUser', 'apiKey'); // replace with your API user and key

$result = $proxy->shoppingCartLicense($sessionId, '15');
var_dump($result);
```

#### Request Example SOAP V2 (WS-I Compliance Mode)

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl'); 
$sessionId = $proxy->login((object)['username' => 'apiUser', 'apiKey' => 'apiKey']); 
 
$result = $proxy->shoppingCartLicense((object)['sessionId' => $sessionId->result, 'quoteId' => 15]);   
var_dump($result->result);
```

#### Response Example SOAP V1

```php
array
  0 =>
    array
      'agreement_id' => string '1' (length=1)
      'name' => string 'license' (length=4)
      'content' => string 'terms and conditions' (length=20)
      'content_height' => null
      'checkbox_text' => string 'terms' (length=5)
      'is_active' => string '1' (length=1)
      'is_html' => string '0' (length=1)
```
