---
description: Start developing for Maho with this adapted Alana Storm guide series, learning how code is organized into modules under app/code and how you extend the platform.
---

# Introduction to Maho Development

!!! tip "Deep dive"
    For AI-generated documentation with architecture diagrams and source-level explanations, see the [DeepWiki documentation](https://deepwiki.com/MahoCommerce/maho){:target="_blank"}.

This series of guides was written by [Alana Storm](https://alanastorm.com){target=_blank} for the Magento devdocs website and it's now edited and  adapted for Maho.

## Code Organized in Modules

Maho organizes its code into individual Modules. In a typical PHP [**Model-View-Controller (MVC)**](https://en.wikipedia.org/wiki/Model–view–controller) application, all the Controllers will be in one folder, all the Models in another, etc. In Maho, files are grouped together based on functionality, which are called **modules** in Maho.

**Maho's Code**

For example, you'll find Controllers, Models, Helpers, Blocks, etc. related to Maho's checkout functionality in

`app/code/core/Mage/Checkout`

**Your Code**

When you want to customize or extend Maho, rather than editing core files directly, or even placing your new Controllers, Models, Helpers, Blocks, etc. next to Maho code, you'll create your own Modules in

`app/code/local/Package/Modulename`

**Package** (also often referred to as a **Namespace**) is a unique name that identifies your company or organization. The intent is that each member of the world-wide Maho community will use their own Package name when creating modules in order to avoid colliding with another user's code.

When you create a new Module, you need to tell Maho about it. This is done by adding an XML file to the folder:

`app/etc/modules`

There are files in this folder named in the form:

`Packagename_Modulename.xml`

## Configuration-Based MVC

Maho is a **configuration-based** MVC system. The alternative to this would a **convention-based** MVC system.

In a convention-based MVC system, if you wanted to add, say, a new Controller or maybe a new Model, you'd just create the file/class, and the system would pick it up automatically.

In a configuration-based system, like Maho, in addition to adding the new file/class to the codebase, you often need to explicitly tell the system about the new class, or new group of classes. In Maho, each Module has a file named config.xml. This file contains all the relevant configuration for a Maho Module. At runtime, all these files are loaded into one large configuration tree.

For example, want to use Models in your custom Module? You'll need to add some code to config.xml that tells Maho you want to use Models, as well as what the base class name for all your Models should be.

```xml
<models>
     <packagename>
          <class>Packagename_Modulename_Model</class>
    </packagename>
</models>
```

The same goes for Helpers, Blocks, Routes for your Controllers, Event Handlers, and more. Almost anytime you want to tap into the power of the Maho system, you'll need to make some change or addition to your config file.

## Controllers

In any PHP system, the main PHP entry point remains a PHP file. Maho is no different, and that file is index.php.

However, you never CODE in index.php. In an MVC system, index.php will contain code/calls to code that does the following:

1.  Examines the URL
2.  Based on some set of rules, turns this URL into a Controller class and an Action method (called Routing)
3.  Instantiates the Controller class and calls the Action method (called dispatching)

This means the **practical** entry point in Maho (or any MVC-based system) is a method in a Controller file. Consider the following URL:

`http://example.com/catalog/category/view/id/25`

Each portion of the path after the server name is parsed as follows.

### Front Name - catalog

The first portion of the URL is called the front name. This, more or less, tells Maho which Module it can find a Controller in. In the above example, the front name is _catalog_, which corresponds to the Module located at:

`app/code/core/Mage/Catalog`

### Controller Name - category

The second portion of the URL tells Maho which Controller it should use. Each Module with Controllers has a special folder named 'controllers' which contains all the Controllers for a module. In the above example, the URL portion category is translated into the Controller file

`app/code/core/Mage/Catalog/controllers/CategoryController.php`

Which looks like

```php
class Mage_Catalog_CategoryController extends Mage_Core_Controller_Front_Action
{
}
```

All Controllers in the Maho cart application extend from Mage_Core_Controller_Front_Action.

### Action Name - view

Third in our URL is the action name. In our example, this is "view". The word "view" is used to create the Action Method. So, in our example, "view" would be turned into "viewAction"

```php
class Mage_Catalog_CategoryController extends Mage_Core_Controller_Front_Action
{
    public function viewAction()
    {
        //main entry point
    }
}
```

People familiar with the Zend Framework will recognize the naming convention here.

### Parameter/Value - id/25

Any path portions after the action name will be considered key/value GET request variables. So, in our example, the "id/25" means there will get a GET variable named "id", with a value of "25".

As previously mentioned, if you want your Module to use Controllers, you'll need to configure them. Below is the configuration chunk that enables Controllers for the Catalog Module

```xml
<frontend>
    <routers>
        <catalog>
            <use>standard</use>
            <args>
                <module>Mage_Catalog</module>
                <frontName>catalog</frontName>
            </args>
        </catalog>
    </routers>
</frontend>
```

Don't worry too much about the specifics right now, but notice the

```xml
<frontName>catalog</frontName>
```

This is what links a Module with a URL frontname. Most Maho core Modules choose a frontname that is the same as their Module name, but this is not required.

### Multiple Routers

The routing described above is for the Maho cart application (often called the frontend). If Maho doesn't find a valid Controller/Action for a URL, it tries again, this time using a second set of Routing rules for the Admin application. If Maho doesn't find a valid **Admin** Controller/Action, it uses a special Controller named Mage_Cms_IndexController.

The CMS Controller checks Maho's content Management system to see if there's any content that should be loaded. If it finds some, it loads it, otherwise the user will be presented with a 404 page.

For example, the main Maho "index" page is one that uses the CMS Controller, which can often throw newcomers for a loop.

## Context-Based URI Model Loading

Now that we're in our Action method entry point, we'll want to start instantiating classes that do things. Maho offers a special way to instantiate Models, Helpers and Blocks using static factory methods on the global Mage class. For example:

```php
Mage::getModel('catalog/product');
Mage::helper('catalog/product');
```

The string 'catalog/product' is called a Grouped Class Name. It's also often called a URI. The first portion of any Grouped Class Name (in this case, catalog), is used to lookup which Module the class resides in. The second portion ('product' above) is used to determine which class should be loaded.

So, in both of the examples above, 'catalog' resolves to the Module app/code/core/Mage/Catalog.

Meaning our class name will start with Mage_Catalog.

Then, product is added to get the final class name

```php
Mage::getModel('catalog/product');
// Mage_Catalog_Model_Product

Mage::helper('catalog/product');
// Mage_Catalog_Helper_Product
```

These rules are bound by what's been setup in each Module's config file. When you create your own custom Module, you'll have your own grouped classnames (also calles classgroups) to work with Mage::getModel('myspecialprefix/modelname');.

You don't **have** to use Grouped Class Names to instantiate your classes. However, as we'll learn later, there are certain advantages to doing so.

## Maho Models

Maho, like most frameworks these days, offers an Object Relational Mapping (ORM) system. ORMs get you out of the business of writing SQL and allow you to manipulate a datastore purely through PHP code. For example:

```php
$model = Mage::getModel('catalog/product')->load(27);
$price = $model->getPrice();
$price += 5;
$model->setPrice($price)->setSku('SK83293432');
$model->save();
```

In the above example we're calling the methods "getPrice" and "setPrice" on our Product. However, the Mage_Catalog_Model_Product class has no methods with these names. That's because Maho's ORM uses PHP's magic ___call_ method to implement getters and setters.

Calling the method `$product->getPrice();` will "get" the Model attribute "price".

Calling `$product->setPrice();` will "set" the Model attribute "price". All of this assumes the Model class doesn't already have methods named getPrice or setPrice. If it does, the magic methods will be bypassed. If you're interested in the implementation of this, checkout the `\Maho\DataObject` class, which all Models inherit from. (`Varien_Object` still works as a legacy backward-compatibility alias for `\Maho\DataObject`.)

If you wanted to get all the available data on a Model, call $product->getData(); to get an array of all the attributes.

You'll also notice it's possible to chain together several calls to the set method:

```php
$model
    ->setPrice($price)
    ->setSku('SK83293432');
```

That's because each set method returns an instance of the Model. This is a pattern you'll see used in much of the Maho codebase.

Maho's ORM also contains a way to query for multiple Objects via a Collections interface. The following would get us a collection of all products that cost $5.00

```php
$products_collection = Mage::getModel('catalog/product')->getCollection()
    ->addAttributeToSelect('*')
    ->addFieldToFilter('price', '5.00');
```

Again, you'll notice Maho's implemented a chaining interface here. Collections use the PHP Standard Library to implement Objects that have array like properties.

```php
foreach($products_collection as $product) {
    echo $product->getName();
}
```

You may be wondering what the "addAttributeToSelect" method is for. Maho has two broad types of Model objects. One is a traditional "One Object, One Table" Active Record style Model. When you instantiate these Models, all attributes are automatically selected.

The second type of Model is an Entity Attribute Value (EAV) Model. EAV Models spread data across several different tables in the database. This gives the Maho system the flexibility to offer its flexible product attribute system without having to do a schema change each time you add an attribute. When creating a collection of EAV objects, Maho is conservative in the number of columns it will query for, so you can use addAttributeToSelect to get the columns you want, or addAttributeToSelect('*') to get all columns.

## Helpers

Maho's Helper classes contain utility methods that will allow you to perform common tasks on objects and variables. For example:

```php
$helper = Mage::helper('catalog');
```

You'll notice we've left off the second part of the grouped class name. Each Module has a default Data Helper class. The following is equivalent to the above:

```php
$helper = Mage::helper('catalog/data');
```

Most Helpers inherit form Mage_Core_Helper_Abstract, which gives you several useful methods by default.

```php
$translated_output =  $helper->__('Maho is Great'); //gettext style translations
if ($helper->isModuleOutputEnabled()): //is output for this module on or off?
```

## Layouts

So, we've seen Controllers, Models, and Helpers. In a typical PHP MVC system, after we've manipulated our Models we would

1.  Set some variables for our view
2.  The system would load a default "outer" HTML layout>
3.  The system would then load our view inside that outer layout

However, if you look at a typical Maho Controller action, you don't see any of this:

```php
/**
 * View product gallery action
 */
public function galleryAction()
{
    if (!$this->_initProduct()) {
        if (isset($_GET['store']) && !$this->getResponse()->isRedirect()) {
            $this->_redirect('');
        } elseif (!$this->getResponse()->isRedirect()) {
            $this->_forward('noRoute');
        }
    
        return;
    }

    $this->loadLayout();
    $this->renderLayout();
}
```

Instead, the Controller action ends with two calls

```php
$this->loadLayout();
$this->renderLayout();
```

So, the "V" in Maho's MVC already differs from what you're probably used to, in that you need to explicitly kick off rendering the layout.

The layout itself also differs. A Maho Layout is an object that contains a nested/tree collection of "Block" objects. Each Block object will render a specific bit of HTML. Block objects do this through a combination of PHP code, and including PHP .phtml template files.

Blocks objects are meant to interact with the Maho system to retrieve data from Models, while the phtml template files will produce the HTML needed for a page.

For example, the page header Block app/code/core/Mage/Page/Block/Html/Head.php uses the head.phtml file page/html/head.phtml.

Another way of thinking about it is the Block classes are almost like little mini-controllers, and the .phtml files are the view.

By default, when you call

```php
$this->loadLayout();
$this->renderLayout();
```

Maho will load up a Layout with a skeleton site structure. There will be Structure Blocks to give you your html, head, and body, as well as HTML to setup single or multiple columns of Layout. Additionally, there will be a few Content Blocks for the navigation, default welcome message, etc.

"Structure" and "Content" are arbitrary designations in the Layout system. A Block doesn't programmatically know if it's Structure or Content, but it's useful to think of a Block as one or the other.

To add Content to this Layout you need to tell the Maho system something like

"Hey, Maho, add these additional Blocks under the "content" Block of the skeleton"

or

"Hey, Maho, add these additional Blocks under the "left column" Block of the skeleton"

This can be done programmatically in a Controller action

```php
public function indexAction()
{
    $this->loadLayout();
    $block = $this->getLayout()->createBlock('adminhtml/system_account_edit')
    $this->getLayout()->getBlock('content')->append($block);
    $this->renderLayout();
}
```

but more commonly (at least in the frontend cart application), is use of the XML Layout system.

The Layout XML files in a theme allow you, on a per Controller basis, to remove Blocks that would normally be rendered, or add Blocks to that default skeleton areas. For example, consider this Layout XML file:

```xml
<catalog_category_default>
    <reference name="left">
        <block type="catalog/navigation" name="catalog.leftnav" after="currency" template="catalog/navigation/left.phtml"/>
    </reference>
</catalog_category_default>
```

It's saying in the catalog Module, in the category Controller, and the default Action, insert the catalog/navigation Block into the "left" structure Block, using the catalog/navigation/left.phtml template.

One last important thing about Blocks. You'll often see code in templates that looks like this:

```php
$this->getChildHtml('order_items');
```

This is how a Block renders a nested Block. However, a Block can only render a child Block if the child Block **is included as a nested Block in the Layout XML file**. In the example above our catalog/navigation Block has no nested Blocks. This means any call to `$this->getChildHtml()` in left.phtml will render as blank.

If, however, we had something like:

```xml
<catalog_category_default>
    <reference name="left">
        <block type="catalog/navigation" name="catalog.leftnav" after="currency" template="catalog/navigation/left.phtml">
            <block type="core/template" name="foobar" template="foo/baz/bar.phtml"/>
        </block>
    </reference>
</catalog_category_default>
```

From the catalog/navigation Block, we'd be able to call

```php
$this->getChildHtml('foobar');
```

## Observers

Like any good object-oriented system, Maho implements an Event/Observer pattern for end users to hook into. As certain actions happen during a Page request (a Model is saved, a user logs in, etc.), Maho will issue an event signal.

When creating your own Modules, you can "listen" for these events. Say you wanted to get an email every time a certain customer logged into the store. You could listen for the "customer_login" event (setup in config.xml)

```xml
<events>
    <customer_login>
        <observers>
            <unique_name>
                <type>singleton</type>
                <class>mymodule/observer</class>
                <method>iSpyWithMyLittleEye</method>
            </unique_name>
        </observers>
    </customer_login>
</events>
```

and then write some code that would run whenever a user logged in:

```php
class Packagename_Mymodule_Model_Observer
{
    public function iSpyWithMyLittleEye($observer)
    {
        $data = $observer->getData();
        //code to check observer data for our user,
        //and take some action goes here
    }
}
```

!!! tip "Modern alternative"
    XML observer registration still works, but since v26.5 the recommended way is the `#[Maho\Config\Observer]` PHP attribute placed directly on the observer method. See [Event observers](../observers.md).

## Class Overrides

Finally, the Maho System offers you the ability to replace Model, Helper and Block classes from the core modules with your own. This is a feature that's similar to "Duck Typing" or "Monkey Patching" in a language like Ruby or Python.

Here's an example to help you understand. The Model class for a product is Mage_Catalog_Model_Product.

Whenever the following code is called, a `Mage_Catalog_Model_Product` object is created

```php
$product = Mage::getModel('catalog/product');
```

This is a factory pattern.

What Maho's class override system does is allow you to tell the system

"Hey, whenever anyone asks for a catalog/product, instead of giving them a Mage_Catalog_Model_Product,
give them a `Packagename_Modulename_Model_Foobazproduct` instead".

Then, if you want, your `Packagename_Modulename_Model_Foobazproduct` class can extend the original product class

```php
class Packagename_Modulename_Model_Foobazproduct extends Mage_Catalog_Model_Product
{
}
```

Which will allow you to change the behavior of any method on the class, but keep the functionality of the existing methods.

```php
class Packagename_Modulename_Model_Foobazproduct extends Mage_Catalog_Model_Product
{
    public function validate()
    {
        //add custom validation functionality here
        return $this;
    }

}
```

As you might expect, this overriding (or rewriting) is done in the config.xml file.

```xml
<models>
    <catalog>
        <rewrite>
            <product>Packagename_Modulename_Model_Foobazproduct</product>
        </rewrite>
    </catalog>
</models>
```

One thing that's important to note here. Individual classes in **your** Module are overriding individual classes in **other** Modules. You are not, however, overriding the entire Module. This allows you to change specific method behavior without having to worry what the rest of the Module is doing.