---
description: Learn Maho models and ORM basics, from ActiveRecord-style simple models to EAV, with model resources handling database reads and writes and free collections.
---

# Models and ORM Basics

## Introduction

The implementation of a "Models Tier" is a huge part of any MVC framework. It represents the data of your application, and most applications are useless without data. Maho Models play an even bigger role, as they typically contain the "Business Logic" that's often relegated to the Controller or Helper methods in other PHP MVC frameworks.

## Traditional PHP MVC Models

If the definition of MVC is somewhat fuzzy, the definition of a Model is even fuzzier. Prior to the wide adoption of the MVC pattern by PHP developers, data access was usually raw SQL statements and/or an SQL abstraction layer. Developers would write queries and not think too much about what objects they were modeling.

In this day and age, raw SQL is mostly frowned upon, but many PHP frameworks are still SQL centric. Models will be objects that provide some layer of abstraction, but behind the scenes developers are still writing SQL and/or calling SQL like abstraction methods to read and write-down their data.

Other frameworks eschew SQL and take the Object Relational Mapping (ORM) approach. Here, a developer is dealing strictly with Objects. Properties are set, and when a save method is called on the Object, the data is automatically written to the database. Some ORMs will attempt to divine object properties from the database, others require the user to specify them in some way, (usually in an abstract data language such as YAML). One of the most famous and popular implementations of this approach is ActiveRecord.

This definition of ORM should suffice for now, but like everything Computer Science these days, the strict definition of ORM has blurred over the years. It's beyond the scope of this article to settle that dispute, but suffice it say we're generalizing a bit.

## Maho Models

It should be no surprise that Maho takes the ORM approach. While the Zend Framework SQL abstractions are available, most of your data access will be via the built in Maho Models, and Models you build yourself. It should also come as no surprise that Maho has a highly flexible, highly abstract, concept of what a Model is.

### Anatomy of a Maho Model

Most Maho Models can be categorized in one of two ways. There's a basic, ActiveRecord-like/one-object-one-table Model, and there's also an Entity Attribute Value (EAV) Model. Each Model also gets a Model Collection. Collections are PHP objects used to hold a number of individual Maho Model instances. The Maho team has implemented the PHP Standard Library interfaces of IteratorAggregate and Countable to allow each Model type to have it's own collection type. If you're not familiar with the PHP Standard Library, think of Model Collections as arrays that also have methods attached.

Maho Models don't contain any code for connecting to the database. Instead, each Model uses a modelResource class, that is used to communicate with the database server (via one read and one write adapter object). By decoupling the logical Model and the code that talks to the database, it's theoretically possible to write new resource classes for a different database schemas and platforms while keeping your Models themselves untouched.

## Enable developer mode

Something you should do in development, but _never_ in production, is to enable Maho's _developer mode_ which, among other things, displays exceptions in your browser. It's useful for debugging your code.

## Creating a Basic Model

To begin, we're going to create a basic Maho Model. PHP MVC tradition insists we model a weblog post. The steps we'll need to take are

1.  Create a new "Weblog" module
2.  Create a database table for our Model
3.  Add Model information to the config for a Model named Blogpost
4.  Add Model Resource information to the config for the Blogpost Model
5.  Add a PHP class file for the Blogpost Model
6.  Add a PHP class file for the Blogpost Resource Model
7.  Instantiate the Model

### Create a Weblog Module

You should be an old hat at creating empty modules at this point, so we'll skip the details and assume you can create an empty module named Weblog. After you've done that, we'll setup a route for an index Action Controller with an action named "testModel". As always, the following examples assume a Package Name of "Mahotutorial".

In `Mahotutorial/Weblog/etc/config.xml`, setup the following route

```xml
<frontend>
    <routers>
        <weblog>
            <use>standard</use>
            <args>
                <module>Mahotutorial_Weblog</module>
                <frontName>weblog</frontName>
            </args>
        </weblog>
    </routers>
</frontend>
```

!!! tip "Modern alternative"
    XML route registration still works, but since v26.5 the recommended way is the `#[Maho\Config\Route]` PHP attribute placed directly on controller action methods. See [Routing](../routing.md).

And then add the following Action Controller in

