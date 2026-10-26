---
description: Test Maho's legacy REST API resources with an API client such as Postman, Insomnia, or Bruno, including OAuth-authenticated example calls.
---

# Testing REST Resources

!!! warning "Legacy API"
    This is Maho's legacy REST API, inherited from Magento 1 and kept for backward compatibility. New integrations should use the modern [REST & GraphQL API (v2)](../v2/index.md) instead.

## Introduction

Three steps are required for utilizing REST API resources:

-   [Authenticate the user](authentication/oauth_authentication.md "OAuth Authentication") (receive the access token for further steps);
-   Configure the [permissions for operations](permission_settings/roles_configuration.md "REST Roles Configuration") and [attributes](permission_settings/attributes_configuration.md "REST Attributes Configuration") for the type of the user;
-   Make an API call.

The following headers are required for the call:

-   Authorization
-   Version
-   Accept
-   Content-type

The following parameters must be provided in the Authorization header for the call:

-   oauth_signature_method
-   oauth_version
-   oauth_nonce
-   oauth_timestamp
-   oauth_consumer_key
-   oauth_token
-   oauth_signature

## Testing REST resources with an API client (Postman, Insomnia, or Bruno)

1.  Open your API client.
2.  From the Authentication drop-down, select **OAuth**.
3.  In the OAuth window, on the Signature for the request tab, fill in the following fields:  
    -   **Consumer key**: Enter the **Key** value provided when you created the consumer in Maho Admin Panel.
    -   **Consumer secret**: Enter the **Secret** value provided when you created the consumer in Maho Admin Panel.
    -   **Access token**: Enter the oauth_token value received when you authenticated the application.
    -   **Access token secret**: Enter the oauth_token_secret value received when you authenticated the application.
4.  On the OAuth setting tab, define the following options:
    -   **Signature Methods**: From the drop-down list, select which method will be used for signatures (HMAC-SHA1 or PLAINTEXT).
    -   **oAuth Version**: From the drop-down list, select the **1.0** option (REST API supports OAuth 1.0a).
    -   Leave the **Realm**, **oAuth Nonce**, and **oAuth Timestamp** values set by default.
5.  Click **Save** and wait for the confirmation dialog to close.
6.  Return to the Signature for the request tab and select **Insert > Insert as header**.  
    An authorization header is created on the main page of your API client.  
    **NOTE**: Click the header with authorization data and click **Auto refresh** in the opened pop-up in order to generate new values for oauth_nonce, oauth_timestamp, and oauth_signature at each request.
7.  From the **Headers** drop-down, select **Custom Header**.  
8.  In the **Request Header** window, enter "Content-Type" in the **Name** field and "text/xml" in the **Value** field (if you want to use the XML data format). To use the JSON request data format, enter application/json instead of the text/xml value.
9.  Click **Okay**.

### Example: Retrieving the List of Products

1.  From the **Method** drop-down list, select the **GET** option.
2.  In the **URL** field, enter the following URL: https://mahohost/api/rest/products. You can limit the number of products returned in the response. To set the limit to 4, enter the following URL: https://mahohost/api/rest/products?limit=4
3.  Click **Send**. Information about all products will be displayed in the response body.

## Testing REST resources with the Advanced REST Client

1.  Open the Advanced REST Client Application.  
2.  In the **Headers** box, select the **Form** tab.
3.  In the first field, start typing _authorization_. An **Authorization** popup appears. Click it.
4.  When you click the fields next to the Authorization header, the **Construct** link appears. Click it to configure OAuth authentication.
5.  The Authorization window opens. Select the OAuth tab.
6.  In the **Type** group of options, select the **Signed Request** option.
7.  In the **signature method** group of options, select which method will be used for signatures (HMAC-SHA1 or PLAINTEXT).
8.  Fill in the following data:
    -   **Consumer key**: Enter the **Key** value provided when you created the consumer in Maho Admin Panel.
    -   **Consumer secret**: Enter the **Secret** value provided when you created the consumer in Maho Admin Panel.
    -   **Access Token**: Enter the oauth_token value received when you authenticated the application.
    -   **Access Token Secret**: Enter the oauth_token_secret value received when you authenticated the application.
9.  Click **OK**.  
    **NOTE**: Advanced REST Client does not save the **Consumer secret** and **Access Token Secret** values. You need to enter these values each time you make a request.
10.  In the **URL** field, enter the URL to which the API call will be performed and select the required HTTP method.
11.  In the **Headers** table, click **Add row** and add the Accept - application/json or Accept - text/xml header depending on which format you prefer for the returned data.
12.  Click **Send Request**.

### Example: Retrieving the list of customers

1.  In the **Method** group of options, select the **GET** option.
2.  In the URL field, enter the following URL: https://mahohost/api/rest/customers.
3.  Click **Send request**. Information about all customers will be displayed in the response body. Note that only Admin type of the user can retrieve the list of customers.

### Example: Creating a customer address

1.  In the **Method** group of options, select the **POST** option.
2.  In the URL field, enter the following URL: https://mahohost/api/rest/customers/:id/addresses where the ":id" value is the customer ID in the system.
3.  In the **Body** table, on the **Raw input** tab, enter the data required for customer address creation.
4.  Click **Send request**. If the address is created, the 200 OK HTTP status code will be returned.