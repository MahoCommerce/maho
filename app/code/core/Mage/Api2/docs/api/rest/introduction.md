---
description: Overview of Maho's legacy REST API - available resources, OAuth authentication, output formats, request structure, and PHP examples.
---

# REST API

!!! warning "Legacy API"
    This is Maho's legacy REST API, inherited from Magento 1 and kept for backward compatibility. New integrations should use the modern [REST & GraphQL API (v2)](../v2/index.md) instead.

Maho REST API allows managing a number of features, namely:

-   Managing customers.
-   Managing customer addresses.
-   Managing products.
-   Retrieving sales orders.
-   Managing inventory.

## Authentication

Maho REST API uses 3-legged [OAuth 1.0a](http://tools.ietf.org/html/rfc5849) protocol to authenticate the application to access the Maho service.

## Output Formats

The REST API supports the response in two formats, which are XML and JSON.

## HTTP Verbs

HTTP verbs are used to manage the state of resources. In Maho REST API, there are four verbs used to manage resources: GET, POST, PUT, and DELETE. You can get the contents of the data using HTTP GET, delete the data using HTTP DELETE, and create or update the data using POST/PUT.

## Request Structure

All URLs in REST API have the following base URL. 

https://mahohost/api/rest/

**Example**

Supposing, you want to retrieve the list of customers from Maho. To do this, you need to use the GET HTTP method. The GET request to retrieve the list of customers will look as follows:

https://mahohost/api/rest/customers

where

-   https://mahohost/api/rest/ - endpoint
-   /customers - action URL

## REST Resources

The Maho REST API allows you to manage customers, customer addresses, sales orders, inventory, and products. REST API is organized into the following categories:

### Products

Retrieve the list of products, create, update, and delete a product.  
**Resource Structure**: https://mahohost/api/rest/products

#### Product Categories

Retrieve the list of categories assigned to a product, assign, and unassign the category to/from the specific product.  
**Resource Structure**: https://mahohost/api/rest/products/:productId/categories

#### Product Images

Retrieve the list of images assigned to a product, add, update, and remove an image to/from the specific product.  
**Resource Structure**: https://mahohost/api/rest/products/:productId/images

#### Product Websites

Retrieve the list of websites assigned to a product, assign, and unassign a website to/from the specific product.  
**Resource Structure**: https://mahohost/api/rest/products/:productId/websites

### Customers

Retrieve the list of customers, create, delete a customer, and update the customer information.  
**Resource Structure**: https://mahohost/api/rest/customers

### Customer Addresses

Retrieve the list of customer addresses, create, update, and delete the customer address.  
**Resource Structure**: https://mahohost/api/rest/customers/:customerId/addresses

### Inventory

Retrieve the list of stock items and update required stock items.  
**Resource Structure**: https://mahohost/api/rest/stockitems

### Sales Orders

Retrieve the list of sales orders as well as the specific order information.  
**Resource Structure**: https://mahohost/api/rest/orders

#### Order Items

Retrieve order items for the specific order.  
**Resource Structure**: https://mahohost/api/rest/orders/:orderId/items

#### Order Addresses

Retrieve information on order billing and shipping addresses for the specific order.  
**Resource Structure**: https://mahohost/api/rest/orders/:orderid/addresses

#### Order Comments

Retrieve order comments for the specific order  
**Resource Structure**: https://mahohost/api/rest/orders/:orderid/comments

## Preparing for REST API

These steps are required for utilizing REST API resources:

1.  Enable the `legacy_rest` protocol, which is off by default (see [Legacy API Protocols](../legacy-protocols.md)).
2.  Set up permissions for REST resource operations from Maho Admin Panel.
3.  Configure the attributes for different users types in Maho Admin Panel. There are 3 different types of users in accessing the data: Admin, Customer, and Guest. Admin is the backend logged in user, Customer is the fronted logged in user, and Guest is a non-logged in fronted user.

## Preparing REST API for the Third-Party Application

1.  Register the third-party application (Consumer) in Maho Admin Panel.
2.  The third-party application will utilize the provided consumer credentials to call Maho store for getting the access token to access the data.

## PHP Examples

### Create a simple product as an Admin user with OAuth authentication

```php
<?php
/**
 * Example of simple product POST using Admin account via Maho REST API. OAuth authorization is used.
 * 
 * This file is a stand-alone OAuth client PHP file, which handles everything with the OAuth three-legged authentication. 
 * It uses PHP session to persist the current authentication state (step), the OAuth request token with its secret 
 * and the OAuth access token with its secret. 
 * 
 * Create this file oauth_admin.php in your Maho instance root folder to run this oauth authentication example.         
 * 
 * oauth_admin.php
 * 
 */

$callbackUrl = "http://yourhost/oauth_admin.php";
$temporaryCredentialsRequestUrl = "https://mahohost/oauth/initiate?oauth_callback=" . urlencode($callbackUrl);
$adminAuthorizationUrl = 'https://mahohost/admin/oauth_authorize';
$accessTokenRequestUrl = 'https://mahohost/oauth/token';
$apiUrl = 'https://mahohost/api/rest';
$consumerKey = 'yourconsumerkey';
$consumerSecret = 'yourconsumersecret';

session_start();
if (!isset($_GET['oauth_token']) && isset($_SESSION['state']) && $_SESSION['state'] == 1) {
    $_SESSION['state'] = 0;
}
try {
    $authType = ($_SESSION['state'] == 2) ? OAUTH_AUTH_TYPE_AUTHORIZATION : OAUTH_AUTH_TYPE_URI;
    $oauthClient = new OAuth($consumerKey, $consumerSecret, OAUTH_SIG_METHOD_HMACSHA1, $authType);
    $oauthClient->enableDebug();

    if (!isset($_GET['oauth_token']) && !$_SESSION['state']) {
        // step 1 (state 1) - Get the initial temporary request token.
        $requestToken = $oauthClient->getRequestToken($temporaryCredentialsRequestUrl);
        
        // persist the request token secret in the session variable for step 3 to get the access token and secret.
        $_SESSION['secret'] = $requestToken['oauth_token_secret'];
        $_SESSION['state'] = 1;
        
        // step 2 (state 2) - redirect to the Oauth admin authorization url to validate and confirm / reject the admin Oauth authorization request.  
        // variable $requestToken['oauth_token'] has the temporary request token
        header('Location: ' . $adminAuthorizationUrl . '?oauth_token=' . $requestToken['oauth_token']);
        exit;
    } elseif ($_SESSION['state'] == 1) {
        //step 3 (state 3) Exchange the temporary request token and secret to get the final access token and secret.
        $oauthClient->setToken($_GET['oauth_token'], $_SESSION['secret']);
        $accessToken = $oauthClient->getAccessToken($accessTokenRequestUrl);
        
        // persist the access token and its secret in the session variable
        $_SESSION['state'] = 2;
        $_SESSION['token'] = $accessToken['oauth_token'];
        $_SESSION['secret'] = $accessToken['oauth_token_secret'];
        
        // redirect back to the callback url which is the same file
        header('Location: ' . $callbackUrl);
        exit;
    } else {
        
        // send a POST request to create a simple product  
        $oauthClient->setToken($_SESSION['token'], $_SESSION['secret']);
        $resourceUrl = "$apiUrl/products";
        $productData = json_encode(array(
            'type_id'           => 'simple',
            'attribute_set_id'  => 4,
            'sku'               => 'simple' . uniqid(),
            'weight'            => 1,
            'status'            => 1,
            'visibility'        => 4,
            'name'              => 'Simple Product',
            'description'       => 'Simple Description',
            'short_description' => 'Simple Short Description',
            'price'             => 99.95,
            'tax_class_id'      => 0,
        ));
        $headers = array('Content-Type' => 'application/json');
        $oauthClient->fetch($resourceUrl, $productData, OAUTH_HTTP_METHOD_POST, $headers);
        print_r($oauthClient->getLastResponseInfo());
    }
} catch (OAuthException $e) {
    print_r($e);
}
```

### Retrieve the list of products as a Customer user with OAuth authentication

```php
<?php
/**
 * Example of products list retrieve using Customer account via Maho REST API. OAuth authorization is used
 * 
 * This file is a stand-alone Oauth client PHP file, which handles everything with the Oauth three-legged authentication. 
 * It uses PHP session to persist the current authentication state (step), the oauth request token with its secret 
 * and the oauth access token with its secret. 
 * 
 * Create this file oauth_customer in your Maho instance root folder to run this oauth authentication example.         
 * 
 * oauth_customer.php
 * 
 */ 
$callbackUrl = "http://yourhost/oauth_customer.php";
$temporaryCredentialsRequestUrl = "https://mahohost/oauth/initiate?oauth_callback=" . urlencode($callbackUrl);
$adminAuthorizationUrl = 'https://mahohost/oauth/authorize';
$accessTokenRequestUrl = 'https://mahohost/oauth/token';
$apiUrl = 'https://mahohost/api/rest';
$consumerKey = 'yourconsumerkey';
$consumerSecret = 'yourconsumersecret';

session_start();
if (!isset($_GET['oauth_token']) && isset($_SESSION['state']) && $_SESSION['state'] == 1) {
    $_SESSION['state'] = 0;
}
try {
    $authType = ($_SESSION['state'] == 2) ? OAUTH_AUTH_TYPE_AUTHORIZATION : OAUTH_AUTH_TYPE_URI;
    $oauthClient = new OAuth($consumerKey, $consumerSecret, OAUTH_SIG_METHOD_HMACSHA1, $authType);
    $oauthClient->enableDebug();

    if (!isset($_GET['oauth_token']) && !$_SESSION['state']) {
        // step 1 (state 1) - Get the initial temporary request token.
        $requestToken = $oauthClient->getRequestToken($temporaryCredentialsRequestUrl);
        
        // persist the request token secret in the session variable for step 3 to get the access token and secret.
        $_SESSION['secret'] = $requestToken['oauth_token_secret'];
        $_SESSION['state'] = 1;
        
        // step 2 (state 2) - redirect to the Oauth customer authorization url.  
        // variable $requestToken['oauth_token'] has the temporary request token
        header('Location: ' . $adminAuthorizationUrl . '?oauth_token=' . $requestToken['oauth_token']);
        exit;
    } elseif ($_SESSION['state'] == 1) {
        //step 3 (state 3) Exchange the temporary request token and secret to get the final access token and secret.
        $oauthClient->setToken($_GET['oauth_token'], $_SESSION['secret']);
        $accessToken = $oauthClient->getAccessToken($accessTokenRequestUrl);
        
        // persist the access token and its secret in the session variable to access the products resource
        $_SESSION['state'] = 2;
        $_SESSION['token'] = $accessToken['oauth_token'];
        $_SESSION['secret'] = $accessToken['oauth_token_secret'];
        
        // redirect back to the callback url which is the same file
        header('Location: ' . $callbackUrl);
        exit;
    } else {
        //  send a GET request to list all the products
        $oauthClient->setToken($_SESSION['token'], $_SESSION['secret']);
        $resourceUrl = "$apiUrl/products";
        $oauthClient->fetch($resourceUrl, array(), 'GET', array('Content-Type' => 'application/json', 'Accept' => '*/*'));
        $productsList = json_decode($oauthClient->getLastResponse());
        print_r($productsList);
    }
} catch (OAuthException $e) {
    print_r($e);
}
```

## API Client Example

Retrieving the list of Products as a Guest

1.  Using an API client such as Postman, Insomnia, or Bruno, set the request **Method** to **GET**.
2.  In the URL field, enter the following URL: https://mahohost/api/rest/products?limit=2.
3.  Click **Send**. Information about the products will be displayed in the response body. Example in the XML format is as follows:

**Example: XML**

```xml
<?xml version="1.0"?>
<magento_api>
    <data_item>
        <entity_id>16</entity_id>
        <type_id>simple</type_id>
        <sku>n2610</sku>
        <description>The Nokia 2610 is an easy to use device that combines multiple messaging options including email, instant messaging, and more. You can even download MP3 ringtones, graphics, and games straight to the phone, or surf the Internet with Cingular's MEdia Net service. It's the perfect complement to Cingular service for those even remotely interested in mobile Web capabilities in an affordable handset.
        </description>
        <meta_keyword>Nokia 2610, cell, phone, </meta_keyword>
        <short_description>The words "entry level" no longer mean "low-end," especially when it comes to the Nokia 2610. Offering advanced media and calling features without breaking the bank</short_description>
        <name>Nokia 2610 Phone</name>
        <meta_title>Nokia 2610</meta_title>
        <meta_description>Offering advanced media and calling features without breaking the bank, The Nokia 2610 is an easy to use</meta_description>
        <regular_price_with_tax>149.99</regular_price_with_tax>
        <regular_price_without_tax>149.99</regular_price_without_tax>
        <final_price_with_tax>149.99</final_price_with_tax>
        <final_price_without_tax>149.99</final_price_without_tax>
        <is_saleable>1</is_saleable>
        <image_url>https://mahohost/imageulr/nokia.jpg</image_url>
    </data_item>
    <data_item>
        <entity_id>17</entity_id>
        <type_id>simple</type_id>
        <sku>bb8100</sku>
        <description> Like the BlackBerry 7105t, the BlackBerry 8100 Pearl is
        The BlackBerry 8100 Pearl sports a large 240 x 260 screen that supports over 65,000 colors-- plenty of real estate to view your e-mails, Web browser content, messaging sessions, and attachments. The venerable BlackBerry trackwheel has been replaced on this model with an innovative four-way trackball placed below the screen. On the rear of the handheld, you'll find a 1.3-megapixel camera and a self portrait mirror. The handheld's microSD memory card slot is located inside the device, behind the battery. There's also a standard 2.5mm headset jack that can be used with the included headset, as well as a mini-USB port for data connectivity.</description>
        <meta_keyword>Blackberry, 8100, pearl, cell, phone</meta_keyword>
        <short_description>The BlackBerry 8100 Pearl is a departure from the form factor of previous BlackBerry devices. This BlackBerry handset is far more phone-like, and RIM's engineers have managed to fit a QWERTY keyboard onto the handset's slim frame.</short_description>
        <name>BlackBerry 8100 Pearl</name>
        <meta_title>BlackBerry 8100 Pearl</meta_title>
        <meta_description>BlackBerry 8100 Pearl sports a large 240 x 260 screen that supports over 65,000 colors-- plenty of real estate to view your e-mails, Web browser content, messaging sessions, and attachments.</meta_description>
        <regular_price_with_tax>349.99</regular_price_with_tax>
        <regular_price_without_tax>349.99</regular_price_without_tax>
        <final_price_with_tax>349.99</final_price_with_tax>
        <final_price_without_tax>349.99</final_price_without_tax>
        <is_saleable>1</is_saleable>
        <image_url>https://mahohost/imageulr/blackberry.jpg</image_url>
    </data_item>
</magento_api>
```

## Additional Information

You can define the limit of items returned in the response by passing the limit parameter. By default, 10 items are returned and the maximum number is 100 items. You can also define the page number by passing the page parameter. Example:

https://mahohost/api/rest/products?page=2&limit=20

Authorization header will be required for Admin and Customer user types. The following parameters must be provided in the Authorization header for the call:

-   oauth_consumer_key - the Consumer Key value provided after the registration of the application.
-   oauth_nonce - a random value, uniquely generated by the application.
-   oauth_signature_method - name of the signature method used to sign the request. Can have one of the following values: HMAC-SHA1, RSA-SHA1, and PLAINTEXT.
-   oauth_signature - a generated value (signature).
-   oauth_timestamp - a positive integer, expressed in the number of seconds since January 1, 1970 00:00:00 GMT.
-   oauth_token - the oauth_token value (Request Token).
-   oauth_version - OAuth version.