```php
class Mahotutorial_Weblog_IndexController extends Mage_Core_Controller_Front_Action
{
    public function testModelAction() {
        echo 'Setup!';
    }
}
```

at Mahotutorial/Weblog/controllers/IndexController.php. Clear your Maho cache and load the following URL to ensure everything's been setup correctly.

`http://example.com/weblog/index/testModel`

You should see the word "Setup" on a white background.

### Creating the Database Table

Maho has systems for creating and changing your database schemas - the modern [declarative schema](../declarative-database-schema.md) (v26.7+) and the classic [setup resources](setup-resources.md) covered later in this tutorial - but for the time being we'll just manually create a table so we can focus on the Model itself.

Using the command-line or your favorite MySQL GUI application, create a table with the following schema

```sql
CREATE TABLE `blog_posts` (
  `blogpost_id` int(11) NOT NULL auto_increment,
  `title` text,
  `post` text,
  `date` datetime default NULL,
  `timestamp` timestamp NOT NULL default CURRENT_TIMESTAMP,
  PRIMARY KEY  (`blogpost_id`)
);
```

And then populate it with some data

```sql
INSERT INTO `blog_posts` VALUES (1,'My New Title','This is a blog post','2010-07-01 00:00:00','2010-07-02 23:12:30');
```

### The Global Config and Creating The Model

There are three individual things we need to setup for a Model in our config.

1.  Enabling Models in our Module
2.  Enabling Model Resources in our Module
3.  Add an "entity" table configuration to our Model Resource.

When you instantiate a Model in Maho, you make a call like this

```php
$model = Mage::getModel('weblog/blogpost');
```

The first part of the URI you pass into `getModel()` is the Model Group Name. Because it is a good idea to follow conventions, this should be the (lowercase) name of your module, or to be safeguarded against conflicts use the packagename and modulename (also in lowercase). The second part of the URI is the lowercase version of your Model name.

So, let's add the following XML to our module's `config.xml`.

```xml
<global>
    <!-- ... -->
    <models>
        <weblog>
            <class>Mahotutorial_Weblog_Model</class>
            <!--
            need to create our own resource, can't just
            use core_resource
            -->
            <resourceModel>weblog_resource</resourceModel>
        </weblog>
    </models>
    <!-- ... -->
</global>
```

The outer `<weblog />` tag is your Group Name, which should match your module name. `<class />` is the BASE name all Models in the weblog group will have, also called **Class Prefix**. The `<resourceModel />` tag indicates which Resource Model that weblog group Models should use. We talk more about this later on in this page. For now, remember your Group Name and the literal string "resource".

So, we're not done yet, but let's see what happens if we clear our Maho cache and attempt to instantiate a blogpost Model. In your `testModelAction` method, use the following code

```php
public function testModelAction()
{
    $blogpost = Mage::getModel('weblog/blogpost');
    echo get_class($blogpost);
}
```

and reload your page. You should see an error like the following:

```
include(Mahotutorial/Weblog/Model/Blogpost.php) [function.include]: failed to open stream: No such file or directory
```

By attempting to retrieve a weblog/blogpost Model, you told Maho to instantiate a class with the name

`Mahotutorial_Weblog_Model_Blogpost`

Maho is trying to __autoload include this Model, but can't find the file. Let's create it! Create the following class at the following location

File: app/code/local/Mahotutorial/Weblog/Model/Blogpost.php

```php
class Mahotutorial_Weblog_Model_Blogpost extends Mage_Core_Model_Abstract
{
    protected function _construct()
    {
        $this->_init('weblog/blogpost');
    }
}
```

Reload your page, and the exception should be replaced with the name of your class.

