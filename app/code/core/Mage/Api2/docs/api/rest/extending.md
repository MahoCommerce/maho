---
description: Extend Maho's legacy REST API with a custom coupon auto-generation resource - configuration files, REST roles and attributes, OAuth setup, and a test script.
---

# Extending REST API to use coupon auto generation

!!! warning "Legacy API"
    This is Maho's legacy REST API, inherited from Magento 1 and kept for backward compatibility. New integrations should use the modern [REST & GraphQL API (v2)](../v2/index.md) instead.

## Overview

Customers of traditional stores and online web stores love coupons. Typically, a merchant sends coupons to customers who input them when checking out. The coupon saves the customer money and hopefully entices the customer to visit the store more frequently. In addition, the merchant can track coupon codes to individual customers to target market those customers.

### Coupon Auto Generation and the Maho REST API

Maho supports a method of creating coupon codes - _auto generation_. Auto generating coupons means Maho programmatically creates several coupon codes at one time quickly and easily. However, if Maho generates the coupon codes, you'd have to manually distribute them to customers.

Maho's REST API is extensible and can easily be called by an outside program to auto generate coupon codes. You can use this feature, for example, to e-mail coupon codes to your top 100 customers.

No programming is necessary to implement the extension module discussed in this guide; however, basic familiarity with Maho modules and PHP programming is desirable.

The Coupon AutoGen API enables any authorized external program to instruct Maho to:

*   Auto-generate the specified number of coupon codes
*   Return these codes to the calling program - simulated in this guide using a simple `.php` file

### Implementation Details

This guide discusses how to use coupon auto generation and a web service to dynamically call the Maho REST API to generate a series of codes. The web service instantiates the underlying Maho sales rule (`salesrule/rule`) coupon code generator and creates a pool of new codes. These codes returned to the caller as a JSON string.

To extend the REST API to add a web service for generating and retrieving coupon codes, this guide discusses the following:

*   Setting up an auto generated coupon rule
*   Creating a module to use a REST based API extension  
    This guide gives you all the files to create the module; no programming is necessary. There are four module configuration `.xml` files and one `.php` file to create the web service.
*   Creating a user for the OAuth access to the new service
*   Testing the web service from a separate page

## System Requirements

To implement and test the Coupon AutoGen API, you must have all of the following:

