---
description: Manage your Maho store over the SOAP and XML-RPC API - work with customers, categories, products, orders, carts, and inventory from any SOAP client.
---

# SOAP API

!!! warning "Legacy API"
    This is Maho's legacy SOAP/XML-RPC API, inherited from Magento 1 and kept for backward compatibility. New integrations should use the modern [REST & GraphQL API (v2)](v2/index.md) instead.

## Introduction

The Maho SOAP API provides you with the ability to manage your eCommerce stores by providing calls
for working with resources such as customers, categories, products, and sales orders.
It also allows you to manage shopping carts and inventory.

## Supported Types

The Maho API supports SOAP and XML-RPC, where SOAP is the default protocol.

Every protocol is disabled by default and the SOAP/XML-RPC adapters need optional Composer packages: see [Legacy API Protocols](legacy-protocols.md) before making your first call.

### SOAP

To connect to Maho SOAP web services, load the WSDL into your SOAP client from either of these URLs:

- `https://mahohost/api/?wsdl`
- `https://mahohost/api/soap/?wsdl`
- `https://mahohost/api/v2_soap?wsdl=1`

The following PHP example shows how to make SOAP calls to the Maho API v1:

```php
$client = new SoapClient('https://mahohost/api/soap/?wsdl');
$session = $client->login('apiUser', 'apiKey');

$result = $client->call($session, 'example.method');
$result = $client->call($session, 'example.method', 'arg1');
$result = $client->call($session, 'example.method', ['arg1', 'arg2', 'arg3']);
$result = $client->multiCall($session, [
    ['example.method'],
    ['example.method', 'arg1'],
    ['example.method', ['arg1', 'arg2']]
]);

$client->endSession($session);
```

### XML-RPC

To use XML-RPC, load the following URL into your XML-RPC client:

`https://mahohost/api/xmlrpc/`

where `mahohost` is the domain for your Maho host.

The following PHP example shows how to make XML-RPC calls:

```php
$client = new Zend_XmlRpc_Client('https://mahohost/api/xmlrpc/');
$session = $client->call('login', ['apiUser', 'apiKey']);

$client->call('call', [$session, 'example.method', ['arg1', 'arg2', 'arg3']]);
$client->call('call', [$session, 'example.method', ['arg1']]);
$client->call('call', [$session, 'example.method']);
$client->call('multiCall', [$session,
    [
        ['example.method', 'arg1'],
        ['example.method', ['arg1', 'arg2']],
        ['example.method']
    ]
]);

// If you don't need the session anymore
$client->call('endSession', [$session]);
```

The XML-RPC only supports version 1 of the Maho API.

## API Methods

The following table contains the API methods that can be called from your SOAP or XML-RPC client on the Maho v1 API.

| Method                                           | Description                                                                                                 | Return Value |
|--------------------------------------------------|-------------------------------------------------------------------------------------------------------------|--------------|
| startSession()                                   | Start the API session and return session ID.                                                                | string       |
| endSession(sessionId)                            | End the API session.                                                                                        | boolean      |
| login(apiUser, apiKey)                           | Start the API session, return the session ID, and authorize the API user.                                   | string       |
| call(sessionId, resourcePath, array arguments)   | Call the API resource that is allowed in the current session. See Note below.                               | mixed        |
| multiCall(sessionId, array calls, array options) | Call the API resource’s methods that are allowed for current session. See Notes below.                      | array        |
| resources(sessionId)                             | Return a list of available API resources and methods allowed for the current session.                       | array        |
| globalFaults(sessionId)                          | Return a list of fault messages and their codes that do not depend on any resource.                         | array        |
| resourceFaults(sessionId, resourceName)          | Return a list of the specified resource fault messages, if this resource is allowed in the current session. | array        |

**Note:** For `call()` and `multiCall()`, if no session is specified,
you can call only resources that ACL does not protect.

**Note:** For `multiCall()`, if the "break" option is specified, `multiCall()` breaks on the first error.

The Maho SOAP API v2 does not support the `call()` and `multiCall()` methods,
and instead provides a separate method for each API resource.

## Global API Faults

The following table contains fault codes that apply to all SOAP/XML-RPC API calls.

| Fault Code | Fault Message                               |
|------------|---------------------------------------------|
| 0          | Unknown Error                               |
| 1          | Internal Error. Please see log for details. |
| 2          | Access denied.                              |
| 3          | Invalid API path.                           |
| 4          | Resource path is not callable.              |

## SOAP API Version v2

The main difference between v1 and v2 is that instead of using `call()` and `multiCall()`,
it has separate methods for each action.

For example, consider the following PHP code using SOAP v1.

```php
$params = [
    [
        'status' => ['eq' => 'pending'],
        'customer_is_guest' => ['eq' => '1']
    ]
];
$result = $client->call($sessionId, 'sales_order.list', $params);
```