All basic Models that interact with the database should extend the Mage_Core_Model_Abstract class. By convention you override a single method named _construct (**NOTE:** this is not PHP's constructor __construct). The base _construct is a concrete, empty method, so overriding it is conventional rather than required. Your override should call the class's _init method with the same identifying URI you'll be using in the Mage::getModel method call.

### The Global Config and Resources

So, we've setup our Model. Next, we need to setup our Model Resource. Model Resources contain the code that actually talks to our database. In the last section, we included the following in our config.

```xml
<resourceModel>weblog_resource</resourceModel>
```

The value in `<resourceModel />` will be used to instantiate a Model Resource class. Although you'll never need to call it yourself, when any Model in the weblog group needs to talk to the database, Maho will make the following method call to get the Model resource

```php
Mage::getResourceModel('weblog/blogpost');
```

Again, weblog is the Group Name, and blogpost is the Model. The Mage::getResourceModel method will use the weblog/blogpost URI to inspect the global config and pull out the value in `<resourceModel>` (in this case, weblog_resource). Then, a model class will be instantiated with the following URI

`weblog_resource/blogpost`

So, if you followed that all the way, what this means is, **resource models are configured in the same section of the XML config as normal Models**. This can be confusing to newcomers and old-hands alike.

So, with that in mind, let's configure our resource. In our <models> section add

```xml
<global>
    <!-- ... -->
    <models>
        <!-- ... -->
        <weblog_resource>
            <class>Mahotutorial_Weblog_Model_Resource</class>
        </weblog_resource>
    </models>
</global>
```

You're adding the `<weblog_resource />` tag, which is the value of the `<resourceModel />` tag you just setup. The value of `<class />` is the base name that all your resource models will have, and should be named with the following format

`Packagename_Modulename_Model_Resource`

So, we have a configured resource, let's try loading up some Model data. Change your action to look like the following

```php
public function testModelAction()
{
    $params = $this->getRequest()->getParams();
    $blogpost = Mage::getModel('weblog/blogpost');
    echo("Loading the blogpost with an ID of ".$params['id']);
    $blogpost->load($params['id']);
    $data = $blogpost->getData();
    var_dump($data);
}
```

And then load the following URL in your browser (after clearing your Maho cache)

`http://example.com/weblog/index/testModel/id/1`

You should see an exception something like the following

```
Warning: include(Mahotutorial/Weblog/Model/Resource/Blogpost.php) [function.include]: failed to open stream: No such file ....
```

As you've likely intuited, we need to add a resource class for our Model. **Every** Model has its own resource class. Add the following class at the following location

File: app/code/local/Mahotutorial/Weblog/Model/Resource/Blogpost.php

```php
class Mahotutorial_Weblog_Model_Resource_Blogpost extends Mage_Core_Model_Resource_Db_Abstract
{
    protected function _construct()
    {
        $this->_init('weblog/blogpost', 'blogpost_id');
    }
}
```

Again, the first parameter of the init method is the URL used to identify the **Model**. The second parameter is the database field that uniquely identifies any particular column. In most cases, this should be the primary key. Clear your cache, reload, and you should see

Can't retrieve entity config: weblog/blogpost

Another exception! When we use the Model URI weblog/blogpost, we're telling Maho we want the Model Group weblog, and the blogpost _Entity_. In the context of simple Models that extend Mage_Core_Model_Resource_Db_Abstract, an entity corresponds to a table. In this case, the table named blog_post that we created above. Let's add that entity to our XML config.

```xml
<models>
    <!-- ... --->
    <weblog_resource>
        <class>Mahotutorial_Weblog_Model_Resource</class>
        <entities>
            <blogpost>
                <table>blog_posts</table>
            </blogpost>
        </entities>
    </weblog_resource>
</models>
```

We've added a new `<entities />` section to the resource Model section of our config. This, in turn, has a section named after our entity (`<blogpost />`) that specifies the name of the database table we want to use for this Model.

Clear your Maho cache, cross your fingers, reload the page and ...

Loading the blogpost with an ID of 1

```php
array
  'blogpost_id' => string '1' (length=1)
  'title' => string 'My New Title' (length=12)
  'post' => string 'This is a blog post' (length=19)
  'date' => string '2009-07-01 00:00:00' (length=19)
  'timestamp' => string '2009-07-02 16:12:30' (length=19)
```

Eureka! We've managed to extract our data and, more importantly, completely configure a Maho Model.

## Basic Model Operations

All Maho Models inherit from the \Maho\DataObject class. This class is part of the Maho system library and **not** part of any Maho core module. You can find this object at

`lib/Maho/DataObject.php`

(The legacy name Varien_Object still works as a backward-compatible class alias, but \Maho\DataObject is the current, future-proof name.)

Maho Models store their data in a protected _data property. The \Maho\DataObject class gives us several methods we can use to extract this data. You've already seen getData, which will return an array of key/value pairs. This method can also be passed a string key to get a specific field.

```php
$model->getData();
$model->getData('title');
```

There's also a getOrigData method, which will return the Model data as it was when the object was initially populated, (working with the protected _origData method).

```php
$model->getOrigData();
$model->getOrigData('title');
```

The \Maho\DataObject also implements some special methods via PHP's magic __call method. You can get, set, unset, or check for the existence of any property using a method that begins with the word get, set, unset or has and is followed by the camel cased name of a property.

```php
$model->getBlogpostId();
$model->setBlogpostId(25);
$model->unsBlogpostId();
if($model->hasBlogpostId()){...}
```

For this reason, you'll want to name all your database columns with lower case characters and use underscores to separate characters.

## CRUD, the Maho Way

Maho Models support the basic Create, Read, Update, and Delete functionality of CRUD with load, save, and delete methods. You've already seen the load method in action. When passed a single parameter, the load method will return a record whose id field (set in the Model's resource) matches the passed in value.

