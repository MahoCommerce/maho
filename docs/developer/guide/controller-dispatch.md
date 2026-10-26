---
description: Understand how Maho's MVC controller dispatch works, from URL routing through the front controller to action methods that load models and prepare views.
---

# Controller Dispatch

## Introduction

The Model-View-Controller (MVC) architecture traces its origins back to the Smalltalk Programming language and Xerox Parc. Since then, there have been many systems that describe their architecture as MVC. Each system is slightly different, but all have the goal of separating data access, business logic, and user-interface code from one another.

The architecture of most PHP MVC frameworks looks something like this:

```mermaid
flowchart LR
    A[URL] --> B[Front Controller]
    B --> C[Routing]
    C --> D[Controller Action]
    D --> E[Models]
    D --> F[Data structure]
    F --> G[View renders HTML]
```

1.  A URL is intercepted by a single PHP file (usually called a Front Controller).
2.  This PHP file will examine the URL, and derive a Controller name and an Action name (a process that's often called routing).
3.  The derived Controller is instantiated.
4.  The method name matching the derived Action name is called on the Controller.
5.  This Action method will instantiate and call methods on models, depending on the request variables.
6.  The Action method will also prepare a data structure of information. This data structure is passed on to the view.
7.  The view then renders HTML, using the information in the data structure it has received from the Controller.

While this pattern was a great leap forward from the "each php file is a page" pattern established early on, for some software engineers, it's still an ugly hack. Common complaints are:

*   The Front Controller PHP file still operates in the global namespace.
*   Convention over configuration leads to less modularity.
    *   URLs routing is often inflexible.
    *   Controllers are often bound to specific views.
    *   Even when a system offers a way to override these defaults, the convention leads to applications where it's difficult/impossible to drop in a new model, view, or Controller implementation without massive re-factoring.

As you've probably guessed, the Maho team shares this world view and has created a more abstract MVC pattern that looks something like this:

```mermaid
flowchart LR
    A[URL] --> B[Front Controller]
    B --> C[Routers]
    C --> D[Action Controller]
    D --> E[Models]
    D --> F[Layout]
    F --> G[Blocks]
    G --> E
    G --> H[Templates render HTML]
```

1.  A URL is intercepted by a single PHP file.
2.  This PHP file instantiates a Maho application.
3.  The Maho application instantiates a Front Controller object.
4.  Front Controller instantiates any number of Router objects (specified in global config).
5.  Routers check the request URL for a "match".
6.  If a match is found, an Action Controller and Action are derived.
7.  The Action Controller is instantiated and the method name matching the Action Name is called.
8.  This Action method will instantiate and call methods on models, depending on the request.
9.  This Action Controller will then instantiate a Layout Object.
10.  This Layout Object will, based some request variables and system properties (also known as "handles"), create a list of Block objects that are valid for this request.
11.  Layout will also call an output method on certain Block objects, which start a nested rendering (Blocks will include other Blocks).
12.  Each Block has a corresponding Template file. Blocks contain PHP logic, templates contain HTML and PHP output code.
13.  Blocks refer directly back to the models for their data. In other words, **the Action Controller does not pass them a data structure.**

We'll eventually touch on each part of this request, but for now we're concerned with the **Front Controller -> Routers -> Action Controller** section.

## Hello World

Enough theory, it's time for Hello World. We're going to

1.  Create a Hello World module in the Maho system
2.  Configure this module with routes
3.  Create Action Controller(s) for our routes

### Create Hello World Module

First, we'll create a directory structure for this module. Our directory structure should look as follows:

app/code/local/Mahotutorial/Helloworld/Block
app/code/local/Mahotutorial/Helloworld/controllers
app/code/local/Mahotutorial/Helloworld/etc   
app/code/local/Mahotutorial/Helloworld/Helper
app/code/local/Mahotutorial/Helloworld/Model
app/code/local/Mahotutorial/Helloworld/sql

Then create a configuration file for the module (at path app/code/local/Mahotutorial/Helloworld/etc/config.xml):

```xml
<config>    
    <modules>
        <Mahotutorial_Helloworld>
            <version>0.1.0</version>
        </Mahotutorial_Helloworld>
    </modules>
</config>
```

Then create a file to activate the module (at path app/etc/modules/Mahotutorial_Helloworld.xml):

```xml
<config>
    <modules>
        <Mahotutorial_Helloworld>
            <active>true</active>
            <codePool>local</codePool>
        </Mahotutorial_Helloworld>
    </modules>
</config>
```

Finally, we ensure the module is active:

1.  Clear your Maho cache.
2.  In the Maho Admin, go to **System->Configuration->Advanced**.
3.  Expand "Disable Modules Output" (if it isn't already).
4.  Ensure that Mahotutorial_Helloworld shows up.

### Configuring Routes

Next, we're going to configure a route. A route will turn a URL into an Action Controller and a method.

Maho supports two ways to declare routes: PHP attributes (recommended, since v26.5) and XML (legacy, still supported as a back-compatibility shim).

#### PHP attributes <span class="version-badge">v26.5+</span>

Since v26.5, the recommended approach is to declare routes with the `#[Maho\Config\Route]` attribute directly on the action method. No XML, no front-name registration. See [Routing](../routing.md) for the full reference.

```php
class Mahotutorial_Helloworld_IndexController extends Mage_Core_Controller_Front_Action
{
    #[Maho\Config\Route('/helloworld', name: 'helloworld.index')]
    #[Maho\Config\Route('/helloworld/index', name: 'helloworld.index.index')]
    public function indexAction()
    {
        echo 'Hello World';
    }
}
```

After adding the attribute, run:

```bash
composer dump-autoload
```

Maho compiles the route into `vendor/composer/maho_url_matcher.php`. No `config.xml` route block is needed.

#### XML routers (legacy)

Modules upgraded from older Maho or OpenMage versions still use the XML form. It continues to work via a back-compatibility shim, so you don't need to migrate immediately.

In your config.xml file (at path app/code/local/Mahotutorial/Helloworld/etc/config.xml), add the following section:

```xml
<config>    
    ...
    <frontend>
        <routers>
            <helloworld>
                <use>standard</use>
                <args>
                    <module>Mahotutorial_Helloworld</module>
                    <frontName>helloworld</frontName>
                </args>
            </helloworld>
        </routers>  
    </frontend>
    ...
</config>
```

We have a lot of new terminology here, let's break it down.

#### What is a `<frontend>`?

The `<frontend>` tag refers to a Maho Area. For now, think of Areas as individual Maho applications. The "frontend" Area is the public facing Maho shopping cart application. The "admin" Area is the private administrative console application. The "install" Area is the application you use to run though installing Maho the first time.

#### Why a `<routers>` tags if we're configuring individual routes?

There's a famous quote about computer science, often attributed to Phil Karlton:

> "There are only two hard things in Computer Science: cache invalidation and naming things"

Maho, like all large systems, suffers from the naming problem in spades. You'll find there are many places in the global config, and the system in general, where the naming conventions seem unintuitive or even ambiguous. This is one of those places. Sometimes the `<routers>` tag will enclose configuration information about routers, other times it will enclose configuration information about the actual router objects that do the routing. This is going to seem counter intuitive at first, but as you start to work with Maho more and more, you'll start to understand its world view a little better. (Or, in the words of Han Solo, "Hey, trust me!").

#### What is a `<frontName>`?

When a router parses a URL, it gets separated as follows

`http://example.com/frontName/actionControllerName/actionMethod/`

So, by defining a value of "helloworld" in the <frontName> tags, we're telling Maho that we want the system to respond to URLs in the form of

`http://example.com/helloworld/*`

Many developers new to Maho confuse this frontName with the Front Controller object. They are **not** the same thing. The frontName belongs solely to routing.

#### What's the `<helloworld>` tag for?

This tag should be the lowercase version of you module name. Our module name is Helloworld, this tag is helloworld. Technically this tag defines our _route name_

You'll also notice our frontName matches our module name. It's a loose convention to have frontNames match the module names, but it's not a requirement. In your own modules, it's probably better to use a route name that's a combination of your module name and package name to avoid possible namespace collisions.

#### What's `<module>Mahotutorial_Helloworld</module>` for?

This module tag should be the full name of your module, including its package/namespace name. This will be used by the system to locate your Controller files.

### Create Action Controller(s) for our Routes

One last step to go, and we'll have our Action Controller. Create a file at

`app/code/local/Mahotutorial/Helloworld/controllers/IndexController.php`

That contains the following

```php
class Mahotutorial_Helloworld_IndexController extends Mage_Core_Controller_Front_Action
{
    public function indexAction()
    {
        echo 'Hello World';
    }
}
```

Clear your config cache, and load the following URL

`http://example.com/helloworld/index/index`

You should also be able to load

```
http://example.com/helloworld/index/
http://example.com/helloworld/
```

You should see a blank page with the text "Hello World". Congratulations, you've setup your first Maho Controller!

#### Where do Action Controllers go?

Action Controllers should be placed in a module's controllers (lowercase c) folder. This is where the system will look for them.

#### How should Action Controllers be named?

Remember the `<module>` tag back in config.xml?

```xml
<module>Mahotutorial_Helloworld</module>
```

An Action Controller's name will

1.  Start with this `<module>` string specified in config.xml (Mahotutorial_Helloworld)
2.  Be followed by an underscore (Mahotutorial_Helloworld_)
3.  Which will be followed by the Action Controller's name (Mahotutorial_Helloworld_Index)
4.  And finally, the word "Controller" (Mahotutorial_Helloworld_IndexController)

All Action Controllers need Mage_Core_Controller_Front_Action as an ancestor.

#### What's that index/index nonsense?

As we previously mentioned, Maho URLs are routed (by default) as follows

`http://example.com/frontName/actionControllerName/actionMethod/`

So in the URL

`http://example.com/helloworld/index/index`

the URI portion "helloworld" is the frontName, which is followed by index (The Action Controller name), which is followed by another index, which is the name of the Action Method that will be called. (an Action of index will call the method public function **index**Action(){...}.

If a URL is incomplete, Maho uses "index" as the default, which is why the following URLs are equivalent.

```
http://example.com/helloworld/index
http://example.com/helloworld
```

If we had a URL that looked like this

`http://example.com/checkout/cart/add`

Maho would

1.  Consult the global config to find the module to use for the frontName checkout (Mage_Checkout)
2.  Look for the cart Action Controller (Mage_Checkout_CartController)
3.  Call the addAction method on the cart Action Controller

#### Other Action Controller Tricks

Let's try adding a non-default method to our Action Controller. Add the following code to IndexController.php

```php
public function goodbyeAction()
{
    echo 'Goodbye World!';
}
```

And then visit the URL to test it out:

`http://example.com/helloworld/index/goodbye`

Because we're extending the Mage_Core_Controller_Front_Action class, we get some methods for free. For example, additional URL elements are automatically parsed into key/value pairs for us. Add the following method to your Action Controller.

```php
public function paramsAction()
{
    echo '<dl>';            
    foreach($this->getRequest()->getParams() as $key=>$value) {
        echo '<dt><strong>Param:</strong>'.$key.'</dt>';
        echo '<dt><strong>Value: </strong>'.$value.'</dt>';
    }
    echo '</dl>';
}
```

and visit the following URL

`http://example.com/helloworld/index/params?foo=bar&baz=eof`

You should see each parameter and value printed out.

Finally, what would we do if we wanted a URL that responded at

`http://example.com/helloworld/messages/goodbye`

Here our Action Controller's name is messages, so we'd create a file at

`app/code/local/Mahotutorial/Helloworld/controllers/MessagesController.php`

with an Action Controller named Mahotutorial_Helloworld_MessagesController and an Action Method that looked something like

```php
public function goodbyeAction()         
{
    echo 'Another Goodbye';
}
```

And that, in a nutshell, is how Maho implements the Controller portion of MVC. While it's a little more complicated than other PHP MVC framework's, it's a highly flexible system that will allow you build almost any URL structure you want.