<p>With SOAP v2, the following code would be equivalent.</p>

```php
$params = [
    'filter' => [
        ['key' => 'status', 'value' => 'pending'],
        ['key' => 'customer_is_guest', 'value' => '1']
    ]
];
$result = $client->salesOrderList($sessionId, $params);
```

<p>Note that the WSDL for SOAP v1 and SOAP v2 are different. Note that in SOAP v1,
customizing the API did not involve changing the WSDL. In SOAP v2, changes to the WSDL are required.</p>

<p>You can configure the SOAP v2 API to be <a href="https://www.oasis-ws-i.org/">WS-I</a> compliant in the 
system configuration menu. To do this, set <b>Services &gt; Maho Core API &gt; WS-I Compliance</b> to <b>Yes</b>.</p>

<p>Note that the WSDL for the SOAP v2 API is different when in WS-I compliant mode.</p>

<p>Using the WS-I compliant SOAP v2 API WSDL, it is easy to automatically generate client classes for Java, .NET,
and other languages using standard libraries.</p>

## WS-I Compliance Mode

Maho provides you with the ability to use two modes for SOAP API V2.
These are with WS-I compliance mode enabled and WS-I compliance mode disabled.
The first one was introduced to make the system flexible,
namely, to increase compatibility with .NET and Java programming languages.

To enable/disable the WS-I compliance mode, perform the following steps:

1. In the Maho Admin Panel, go to **System > Configuration > Maho Core API**.
2. In the WS-I Compliance drop-down, select **Yes** to enable the WS-I compliance mode and **No** to disable
   the WS-I compliance mode, correspondingly.

The WS-I compliant mode uses the same WSDL endpoint as SOAP API V2 does.
The key difference is that XML namespaces are used in WS-I compliance mode.

## Creating a Custom API or Extending the Core API

The Core API allows you to manage a set of common resources used in Maho.
However, you may choose to have your own set of resources to manage,
or you may wish to extend the Core API to handle additional resources.

This tutorial leads you through the process of creating a custom API for a customer module that handles
basic customer information.

**Note:** This tutorial applies to v1 of the API.

To learn more about the Core API, to read Maho Core API calls.