```php
$blogpost->load(1);
```

The save method will allow you to both INSERT a new Model into the database, or UPDATE an existing one. Add the following method to your Controller

```php
public function createNewPostAction()
{
    $blogpost = Mage::getModel('weblog/blogpost');
    $blogpost->setTitle('Code Post!');
    $blogpost->setPost('This post was created from code!');
    $blogpost->save();
    echo 'post with ID ' . $blogpost->getId() . ' created';
}
```

and then execute your Controller Action by loading the following URL

`http://example.com/weblog/index/createNewPost`

You should now see an additional saved post in your database table. Next, try the following to edit your post

```php
public function editFirstPostAction()
{
    $blogpost = Mage::getModel('weblog/blogpost');
    $blogpost->load(1);
    $blogpost->setTitle("The First post!");
    $blogpost->save();
    echo 'post edited';
}
```

And finally, you can delete your post using very similar syntax.

```php
public function deleteFirstPostAction()
{
    $blogpost = Mage::getModel('weblog/blogpost');
    $blogpost->load(1);
    $blogpost->delete();
    echo 'post removed';
}
```

## Model Collections

So, having a single Model is useful, but sometimes we want to grab list of Models. Rather than returning a simple array of Models, each Maho Model type has a unique collection object associated with it. These objects implement the PHP IteratorAggregate and Countable interfaces, which means they can be passed to the count function, and used in for each constructs.

We'll cover Collections in full in a later article, but for now let's look at basic setup and usage. Add the following action method to your Controller, and load it in your browser.

```php
public function showAllBlogPostsAction()
{
    $posts = Mage::getModel('weblog/blogpost')->getCollection();
    foreach($posts as $blogpost){
        echo '<h3>'.$blogpost->getTitle().'</h3>';
        echo nl2br($blogpost->getPost());
    }
}
```

Load the action URL,

`http://example.com/weblog/index/showAllBlogPosts`

and you should see a (by now) familiar exception.

`Warning: include(Mahotutorial/Weblog/Model/Resource/Blogpost/Collection.php) [function.include]: failed to open stream`

You're not surprised, are you? We need to add a PHP class file that defines our Blogpost collection. Every Model has a protected property named _resourceCollectionName that contains a URI that's used to identify our collection.

`  protected '_resourceCollectionName' => string 'weblog/blogpost_collection'`

By default, this is the same URI that's used to identify our Resource Model, with the string "_collection" appended to the end. Maho considers Collections part of the Resource, so this URI is converted into the class name.

`Mahotutorial_Weblog_Model_Resource_Blogpost_Collection`

Add the following PHP class at the following location

File: app/code/local/Mahotutorial/Weblog/Model/Resource/Blogpost/Collection.php

```php
class Mahotutorial_Weblog_Model_Resource_Blogpost_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    protected function _construct()
    {
        $this->_init('weblog/blogpost');
    }
}
```

Just as with our other classes, we need to init our Collection with the Model URI. (weblog/blogpost). Rerun your Controller Action, and you should see your post information.