*   Maho on Ubuntu.
*   [pecl OAuth 1.0a extension](http://pecl.php.net/package/oauth) which you install as discussed in [Installing OAuth](#installing-oauth).  
    Maho supports the [OAuth 1.0a specification](http://tools.ietf.org/html/rfc5849).
*   _Optional._ [`phpmyadmin`](http://www.phpmyadmin.net/home_page/downloads.php), which makes it easier to view and manipulate the Maho database. You can use `phpmyadmin` for convenience to get the OAuth key and shared secret later in this guide.

Although Maho supports other Linux operating systems, Ubuntu is the only one discussed in this guide. Consult an appropriate reference for equivalent commands on other Linux operating systems.

### Installing OAuth

This section discusses how to install the [pecl OAuth extension](http://pecl.php.net/package/oauth) on Ubuntu. Consult the [pecl.php.net documentation](http://pecl.php.net/support.php) for installation instructions on other operating systems.

The pecl OAuth extension requires the PEAR installer. The following sections discuss the installation in detail:

*   [Creating a phpinfo File](#creating-a-phpinfo-file)
*   [Installing the OAuth Packages](#installing-the-oauth-packages)
*   [Confirming that OAuth Installed Successfully](#confirming-that-oauth-installed-successfully)

**Note**: You must perform the tasks discussed in this section as a user with `root` privileges.

#### Creating a phpinfo File

Before you begin, create a `phpinfo.php` file, if you have not already done so, to determine if you already have OAuth running.

To create `phpinfo.php`:

1.  Open a command prompt window and connect to your Maho server.
2.  Create a file named `phpinfo.php` anywhere on the web server's docroot:

    ```php
    <?php
    phpinfo();
    ```

3.  Start a web browser and enter the following URL in its address or location field:

    http://_host-or-ip_[:_port_]/_path-to-phpinfo_/phpinfo.php


    For example, if your Maho instance hostname is `www.example.com` and you put `phpinfo.php` in the web server's docroot, enter:
    
    http://www.example.com/phpinfo.php

4.  Search the resulting output for `OAuth`.  
    If an OAuth section does _not_ display, OAuth is not set up so continue with the next section.  
    If OAuth is already installed, continue with [Defining a Maho Coupon Code Generation Rule](#defining-a-maho-coupon-code-generation-rule).

#### Installing the OAuth Packages

The pecl OAuth extension requires both PEAR (which enables you to install the package) and `libpcre3-dev`, which enables the OAuth package to be compiled.

To install the packages and confirm that OAuth is enabled:

1.  Enter the following commands in the order shown:

    ```
    apt-get install php-pear
    apt-get install libpcre3-dev
    pecl install oauth
    ```


    **Note**: Remember that you must perform the tasks discussed in this section as a user with `root` privileges.
    
    Wait while each package is installed. The message `Build process completed successfully` displays to indicate OAuth installed successfully.  
    
    **Note**: If ``Error: `make' failed`` displays after you enter `pecl install oauth`, see [OAuth Package Installation Error: `make' failed](#oauth-package-installation-error-make-failed).
    
    If the following displays, you must edit your `php.ini` file to find the OAuth library:
    
    configuration option "php_ini" is not set to php.ini location
    You should add "extension=oauth.so" to php.ini

2.  Open `php.ini` in a text editor.  
    If you're not sure where it's located look in the `phpinfo.php` page output. Add the following anywhere in `php.ini`:

    ```ini
    [OAuth]
    extension=oauth.so
    ```

3.  Save your changes to `php.ini` and exit the text editor.
4.  Enter the following command to restart the Apache web server.

    service apache2 restart

5.  Continue with the next section.

#### Confirming that OAuth Installed Successfully

If your `phpinfo.php` page is still open in a web browser, press Control+R to force a refresh; otherwise, enter the URL shown in [Creating a phpinfo File](#creating-a-phpinfo-file) to view it.

**Note**: Do not continue until you know that OAuth installed successfully.

## Defining a Maho Coupon Code Generation Rule

To define a Maho coupon code generation rule:

1.  Log in to the Maho Admin Panel as an administrator.
2.  Click **Promotions** > **Shopping Cart Price Rules**.
3.  On the Shopping Cart Price Rules page, click **Add New Rule** (in the upper-right corner of the page).  
    The General Information page displays.
4.  Enter the following information.

    | Item | Description |
    |------|------------ |
    | Rule Name | Enter `Generate Coupons`. |
    | Description | Enter an optional description of the rule, such as `Rule that generates a sequence of coupon codes`. |
    | Status | From the list, click **Active**. |
    | Websites | Click the websites on which you want the coupons to display. Hold down the Shift key and click the names of all items to select them. |
    | Customer Groups | Hold down the Shift key and click the names of all items to select them. |
    | Coupon | Click **Specific Coupon** |
    | Coupon Code | Leave the field blank. |
    | _CE only_. Auto Generation | Select the **Use Auto Generation** checkbox. |
    | _CE only_. Uses per Coupon | Enter `10`. |
    | Uses per Customer | Enter `1`. |
    | From Date | Select today's date. |
    | To Date | Select any date in the future. |
    | Priority | Enter `0`. |
    | Public In RSS Feed | Click **No**. |

5.  In the upper-right corner of the page, click **Save and Continue Edit**.  
    The message `The rule has been saved` displays at the top of the page to indicate that Maho successfully saved the rule you just created.
6.  In the left navigation bar, click **Actions**.  
    The Actions page displays.
7.  Enter the following information.

    | Item | Description |
    | --- | --- |
    | Apply | From the list, click **Percent of product price discount**. |
    | Discount Amount | Enter `10`. |
    | Maximum Qty Discount is Applied To | Enter `1`. |
    | Discount Qty Step (Buy X) | Enter `1`. |
    | Apply to Shipping Amount | From the list, click **No**. |
    | Free Shipping | From the list, click **No**. |
    | Stop Further Rules Processing | From the list, click **Yes**. |
    | _EE only_ Add Reward Points | Enter `0`. |

8.  Click **Save** (in the upper-right corner of the page).  
    The message `The rule has been saved` displays to indicate that Maho saved the rule action options you just entered. Notice that this page now has a row for the Generate Coupons rule you just defined.
9.  Write down the rule ID (circled in red in the preceding figure). You will use this value later in this guide.

## Generating Coupon Codes

Now that you've created a rule, this section discusses how to use the rule to manually generate a sequence of coupon codes.

1.  In the Shopping Cart Price Rules page, click the name of the rule you just created (**Generate Coupons**).
2.  In the left navigation bar, click **Manage Coupon Codes**.
3.  On the Coupons Information page, enter the following information:

    | Item | Description |
    | --- | --- |
    | Coupon Qty | Enter `3`. |
    | Code Length | Enter `12`. |
    | Code Format | Click **Alphanumeric**. |
    | Coupon Prefix | Enter `TEST-`. |
    | Coupon Suffix | Enter `-TEST`. |
    | Dash Every X Characters | Enter `0`. |

4.  Click **Generate**.  
    Maho displays the three coupon codes you created in the Coupon Code section.
5.  Click **Save**.

## Extending Maho's REST API to Include Coupon Auto-Generation

In the preceding section, you created a Shopping Cart Price Rule named Generate Coupons that manually generates a set of coupon codes. To use those codes, you could export them to a file, and then import them into any external program you want; however, this is a time-consuming procedure!

Fortunately, you can automate this process by adding a _coupon code auto-generate_ API to Maho's existing REST API. Using this API, an external program can automatically get the coupon codes it needs.

### Disabling the Maho Cache

While you're implementing the Coupon AutoGen API, you must disable Maho's caching so Maho will find and use your new code immediately.

To disable the cache:

1.  In the Maho Admin Panel, click **System** > **Cache Management**.
2.  Click **Select All** (on the upper-left of the page).  
    A check mark displays next to each Maho cache in the list.
3.  From the **Actions** list, click **Disable**.
4.  Click **Submit**.  
    Maho disables the selected caches, replacing the green ENABLED status indicators with red DISABLED indicators.
5.  Click **Flush Maho Cache** and wait for the cache to be flushed.
6.  Log out of the Maho Admin Panel.

### Creating Configuration Files

This section discusses how to create a module (also referred to as an _extension_). The module consists of configuration files that create a web service that extends the Maho REST API to take input from an external program. This program uses [HTTP POST](http://www.w3.org/Protocols/rfc2616/rfc2616-sec9.html#sec9.5) and OAuth calls to auto-generate coupon codes.

In this guide, the external program is a PHP script; however, it could be any application that uses OAuth and REST calls.

For more information about Maho module development, consult the Maho developer documentation.

**Note**: It is up to you to determine ownership and permissions. This guide assumes you create files and directories as `root` and change the permissions appropriately later. Consult an IT administrator if you're not sure how to proceed.

1.  Log in to your Maho server and create the following directories:

     ```
    app/code/community/CouponDemo
    app/code/community/CouponDemo/AutoGen
    app/code/community/CouponDemo/AutoGen/etc
    app/code/community/CouponDemo/AutoGen/Model
    app/code/community/CouponDemo/AutoGen/Model/Api2
    app/code/community/CouponDemo/AutoGen/Model/Api2/Coupon
    app/code/community/CouponDemo/AutoGen/Model/Api2/Coupon/Rest
    app/code/community/CouponDemo/AutoGen/Model/Api2/Coupon/Rest/Admin
    ``` 
    
    **Important**:
    
    *   You must create the files and directories _exactly_ as shown; directory names are case-sensitive.
    *   Do not change any values in the configuration files discussed in this section.
    *   When you create your configuration files, leave _no white space_ at the beginning of the files. Leading white space might cause errors when Maho reads the files and might prevent the coupon demonstration module from working.

2.  Change to the `app/etc/modules` directory.
3.  In that directory, use a text editor to create your module declaration file. This file must be named `CouponDemo_AutoGen.xml` and have the following contents.  
    ```
    <?xml version="1.0"?>
    <config>
       <modules>
          <CouponDemo_AutoGen>
             <active>true</active>
             <codePool>community</codePool>
          </CouponDemo_AutoGen>
       </modules>
    </config>
    ```

4.  Save your changes to `CouponDemo_AutoGen.xml` and exit the text editor.
5.  Change to the `app/code/community/CouponDemo/AutoGen/etc` directory.
6.  Create a file named `config.xml` with the following contents.  
    ```xml
    <?xml version="1.0"?> 
    <config>     
       <modules>         
          <CouponDemo_AutoGen>             
             <version>0.1.0</version>         
          </CouponDemo_AutoGen>     
       </modules>     
       <global>         
          <models>             
             <autogen>                 
                 <class>CouponDemo_AutoGen_Model</class>             
             </autogen>         
          </models>     
       </global> 
    </config>
    ```

7.  Create a file named `api2.xml` with the following contents.  
    ```xml
    <?xml version="1.0"?>
    <config>     
        <api2>         
            <resource_groups>             
                <autogen translate="title" module="CouponDemo_AutoGen">                 
                    <title>CouponDemo API</title>                 
                    <sort_order>10</sort_order>             
                </autogen>         
            </resource_groups>         
            <resources>             
                <autogen translate="title" module="CouponDemo_AutoGen">                 
                    <group>autogen</group>                 
                    <model>autogen/api2_coupon</model>                 
                    <title>Coupon Code Auto Generation</title>                 
                    <sort_order>10</sort_order>                 
                    <privileges>                     
                        <admin>                         
                            <create>1</create>                         
                            <retrieve>1</retrieve>                     
                        </admin>                 
                    </privileges>                 
                    <attributes>                     
                        <coupon_id>Coupon ID</coupon_id>                     
                        <code>Code</code>                     
                        <qty>Quantity</qty>                     
                        <length>Length</length>                     
                        <format>Format</format>                 
                    </attributes>                 
                    <routes>                     
                        <route>                         
                            <route>/coupondemo/rules/:rule_id/codes</route>                         
                            <action_type>collection</action_type>                     
                        </route>                 
                    </routes>                 
                    <versions>1</versions>             
                </autogen>         
            </resources>     
        </api2>
    </config>
    ```

8.  Save your changes to `api2.xml` and exit the text editor.
9.  Change to the `app/code/community/CouponDemo/AutoGen/Model/Api2` directory.
10.  Create a file named `Coupon.php` with the following contents.  
     `<?php class CouponDemo_AutoGen_Model_Api2_Coupon extends Mage_Api2_Model_Resource { }`

11.  Save your changes to `Coupon.php` and exit the text editor.
12.  Change to the `app/code/community/CouponDemo/AutoGen/Model/Api2/Coupon/Rest/Admin` directory.
13.  Create a file named `V1.php` with the following contents.  
     ```php
     <?php
     /* Coupon AutoGen REST API * 
      * @author Chuck Hudson (used with permission). For more recipes, see Chuck's book http://shop.oreilly.com/product/0636920023968.do
      */
     class CouponDemo_AutoGen_Model_Api2_Coupon_Rest_Admin_V1 extends CouponDemo_AutoGen_Model_Api2_Coupon
     {     
        /**     
         * Generate one or more coupon codes using the Generate Coupons rule defined in Maho.      
         * Expected parameters are:      
         * {      
         *    'qty': int, - number of coupon codes to instruct Maho to generate      
         *    'length': int, - length of each generated coupon code      
         *    'format': string, - alphanum (for alphanumeric codes), alpha (for alphabetical codes), and num (for numeric codes)      
         * }      
         *      
         * @param array $couponData      
         * @return string|void      
         */     
        protected function _create($couponData)
        {         
            $ruleId = $this->getRequest()->getParam('rule_id');         
            $couponData['rule_id'] = $ruleId;         
            $rule = $this->_loadSalesRule($ruleId);         
                     
            /** @var Mage_SalesRule_Model_Coupon_Massgenerator $generator */         
            $generator = $rule->getCouponMassGenerator();         
            // Validate the generator         
            if (!$generator->validateData($couponData)) {             
                $this->_critical(Mage::helper('salesrule')->__('Coupon AutoGen API: Invalid parameters passed in.'), Mage_Api2_Model_Server::HTTP_BAD_REQUEST);         
            } else {             
                // Set the data for the generator             
                $generator->setData($couponData);             
                // Generate a pool of coupon codes for the Generate Coupons rule             
                $generator->generatePool();         
            }     
        }     
     
        /**      
         * Retrieve list of coupon codes.      
          *      
          * @return array      
          */     
        protected function _retrieveCollection()     
        {         
            $ruleId = $this->getRequest()->getParam('rule_id');         
            $rule = $this->_loadSalesRule($ruleId);         
     
            /** @var Mage_SalesRule_Model_Resource_Coupon_Collection $collection  */         
            $collection = Mage::getResourceModel('salesrule/coupon_collection');         
            $collection->addRuleToFilter($rule);         
            $this->_applyCollectionModifiers($collection);         
            $data = $collection->load()->toArray();         
            return $data['items'];     
         }     
     
        /**      
         * Load sales rule by ID.      
         *      
         * @param int $ruleId      
         * @return Mage_SalesRule_Model_Rule      
         */     
        protected function _loadSalesRule($ruleId)     
        {         
            if (!$ruleId) {             
                $this->_critical(Mage::helper('salesrule')                 
                    ->__('Rule ID not specified.'), Mage_Api2_Model_Server::HTTP_BAD_REQUEST);         
            }         
        
            $rule = Mage::getModel('salesrule/rule')->load($ruleId);         
            if (!$rule->getId()) {             
                $this->_critical(Mage::helper('salesrule')                 
                    ->__('Rule was not found.'), Mage_Api2_Model_Server::HTTP_NOT_FOUND);         
            }         
            return $rule;     
        } 
     }
     ```

14.  Save your changes to `V1.php` and exit the text editor.

### Setting Permissions on the Configuration Files

File permissions and ownership are important for any Linux application. Maho provides general guidelines for permission and ownership although following them are not a requirement for this guide. The configuration files and directories can be owned by `root` or other users and it won't prevent the procedures discussed in this guide from completing successfully.

Consult your network administrator if you are not sure how to set file permissions and ownership. The procedure that follows is a suggestion only.

The guidelines discussed in the following procedure set the following:

*   File and directory ownership set to `_your-login-name_:_apache-user-group_`.  
    If you're not sure which group owns Apache processes, enter the command `ps -ef | grep apache2`. The following procedure assumes it is `www-data`.
*   File permissions set to 644.
*   Directory permissions set to 755.

To optionally set permissions and ownership according to Maho guidelines:

1.  As a user with `root` privileges, enter the following commands in the order shown to change ownership of the files and directories you created as discussed in this guide:

    ```shell
    cd app/code/community
    chown -R _your-login-name_:www-data CouponDemo
    find . -type f -exec chmod 644 {} +
    find . -type d -exec chmod 755 {} +
    ```

2.  As a user with `root` privileges, enter the following commands in the order shown to change the permissions and ownership of `CouponDemo_AutoGen.xml`.
    
    ```shell
    cd app/etc/modules
    chown _your-login-name_:www-data CouponDemo_AutoGen.xml
    chmod 644 CouponDemo_AutoGen.xml
    ```

## Securing the Coupon AutoGen API

For security reasons, Maho allows only authorized external programs to call the Maho REST API.

### Creating a REST role for the Coupon AutoGen API

To use the Maho Admin Panel to create a role for the Coupon AutoGen API:

1.  Log in to the Maho Admin Panel as an administrator.
2.  Click **System** > **Web Services** > **REST - Roles**.
3.  On the REST - Roles page, click **Add Admin Role**.
4.  In the **Role Name** field, enter `Coupon Auto Generate Demo`.
5.  Click **Save Role**.
6.  In the left navigation bar, click **Role API Resources**.  
    The Role Resources page contains a hierarchical list of resources to which you can grant or deny the Coupon Auto Generate Demo role access.
7.  From the **Resource Access** list, click **Custom**.
8.  Select the checkbox next to the node labeled **CouponDemo API**.
9.  Click **Save Role**.  
    Maho saves the resource API permissions you granted to the Coupon Auto Generate Demo REST role.  
    The Coupon Auto Generate Demo role now has permission to use the Coupon AutoGen API.

### Adding Users to the Coupon Auto Generate Demo Role

Now that you have a role, you must add users to give them permission to call the Coupon AutoGen API as follows:

1.  In the left navigation bar, click **Role Users**.
2.  Click **Reset Filter** (in the upper-right corner of the page).  
    The page displays all registered users.
3.  Select the checkbox next to each user to grant the user privileges to access the resources available to the Coupon Auto Generate Demo REST role - that is, permission to call the Coupon AutoGen API.

    **Note**: If a warning dialog box displays, click **OK** to dismiss it. This warning is not relevant when adding users to REST roles.

4.  When you're done, click **Save Role**.  
    The specified user(s) can now grant an external program the right to call the Coupon AutoGen API.

### Setting up REST Attributes for the Coupon AutoGen API

This section discusses how to enable any user with a REST Admin role to use the Coupon AutoGen API.

To set REST attributes for the REST Admin role:

1.  Click **System** > **Web Services** > **REST - Attributes**.
2.  On the REST Attributes page, under User Type, click **Admin**.
3.  In the User Type Resources section, from the **Resource Access** list, click **Custom**.
4.  Select the **CouponDemo API** checkbox.  
    Doing so selects all the child checkboxes.
5.  Click **Save**.  
    Any user with the REST Admin role can now read from and write to the Coupon AutoGen API.

### Creating an OAuth Consumer for Your Test Script

This section discusses how to create a consumer so you can test the Coupon AutoGen API before you deploy it in a production system. After successfully testing the API, you can remove this user.

1.  In the Maho Admin Panel, click **System** > **Web Services** > **REST - OAuth Consumers**.
2.  Click **Add New** (in the upper-right corner of the page).  
    The **New Consumer** page displays.
3.  In the **Name** field, enter `Coupon AutoGen Test Driver`.
4.  Leave the other fields blank.
5.  _Write down_ the values displayed in the **Key** and **Secret** text boxes.

    **Note**: The key and secret values are stored in the Maho database in the table `oauth_consumer`. It might be more convenient for you to use `phpmyadmin` or database tools to retrieve them from the database after you save the role.

    You must include these values in the test script you will write in the next section. The script uses these values to identify itself to Maho.
6.  Click **Save** (in the upper-right corner of the page).
7.  Log out of the Maho Admin Panel.

## Testing the Coupon AutoGen API

This section discusses how to create a simple PHP file that acts as an external program and, with permissions you granted the OAuth consumer, enables the program to use the [HTTP POST](http://www.w3.org/Protocols/rfc2616/rfc2616-sec9.html#sec9.5) method to auto generate coupon codes.

You can use _any_ type of OAuth/REST call, in fact, such as using an API client like Postman, Insomnia, or Bruno as discussed [here](testing_rest_resources.md).

### Creating the Test Script

The test script you create calls the Coupon AutoGen API, thereby causing Maho to generate the specified coupon codes and return them to the caller (`rest_test.php`) in the form of a JSON-encoded string. Finally, the server responds to the browser's request with an HTML page containing the generated coupon codes.

The PHP code that follows:

*   Uses your OAuth `consumerKey` and `consumerSecret` to set up the OAuth client.
*   Defines data to pass to the array `$couponGenerationData`. The data should include the following:
    *   The rule ID (listed next to the rule in the rule list in the Maho Admin Panel)
    *   The number of codes desired (in this case, two)
    *   The length of the codes
    *   The format to be used (in this case, alphanumeric)
*   The array of parameters is then passed in the `fetch` command with the `resourceUrl` for the web service call.

Finally, the server responds to the browser's request with an HTML page containing the generated coupon codes.

To create the test script, named `rest_test.php`:

1.  Create the file `_maho-install-dir_/rest_test.php` with the following contents.
    ```php
    <?php
    // Replace <<...>> below with the key and secret values generated for the Coupon AutoGen Test Driver
    $consumerKey = '<<YOUR CONSUMER KEY>>';
    // from Admin Panel's "REST - OAuth Consumers page"
    $consumerSecret = '<<YOUR CONSUMER SECRET>>';
    // from Admin Panel's "REST - OAuth Consumers page" 
    // Set the OAuth callback URL to this script since it contains the logic
    // to execute *after* the user authorizes this script to use the Coupon AutoGen API
    $callbackUrl = "http://<<host-or-ip:port>>/<<path>>/rest_test.php"; 
    // Set the URLs below to match your Maho installation
    $temporaryCredentialsRequestUrl = "http://<<host-or-ip:port>>/<<path>>/oauth/initiate?oauth_callback=" . urlencode($callbackUrl);
    $adminAuthorizationUrl = 'http://<<host-or-ip:port>>/<<path>>/admin/oauth_authorize';
    $accessTokenRequestUrl = 'http://<<host-or-ip:port>>/<<path>>/oauth/token';
    $apiUrl = 'http://<<host-or-ip:port>>/<<path>>/api/rest';
    
    session_start();
    if (!isset($_GET['oauth_token']) && isset($_SESSION['state']) && $_SESSION['state'] == 1) {
        $_SESSION['state'] = 0; 
    }
    
    try {     
        $authType = ($_SESSION['state'] == 2) ? OAUTH_AUTH_TYPE_AUTHORIZATION : OAUTH_AUTH_TYPE_URI;     
        $oauthClient = new OAuth($consumerKey, $consumerSecret, OAUTH_SIG_METHOD_HMACSHA1, $authType);     
        $oauthClient->enableDebug();     
        if (!isset($_GET['oauth_token']) && !$_SESSION['state']) {         
            $requestToken = $oauthClient->getRequestToken($temporaryCredentialsRequestUrl);         
            $_SESSION['secret'] = $requestToken['oauth_token_secret'];         
            $_SESSION['state'] = 1;         
            header('Location: ' . $adminAuthorizationUrl . '?oauth_token=' . $requestToken['oauth_token']);         
            exit;     
        } else if ($_SESSION['state'] == 1) {         
            $oauthClient->setToken($_GET['oauth_token'], $_SESSION['secret']);         
            $accessToken = $oauthClient->getAccessToken($accessTokenRequestUrl);         
            $_SESSION['state'] = 2;         
            $_SESSION['token'] = $accessToken['oauth_token'];         
            $_SESSION['secret'] = $accessToken['oauth_token_secret'];         
            header('Location: ' . $callbackUrl);         
            exit;     
        } else {         
            // We have the OAuth client and token. Now, let's make the API call.         
            $oauthClient->setToken($_SESSION['token'], $_SESSION['secret']);         
            // Set the array of params to send with the request         
            $ruleId = <<RULE_ID>>; // Set to the rule ID of the Generate Coupons rule         
            $couponGenerationData = array();         
            $couponGenerationData['qty'] = 2; // Number of coupons codes to create         
            $couponGenerationData['length'] = 7; // Length of each coupon code         
            // Options for format include:         
            // alphanum (for alphanumeric codes), alpha (for alphabetical codes), and num (for numeric codes)         
            $couponGenerationData['format'] = "alphanum"; // Use alphanumeric for the coupon code format         
            // Generate coupon codes via POST         
            $resourceUrl = "$apiUrl/coupondemo/rules/{$ruleId}/codes";         
            $oauthClient->fetch($resourceUrl, json_encode($couponGenerationData), OAUTH_HTTP_METHOD_POST, array(             
                'Accept' => 'application/json',             
                'Content-Type' => 'application/json',         
            ));         
            // Retrieve list of created coupons via GET         
            $collectionFilters = array('limit' => $couponGenerationData['qty'], 'order' => 'coupon_id', 'dir' => 'dsc');         
            $oauthClient->fetch($resourceUrl, $collectionFilters, OAUTH_HTTP_METHOD_GET, array(             
                'Accept' => 'application/json',             
                'Content-Type' => 'application/json',         
            ));         
            $coupons = json_decode($oauthClient->getLastResponse(), true);         
            // Display the newly generated codes to demonstrate that the Coupon AutoGen API works         
            // In reality, you might put these codes in emails to customers, store them in a database, etc.        
            echo "New coupon codes:   ";         
            foreach ($coupons as $coupon) {             
                echo " --> " . $coupon['code'] . "   ";         
            }    
        } 
    } catch (OAuthException $e) {     
        print_r($e->getMessage());     
        echo "   ";     
        print_r($e->lastResponse); 
    }
    ```

2.  The following table discusses the values you must change.

    | String to change | How to change it |
    | --- | --- |
    | <<YOUR CONSUMER KEY>> | Coupon AutoGen Test Driver OAuth consumer's key.<br><br>You can view this in the Admin Panel: **System** > **Web Services** > **REST - OAuth Consumers** or you can get the value from the `oauth_consumer` table in the Maho database. |
    | <<YOUR CONSUMER SECRET>> | Coupon AutoGen Test Driver OAuth consumer's secret.<br><br>You can view this in the Admin Panel: **System** > **Web Services** > **REST - OAuth Consumers** or you can get the value from the `oauth_consumer` table in the Maho database. |
    | <<host-or-ip:port>>/<<path>> | Your Maho instance's fully qualified hostname or IP address and port, if you are using a port other than 80, and the path to your Maho installation. If you are running Maho on localhost, enter `127.0.0.1`<br><br>``   For example, if your Maho server's hostname is `www.example.com`, running on port 80, and Maho is installed at `/var/www/maho`, enter `http://www.example.com/maho`   `` |
    | <<RULE_ID>> | Generate Coupons rule ID.<br><br>Get this value by clicking **Promotions** > **Shopping Cart Price Rules**<br><br>. |

3.  Save the file and close the text editor.

### Running the Test Script

To run the test script:

1.  Start a web browser.
2.  In the browser's address or location field, enter:

    `https://yourdomain.test/rest_test.php`

3.  When prompted, enter the login credentials of the OAuth consumer you created.
4.  Click **Login**.
5.  When prompted, click **Authorize** to grant authorization for the script to access your OAuth consumer account.
6.  When prompted, log in as that user.  
    After you log in, two new coupon codes display as follows to confirm you successfully used the API. If your browser displays a page like this one, you've successfully implemented the Coupon AutoGen REST API!

To optionally see these codes in the Admin Panel:

1.  Log in to the Admin Panel as an administrator.
2.  Click **Promotions** > **Shopping Cart Price Rules**.
3.  Click **Generate Coupons**.
4.  In the left navigation bar, click **Manage Coupon Codes**.  
    The codes you generated manually earlier display with the new codes (highlighted in red).

### Re-Enabling Maho's Cache

Only after successfully completing the test, you should re-enable Maho's caching system, so performance returns to normal.

To re-enable the Maho cache:

1.  In the Maho Admin Panel, click **System** > **Cache Management**.
2.  On the Cache Storage Management page, click **Select All** (in the upper-left of the page).  
    A check mark displays next to each Maho cache in the list.
3.  Click `Enable` from the **Actions** list.
4.  Click **Submit**.  
    Maho enables the selected caches. You can tell because the red DISABLED status indicator is replaced by a green ENABLED indicator.
5.  Click **Select All** again.
6.  Click `Refresh` from the **Actions** list.
7.  Click **Submit**.  
    Maho reloads the selected caches. Maho now performs much faster.

Congratulations! You have successfully added the Coupon AutoGen API to Maho's REST API.

## Troubleshooting Suggestions

### OAuth Package Installation Error: `make' failed

**Problem:** OAuth package installation fails with the error ``ERROR: `make' failed``.

**Description:** In some cases, the `pecl install oauth` command does not install a C compiler. If you encounter the following error, you must install the `make` package; otherwise, OAuth won't compile:

1: make: not found
ERROR: `make' failed

**Solution:**

1.  Enter the following commands in the order shown as a user with `root` privileges:

    ```shell
    apt-get install make
    pecl install oauth
    ```

2.  Make sure the message `Build process completed successfully` displays to indicate OAuth compiled successfully.  
    If the following displays, you must edit your `php.ini` file to find the OAuth library:

    configuration option "php_ini" is not set to php.ini location
    You should add "extension=oauth.so" to php.ini

3.  Open `php.ini` in a text editor.  
    If you're not sure where it's located look in the `phpinfo.php` page output. Add the following anywhere in `php.ini`:

    ```ini
    [OAuth]
    extension=oauth.so
    ```

4.  Save your changes to `php.ini` and exit the text editor.
5.  Enter the following command to restart the Apache web server.

    ```shell
    service apache2 restart
    ```

6.  Verify the OAuth installation succeeded as discussed before.

### CouponDemo API Calls Options Don't Display in the Admin Panel

**Problem:** After setting up the CouponDemo configuration files, the **CouponDemo API Calls** checkboxes do not display in the Admin Panel. They are described in [Creating a REST role for the Coupon AutoGen API](#creating-a-rest-role-for-the-coupon-autogen-api).

**Description:** The **CouponDemo API Calls** checkboxes display to indicate you set up the module correctly. If they don't display, either the Maho cache hasn't been entirely cleared or there's something wrong with the directory structure or configuration files.

**Solution:** Use the following steps to isolate and correct the issue:

1.  Make sure your directory structure is set up _exactly_ as shown in [Creating Configuration Files](#creating-configuration-files).
2.  Make sure you copied the _exact text_ from the sample configuration files discussed in [Creating Configuration Files](#creating-configuration-files). _Do not change anything_, and remember that case is important.
3.  Clear the Maho cache.
4.  Log out of the Maho Admin Panel and log back in.
5.  Click **System** > **REST - Roles**.
6.  Click **Coupon Auto Generate Demo**.
7.  In the left navigation bar, click **Role API Resources**.
8.  If the **CouponDemo API Calls** checkboxes do not display, double-check all of the `.xml` configuration files to make sure there is _no leading white space_ (that is, there are no blank lines at the beginning of the files.  
    Remove any blank lines and save the `.xml` files.

### Errors Running rest_test.php

#### Invalid auth/bad request: oauth_problem=consumer_key_rejected

The following error displays in the web browser:

Invalid auth/bad request (got a 401, expected HTTP/1.1 20X or a redirect)
{"messages":{"error":[{"code":401,"message":"oauth_problem=consumer_key_rejected"}]}}

**Description:** Your OAuth authentication attempt failed because the credentials are incorrect.

**Solution:** Open `rest_test.php` in a text editor and verify the values of the following:

```php
$consumerKey = '_value_';
$consumerSecret = '_value_';
```

You can find these values in the `oauth_consumer` database table or in the Admin Panel: **System** > **Web Services** > **REST - OAuth Consumers**.

After verifying the correct values, save your changes to `rest_test.php` and try again.

#### Invalid auth/bad request: Rule was not found

The following error displays in the web browser:

Invalid auth/bad request (got a 404, expected HTTP/1.1 20X or a redirect)

```json
{"messages":{"error":[{"code":404,"message":"Rule was not found."}]}}
```

**Description:** The shopping cart promotion rule could not be found.

**Solution:** Open `rest_test.php` in a text editor and verify the value of the following:

```php
$ruleId = _value_;
```

You can find this value in the Admin Panel: **Promotions** > **Shopping Cart Price Rules**.

Change the value in `rest_test.php`, save it, and try again.

#### Invalid auth/bad request: /maho/oauth/initiate was not found on this server

The following error displays in the web browser:

```
Invalid auth/bad request (got a 404, expected HTTP/1.1 20X or a redirect)
Not Found
The requested URL /maho/oauth/initiate was not found on this server.
```

**Description:** The HTTP redirect failed, most likely because web server rewrites are not properly enabled.

**Solution:** Make sure web server rewrites are enabled.