For general information about the Maho API, go to the [Introduction](#introduction).

### 1. Creating an XML File that Will Define the API Resource

Create a file named `api.xml` in the `/etc` directory in the customer module.
Start with the empty structure, as follows:

```xml
<config>
    <api>
        <resources/>
        <acl>
            <resources>
                <all/>
            </resources>
        </acl>
    </api>
</config>
```

### 2. Adding a Resource Named Customer

Add an element named `customer` in the `resources` element.
Add a `methods` element with elements for `list`, `create`, `info`, `update` and `remove` methods for customer resource.

Note that:

- list will return all customers
- create will create a new customer
- info will return data on a specified customer
- update will update data on a specified customer
- remove will delete data on a specified customer

```xml
<config>
    <api>
        ...
        <resources>
            <customer translate="title" module="customer">
                <title>Customer Resource</title>
                <methods>
                    <list translate="title" module="customer">
                        <title>Retrieve customers</title>
                    </list>
                    <create translate="title" module="customer">
                        <title>Create customer</title>
                    </create>
                    <info translate="title" module="customer">
                        <title>Retrieve customer data</title>
                    </info>
                    <update translate="title" module="customer">
                        <title>Update customer data</title>
                    </update>
                    <delete>
                        <title>Delete customer</title>
                    </delete>
                </methods>
                <faults module="customer">
                    ...
                </faults>
            </customer>
        </resources>
        ...
    </api>
</config>
```

### 3. Adding Faults

The resource can return some faults, so add a `faults` element in the `customer` element, and list the various faults.

```xml
<config>
    <api>
        ...
        <resources>
            <customer translate="title" module="customer">
                ...
                <faults module="customer"> <!-- module="customer" specifies the module which will be used for translation. -->
                    <data_invalid> <!-- if we get invalid input data for customers -->
                        <code>100</code>
                        <!-- we cannot know all the errors that can appear, their details can be found in error message for call -->
                        <message>Invalid customer data. Details in error message.</message>
                    </data_invalid>
                    <filters_invalid>
                        <code>101</code>
                        <message>Invalid filters specified. Details in error message.</message>
                    </filters_invalid>
                    <not_exists>
                        <code>102</code>
                        <message>Customer doesn't exist.</message>
                    </not_exists>
                    <not_deleted>
                        <code>103</code>
                        <message>Customer was not deleted. Details in error message.</message>
                    </not_deleted>
                </faults>
            </customer>
        </resources>
        ...
    </api>
</config>
```

### 4. Describing the Access Control List (ACL) for the Resource

To prevent unauthorized access to our custom API,
you must first list the resources that are restricted within the `acl` element.

```xml
<config>
    <api>
        ...
        <acl>
            <resources>
                <customer translate="title" module="customer">
                    <title>Customers</title>
                    <list translate="title" module="customer">
                        <title>View All</title>
                    </list>
                    <create translate="title" module="customer">
                        <title>Create</title>
                    </create>
                    <info translate="title" module="customer">
                        <title>Get Info</title>
                    </info>
                    <update translate="title" module="customer">
                        <title>Update</title>
                    </update>
                    <delete translate="title" module="customer">
                        <title>Delete</title>
                    </delete>
                </customer>
            </resources>
        </acl>
    </api>
</config>
```

Then, map ACL resources to API resource methods by adding an `acl` element to each part of the resource that
needs restricting:

```xml
<config>
    <api>
        <resources>
            <customer translate="title" module="customer">
                <title>Customer Resource</title>
                <acl>customer</acl>
                <methods>
                    <list translate="title" module="customer">
                        <title>Retrieve customers</title>
                        <acl>customer/list</acl>
                    </list>
                    <create translate="title" module="customer">
                        <title>Create customer</title>
                        <acl>customer/create</acl>
                    </create>
                    <info translate="title" module="customer">
                        <title>Retrieve customer data</title>
                        <acl>customer/info</acl>
                    </info>
                    <update translate="title" module="customer">
                        <title>Update customer data</title>
                        <acl>customer/update</acl>
                    </update>
                    <delete>
                        <title>Delete customer</title>
                        <acl>customer/delete</acl>
                    </delete>
                </methods>
                ...
            </customer>
        </resources>
        ...
    </api>
</config>
```

### 5. Creating PHP Code

Next, write some PHP code to access the resources.
Start by creating a class called `Mage_Customer_Model_Customer_Api`that extends `Mage_Api_Model_Resource_Abstract`.
Save it into a file called `api.php`.

```php
class Mage_Customer_Model_Customer_Api extends Mage_Api_Model_Resource_Abstract
{
    public function create($customerData) {}
    
    public function info($customerId) {}
    
    public function items($filters) {}
    
    public function update($customerId, $customerData) {}
    
    public function delete($customerId) {}
}
```

Note that you cannot create method `list` because it's a PHP keyword, so instead the method is named items.
To make this work, add a `method` element into the `list` element in `api.xml`, as shown below.

```xml
<config>
    <api>
        <resources>
            <customer translate="title" module="customer">
                <model>customer/api</model> <!-- our model -->
                <title>Customer Resource</title>
                <acl>customer</acl>
                <methods>
                    <list translate="title" module="customer">
                        <title>Retrieve customers</title>
                        <method>items</method> <!-- we have another method name inside our resource -->
                        <acl>customer/list</acl>
                    </list>
                    ...
                </methods>
            </customer>
            ...
        </resources>
        ...
    </api>
</config>
```

Now add some simple functionality to the `Mage_Customer_Model_Api` methods you created.

Create a customer:

```php
public function create($customerData)
{
    try {
        $customer = Mage::getModel('customer/customer')
            ->setData($customerData)
            ->save();
    } catch (Mage_Core_Exception $e) {
        $this->_fault('data_invalid', $e->getMessage());
        // We cannot know all the possible exceptions,
        // so let's try to catch the ones that extend Mage_Core_Exception
    } catch (Exception $e) {
        $this->_fault('data_invalid', $e->getMessage());
    }
    return $customer->getId();
}
```

Retrieve customer info:

```php
public function info($customerId)
{
    $customer = Mage::getModel('customer/customer')->load($customerId);
    if (!$customer->getId()) {
        $this->_fault('not_exists');
        // If customer not found.
    }
    return $customer->toArray();
    // We can use only simple PHP data types in webservices.
}
```

Retrieve the list of customers using filtering:

```php
public function items($filters)
{
    $collection = Mage::getModel('customer/customer')->getCollection()
        ->addAttributeToSelect('*');

    if (is_array($filters)) {
        try {
            foreach ($filters as $field => $value) {
                $collection->addFieldToFilter($field, $value);
            }
        } catch (Mage_Core_Exception $e) {
            $this->_fault('filters_invalid', $e->getMessage());
            // If we are adding filter on non-existent attribute
        }
    }

    $result = [];
    foreach ($collection as $customer) {
        $result[] = $customer->toArray();
    }

    return $result;
}
```

Update a customer:

```php
public function update($customerId, $customerData)
{
    $customer = Mage::getModel('customer/customer')->load($customerId);

    if (!$customer->getId()) {
        $this->_fault('not_exists');
        // No customer found
    }

    $customer->addData($customerData)->save();
    return true;
}
```

Delete a customer:

```php
public function delete($customerId)
{
    $customer = Mage::getModel('customer/customer')->load($customerId);

    if (!$customer->getId()) {
        $this->_fault('not_exists');
        // No customer found
    }

    try {
        $customer->delete();
    } catch (Mage_Core_Exception $e) {
        $this->_fault('not_deleted', $e->getMessage());
        // Some errors while deleting.
    }

    return true;
}
```

### Creating a Custom Adapter

To create custom webservice adapter, implement the `Mage_Api_Model_Server_Adapter_Interface`, which is shown below.

```php
interface Mage_Api_Model_Server_Adapter_Interface
{
    /**
     * Set handler class name for webservice
     */
    function setHandler(string $handler): Mage_Api_Model_Server_Adapter_Interface;

    /**
     * Retrieve handler class name for webservice
     */
    function getHandler(): string;

    /**
     * Set webservice API controller
     */
    function setController(Mage_Api_Controller_Action $controller): Mage_Api_Model_Server_Adapter_Interface;

    /**
     * Retrieve webservice API controller
     */
    function getController(): Mage_Api_Controller_Action;

    /**
     * Run webservice
     */
    function run(): Mage_Api_Model_Server_Adapter_Interface;

    /**
     * Dispatch webservice fault
     */
    function fault(int $code, string $message);

}
```

Here is an example implementation for XML-RPC:

```php
class Mage_Api_Model_Server_Adapter_Customxmlrpc
    extends Varien_Object
    implements Mage_Api_Model_Server_Adapter_Interface
{
     /**
      * XmlRpc Server
      */
     protected Zend_XmlRpc_Server $_xmlRpc = null;

     /**
     * Set handler class name for webservice
     */
    public function setHandler(string $handler): Mage_Api_Model_Server_Adapter_Xmlrpc
    {
        $this->setData('handler', $handler);
        return $this;
    }

    /**
     * Retrieve handler class name for webservice
     */
    public function getHandler(): string
    {
        return $this->getData('handler');
    }

     /**
     * Set webservice API controller
     */
    public function setController(Mage_Api_Controller_Action $controller): Mage_Api_Model_Server_Adapter_Xmlrpc
    {
         $this->setData('controller', $controller);
         return $this;
    }

    /**
     * Retrieve webservice API controller
     */
    public function getController(): Mage_Api_Controller_Action
    {
        return $this->getData('controller');
    }

    /**
     * Run webservice
     */
    public function run(): Mage_Api_Model_Server_Adapter_Xmlrpc
    {
        $this->_xmlRpc = new Zend_XmlRpc_Server();
        $this->_xmlRpc->setClass($this->getHandler());
        $this
            ->getController()
            ->getResponse()
            ->setHeader('Content-Type', 'text/xml')
            ->setBody($this->_xmlRpc->handle());
        return $this;
    }

    /**
     * Dispatch webservice fault
     */
    public function fault(int $code, string $message)
    {
        throw new Zend_XmlRpc_Server_Exception($message, $code);
    }
}
```

**Notes:** The `setHandler`, `getHandler`, `setController` and `getController` methods have a simple implementation
that uses the `Varien_Object`'s `getData` and `setData` methods.

The `run` and `fault` methods are a native implementation for an XML-RPC webservice.
The `run` method defines webservice logic in this adapter for creating an XML-RPC server to handle XML-RPC requests.

```php
public function run()
    {
        $this->_xmlRpc = new Zend_XmlRpc_Server();
        $this->_xmlRpc->setClass($this->getHandler());
        $this->getController()->getResponse()
            ->setHeader('Content-Type', 'text/xml')
            ->setBody($this->_xmlRpc->handle());
        return $this;
    }
```

The `fault` method allows you to send fault exceptions for XML-RPC service when handling requests.

```php
public function fault($code, $message)
{
    throw new Zend_XmlRpc_Server_Exception($message, $code);
}
```

### Common Error Messages

The following are common error messages that you might receive when creating your own custom API.

**Invalid API path**

This error occurs when the methods listed in the `api.xml` file do not correspond exactly
with those used in your PHP file.

For example, in your `api.xml` file, you might have this:

```xml
<config>
    <api>
        <resources>
            <checkout_cart translate="title" module="checkout">
                <model>checkout/cart_api</model>
                <title>Cart API</title>
                <methods>
                    <list translate="title" module="checkout">
                        <title>Retrieve cart data</title>
                        <method>info</method>
                    </list>
                </methods>
            </checkout_cart>
        </resources>
        ...
    </api>
</config>
```

You should have a corresponding info method in your PHP file.

```php
class Mage_Checkout_Model_Cart_Api extends Mage_Cart_Model_Api_Resource
{
    public function info()
    {
        ...
    }
}
```

If you are missing this method, the error "Invalid API path" will be returned.
