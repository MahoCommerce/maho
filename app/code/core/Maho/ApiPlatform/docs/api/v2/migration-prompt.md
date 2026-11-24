---
description: A prompt for an AI coding agent that moves the code of an integration from the legacy Maho or Magento 1 REST, SOAP and XML-RPC APIs to the Maho v2 API.
---

# Migration prompt for AI agents <span class="version-badge">v26.7+</span>

An AI coding agent can move the code of an integration from the legacy APIs to the v2 API. The prompt below tells the agent what to search for, where to find the v2 counterpart of each call, and how to test the result. It uses the [migration guide](migrate-from-legacy-api.md) and the OpenAPI document of your store.

## Before you start

1. Make a test copy of the store. The agent sends requests to it.
2. Turn on **REST v2 (new)** in **System > Configuration > Services > API > API Protocols**. Keep the legacy protocol on.
3. Under **System > API > Roles**, create a role with the resources that the integration uses.
4. Under **System > API > Users**, create a user with this role. Copy the client ID and the client secret.
5. Put the client ID and the client secret in environment variables. Do not put them in the prompt.

## Use the prompt

1. Open the agent in the repository of the integration.
2. Copy the prompt.
3. Replace `<STORE_URL>` with the URL of the test store.
4. Replace `<CREDENTIALS>` with the names of the environment variables.
5. Send the prompt. The agent stops after the first step and shows you the list of the legacy calls.

```text
Move the code of this repository from the legacy Maho or Magento 1 APIs to the Maho v2 API.

Test store: <STORE_URL>
Credentials of a v2 API user (client_credentials grant): <CREDENTIALS>
Do not write the credentials in code, in tests or in logs.

Sources, in this order:
1. The OpenAPI document of the store: <STORE_URL>/api/docs.json. It describes the installed version.
2. The migration guide: https://mahocommerce.com/api/v2/migrate-from-legacy-api/
3. The v2 docs: https://mahocommerce.com/api/v2/authentication/, https://mahocommerce.com/api/v2/conventions/ and https://mahocommerce.com/api/v2/errors/
If source 1 and source 2 do not agree, follow source 1 and tell me.

STEP 1: FIND THE LEGACY CALLS
Search the repository for:
- URLs that contain /api/rest/ but not /api/rest/v2/
- /api/soap, /api/v2_soap, /api/xmlrpc, /api/jsonrpc, ?wsdl, SoapClient
- OAuth 1.0a code: oauth_consumer_key, oauth_signature, /oauth/initiate, /oauth/token, oauth_authorize, the PHP OAuth class
- Calls of SOAP or XML-RPC methods, such as login(, call(, multiCall(, catalog_product.info, sales_order.list
- Code that parses XML with the root element magento_api
- Query strings with filter[, limit=, order= or dir=
For each call, also find the code that reads the answer.
Make a table with: file and line, protocol, HTTP method and path (or SOAP method), the fields that the code sends, the fields that the code reads.
Show me the table. Do not change code before I approve it.

STEP 2: MAP EACH CALL
For each row, find the v2 method and path in the guide. Then make sure that the OpenAPI document has this operation and these fields.
Write the field changes of each call: the new names, the new types and the new values.
If neither source has a counterpart, mark the row "no counterpart". Do not invent an endpoint, a parameter or a field.

STEP 3: REPLACE THE AUTHENTICATION
- Make one client for all v2 calls. Send and accept application/json.
- Read the base URL from the configuration of the integration, in the same way as the legacy URL. Do not write a store URL in code. Use <STORE_URL>/api/rest/v2 only in the test configuration.
- Get a token with POST /auth/token and the client_credentials grant. Send it as "Authorization: Bearer <token>".
- Keep the token until expires_in. If a request gives 401, get a new token one time and send the request again.
- A flow that logs in a customer uses the customer grant and the /customers/me routes.
- A SOAP or XML-RPC integration that must keep its API user for now uses the api_user grant, with the same user name and API key.
- When no call uses the OAuth 1.0a code or the SOAP session code, remove that code and its dependencies.

STEP 4: CHANGE THE CALLS
Change one resource at a time, in this order: products, stock, customers, orders, then the others.
For each call:
- Change the HTTP method, the path and the query parameters.
- Change the field names: snake_case becomes camelCase, entity_id becomes id. Apply the exceptions of the guide.
- Change the types: a number is a JSON number, not a string. A Yes/No value is true or false, not 1 or 0. Product status and visibility are words. A multiselect value is a list of option IDs.
- A field with no value can be missing from the answer. Treat a missing field and null in the same way.
- A list is a JSON array. The legacy lists of products, customers and orders were objects keyed by the record ID: change each loop that uses the key.
- Read a list page by page, with page and itemsPerPage (maximum 100). Stop when a page has fewer items than itemsPerPage.
- Read an error from the HTTP status and the "error" field of the body.
- Keep the behavior of the integration. Do not change code that does not call the API.

STEP 5: TEST
- Change the tests and the mocks of the API client to the v2 requests and answers.
- For each read, call the legacy API and the v2 API on the test store for the same records. Compare the values that the code uses. Report each difference.
- Send writes only to the test store. Never send a request to a production store.
- Run the tests of the repository. Report the result.

STEP 6: REPORT
- The table of step 1, with the v2 method and path of each call, and its status: "done" or "no counterpart".
- The fields that have no counterpart, or that have a different meaning in v2.
- The API resources that the role of the v2 API user needs.
- The configuration values that the integration needs in each environment: the base URL, the client ID and the client secret.
- The changes that I must make in the store: the role and the user.
```

## After the migration

1. Read the report of the agent. Do the changes in the store that it lists.
2. If the report has a call with no counterpart, read [No counterpart in v2](migrate-from-legacy-api.md#no-counterpart-in-v2).
3. Use the integration on the test store, then on the production store.
4. Turn off the legacy protocols as the [procedure](migrate-from-legacy-api.md#procedure) of the guide tells. Other integrations can still use them.
