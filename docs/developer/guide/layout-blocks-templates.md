---
description: Understand Maho's layout, block and template system, how blocks are PHP objects tied to phtml templates and reference models directly to render the view.
---

# Layouts, Blocks and Templates

## Introduction

Developers new to Maho are often confused by the Layout and View system. This article will take a look at Maho's Layout/Block approach, and show you how it fits into Maho MVC worldview.

Unlike many popular MVC systems, Maho's Action Controller does **not** pass a data object to the view or set properties on the view object (with a few exceptions). Instead, the View component directly references system models to get the information it needs for display.

One consequence of this design decision is that the View has been separated into Blocks and Templates. Blocks are PHP objects, Templates are "raw" PHP files (with a .phtml extension) that contain a mix of HTML and PHP (where PHP is used as a templating language). Each Block is tied to a single Template file. Inside a phtml file, PHP's $this keyword will contain a reference to the Template's Block object.  

## A quick example

Take a look at the default product Template at the file at

`app/design/frontend/base/default/template/catalog/product/list.phtml`

You'll see the following PHP template code.

```php
<?php $_productCollection=$this->getLoadedProductCollection() ?>
<?php if(!$_productCollection->count()): ?> <div class="note-msg">
    <?= $this->__("There are no products matching the selection.") ?>
</div> <?php else: ?>
```

The getLoadedProductCollection method can be found in the Template's Block class, Mage_Catalog_Block_Product_List as shown:

File: app/code/core/Mage/Catalog/Block/Product/List.php

```php
public function getLoadedProductCollection()
{
    return $this->_getProductCollection();
}
```

The block's _getProductCollection then instantiates models and reads their data, returning a result to the template.

## Nesting Blocks

The real power of Blocks/Templates come with the getChildHtml method. This allows you to include the contents of a secondary Block/Template inside of a primary Block/Template.

Blocks calling Blocks calling Blocks is how the entire HTML layout for your page is created. Take a look at the one column layout Template.

File: app/design/frontend/base/default/template/page/1column.phtml

```php
<!DOCTYPE html>
<html lang="<?= $this->getLang() ?>">
<head>
<?= $this->getChildHtml('head') ?>
</head>
<body<?= $this->getBodyClass()?' class="'.$this->getBodyClass().'"':'' ?>>
<a href="#main-content" id="skip-to-main-content"><?= $this->__('Skip to main content') ?></a>
<?= $this->getChildHtml('after_body_start') ?>
<div class="wrapper">
    <?= $this->getChildHtml('global_notices') ?>
    <div class="page">
        <?= $this->getChildHtml('header') ?>
        <main class="main-container col1-layout" role="main">
            <div class="main">
                <?= $this->getChildHtml('breadcrumbs') ?>
                <?= $this->getChildHtml('title') ?>
                <div class="col-main" id="main-content">
                    <?= $this->getChildHtml('global_messages') ?>
                    <?= $this->getChildHtml('content') ?>
                </div>
            </div>
        </main>
        <?= $this->getChildHtml('footer_before') ?>
        <?= $this->getChildHtml('footer') ?>
        <?= $this->getChildHtml('global_cookie_notice') ?>
        <?= $this->getChildHtml('before_body_end') ?>
    </div>
</div>
<?= $this->getAbsoluteFooter() ?>
</body>
</html>
```

The template itself is only a few dozen lines long. However, each call to $this->getChildHtml(...) will include and render another Block. These Blocks will, in turn, use getChildHtml to render other Blocks. It's Blocks all the way down.

## The Layout

So, Blocks and Templates are all well and good, but you're probably wondering

1.  How do I tell Maho which Blocks I want to use on a page?
2.  How do I tell Maho which Block I should start rendering with?
3.  How do I specify a particular Block in getChildHtml(...)? Those argument strings don't look like Block names to me.

This is where the Layout Object enters the picture. The Layout Object is an XML object that will define which Blocks are included on a page, and which Block(s) should kick off the rendering process.

Last time we were echoing content directly from our Action Methods. This time let's create a simple HTML template for our Hello World module.

First, create a file at

`app/design/frontend/base/default/layout/local.xml`

with the following contents

```xml
<layout version="0.1.0">
    <default>
        <block type="page/html" name="root" output="toHtml" template="mahotutorial/helloworld/simple_page.phtml" />
    </default>
</layout>
```

Then, create a file at

`app/design/frontend/base/default/template/mahotutorial/helloworld/simple_page.phtml`

with the following contents

```html
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Hello World</title>
    <style type="text/css">
        body {
            background-color:#f00;
        }
    </style>
</head>
<body>

</body>
</html>
```

Finally, each Action Controller is responsible for kicking off the layout process. We'll need to add two method calls to the Action Method.

```php
public function indexAction()
{
    //remove our previous echo
    //echo 'Hello Index!';
    $this->loadLayout();
    $this->renderLayout();
}
```

Clear your Maho cache and reload your Hello World controller page. You should now see a website with a bright red background and an HTML source that matches what's in simple_page.phtml.

## What's Going On

So, that's a lot of voodoo and cryptic incantations. Let's take a look at what's going on.

First, let's peek at some of Maho's internals with the built-in layout debugger. From your project root, run

```bash
./maho dev:frontend:layout:debug http://example.com/helloworld/index/index
```

For any URL you give it, this command shows the layout handles that apply to the request, the layout XML files that were loaded, and the resulting block tree.

The layout XML for your page/request is made up of `<block />`, `<reference />` and `<remove />` tags. When you call the loadLayout method of your Action Controller, Maho will

1.  Generate this Layout XML
2.  Instantiate a Block class for each `<block />` tag, looking up the class using the tag's type attribute as a global config path and store it in the internal _blocks array of the layout object, using the tag's name attribute as the array key.    
3.  If the `<block />` tag contains an output attribute, its value is added to the internal _output array of the layout object.
    

Then, when you call the renderLayout method in your Action Controller, Maho will iterate over all the Blocks in the _output array, using the value of the output attribute as a callback method. This is always toHtml, and means the starting point for output will be that Block's Template.

The following sections will cover how Blocks are instantiated, how this layout file is generated, and finishes up with kicking off the output process.

## Block Instantiation

So, within a Layout XML file, a <block /> has a "type" that's actually a Grouped Class Name URI

```
<block type="page/html" ...
<block type="page/template_links" ...
```

The URI references a location in the (say it with me) global config. The first portion of the URI (in the above examples page) will be used to query the global config to find the page class name. The second portion of the URI (in the two examples above, html and template_links) will be appended to the base class name to create the class name Maho should instantiate.

We'll go through page/html as an example. First, Maho looks for the global config node at file

`app/code/core/Mage/Page/etc/config.xml`

and finds

```xml
<page>
    <class>Mage_Page_Block</class>
</page>
```

This gives us our base class prefix Mage_Page_Block. Then, the second part of the URI (html) is appended to the class name to give us our final Block class name Mage_Page_Block_Html. This is the class that will be instantiated.

If we create a block with the same name as an already existing block, the new block instance will replace the original instance. This is what we've done in our local.xml file from above.

```xml
<layout version="0.1.0">
    <default>
        <block type="page/html" name="root" output="toHtml" template="mahotutorial/helloworld/simple_page.phtml" />
    </default>
</layout>
```

The Block named root has been replaced with our Block, which points at a different phtml Template file.

## Using references

`<reference name="" />` will hook all contained XML declarations into an existing block with the specified name. Contained `<block />` nodes will be assigned as child blocks to the referenced parent block.

```xml
<layout version="0.1.0">
    <default>
        <block type="page/html" name="root" output="toHtml" template="page/2columns-left.phtml">
            <!-- ... sub blocks ... -->
        </block>
    </default>
</layout>
```

In a different layout file:

```xml
<layout version="0.1.0">
    <default>
        <reference name="root">
            <!-- ... another sub block ... -->
            <block type="page/someothertype" name="some.other.block.name" template="path/to/some/other/template" />
        </reference>
    </default>
</layout>
```

Even though the root block is declared in a separate layout XML file, the new block is added as a child block. Maho initially creates a page/html Block named root. Then, when it later encounters the reference with the same name (root), it will assign the new block some.other.block.name as a child of the root block.

## How Layout Files are Generated

So, we have a slightly better understanding of what's going on with the Layout XML, but where is this XML file coming from? To answer that question, we need to introduce two new concepts; Handles and the Package Layout.

### Handles

Each page request in Maho will generate several unique Handles. The layout debugger shows you these Handles:

```bash
./maho dev:frontend:layout:debug http://example.com/helloworld/index/index
```

You should see a list similar to the following (depending on your configuration)

1.  default
2.  STORE_bare_us
3.  THEME_frontend_default_default
4.  helloworld_index_index
5.  customer_logged_out

Each of these is a Handle. Handles are set in a variety of places within the Maho system. The two we want to pay attention to are default and helloworld_index_index. The default Handle is present in **every** request into the Maho system. The helloworld_index_index Handle is created by combining the route name (helloworld), Action Controller name (index), and Action Controller Action Method (index) into a single string. This means each possible method on an Action Controller has a Handle associated with it.

Remember that "index" is the Maho default for both Action Controllers and Action Methods, so debugging the following URL

`http://example.com/helloworld/`

will also produce a Handle named helloworld_index_index

## Package Layout

You can think of the Package Layout similar to the global config. It's a large XML tree that contains **every possible layout configuration** for a particular Maho install. The layout debugger's output lists the layout XML files that are combined to build it for the current request.

The Package Layout is created by combining the contents of all the XML layout files for the current theme (or package). For the default install, this is at

`app/design/frontend/base/default/layout/`

Behind the scenes there are `<frontend><layout><updates />` and `<adminhtml><layout><updates />` sections of the global config that contains nodes with all the file names to load for the respective area. Once the files listed in the config have been combined, Maho will merge in one last xml file, local.xml. This is the file where you're able to add your customizations to your Maho install.

## Combining Handles and The Package Layout

So, if you look at the Package Layout, you'll see some familiar tags such as <block /> and <reference />, but they're all surrounded by tags that look like

```
<default />
<catalogsearch_advanced_index />
etc...
```

These are all Handle tags. The Layout for an individual request is generated by grabbing all the sections of the Package Layout that match any Handles for the request. So, in our example above, our layout is being generated by grabbing tags from the following sections

```xml
<default />
<STORE_bare_us />
<THEME_frontend_default_default />
<helloworld_index_index />
<customer_logged_out />
```

There's one additional tag you'll need to be aware of in the Package Layout. The <update /> tag allows you to include another Handle's tags. For example

```xml
<customer_account_index>
    <!-- ... -->
    <update handle="customer_account"/>
    <!-- ... -->
</customer_account_index>
```

Is saying that requests with a customer_account_index Handle should include <blocks />s from the <customer_account /> Handle.

## Applying What We've Learned

OK, that's a lot of theory. Lets get back to what we did earlier. Knowing what we know now, adding

```xml
<layout version="0.1.0">
    <default>
        <block type="page/html" name="root" output="toHtml" template="mahotutorial/helloworld/simple_page.phtml" />
    </default>
</layout>
```

to local.xml means we've overridden the "root" tag. with a different Block. By placing this in the <default /> Handle we've ensured that this override will happen for **every page request** in the system. That's probably not what we want.

If you go to any other page in your Maho site, you'll notice they're either blank white, or have the same red background that your hello world page does. Let's change your local.xml file so it only applies to the hello world page. We'll do this by changing default to use the full action name handle (helloworld_index_index).

```xml
<layout version="0.1.0">
    <helloworld_index_index>
        <block type="page/html" name="root" output="toHtml" template="mahotutorial/helloworld/simple_page.phtml" />
    </helloworld_index_index>
</layout>
```

Clear your Maho cache, and the rest of your pages should be restored.

Right now this only applies to our index Action Method. Let's add it to the goodbye Action Method as well. In your Action Controller, modify the goodbye action so it looks like

```php
public function goodbyeAction()
{
    $this->loadLayout();
    $this->renderLayout();
}
```

If you load up the following URL, you'll notice you're still getting the default Maho layout.

`http://example.com/helloworld/index/goodbye`

We need to add a Handle for the full action name (helloworld_index_goodbye) to our local.xml file. Rather than specify a new `<block />`, lets use the update tag to include the helloworld_index_index Handle.

```xml
<layout version="0.1.0">
    <!-- ... -->
    <helloworld_index_goodbye>
        <update handle="helloworld_index_index" />
    </helloworld_index_goodbye>
</layout>
```

Loading the following pages (after clearing your Maho cache) should now produce identical results.

```
http://example.com/helloworld/index/index
http://example.com/helloworld/index/goodbye
```

## Starting Output and getChildHtml

In a standard configuration, output starts on the Block named root (because it has an output attribute). We've overridden root's Template with our own

`template="mahotutorial/helloworld/simple_page.phtml"`

Templates are referenced from the root folder of the current theme. In this case, that's

`app/design/frontend/base/default`

so we need to drill down to our custom page. Most Maho Templates are stored in

`app/design/frontend/base/default/template`

Combining this gives us the full path

`app/design/frontend/base/default/template/mahotutorial/helloworld/simple_page.phtml`

## Adding Content Blocks

A simple red page is pretty boring. Let's add some content to this page. Change your `<helloworld_index_index />` Handle in local.xml so it looks like the following

```xml
<helloworld_index_index>
    <block type="page/html" name="root" output="toHtml" template="mahotutorial/helloworld/simple_page.phtml">
        <block type="customer/form_register" name="customer_form_register" template="customer/form/register.phtml"/>
    </block>
</helloworld_index_index>
```

We're adding a new Block nested within our root. This is a Block that's distributed with Maho, and will display a customer registration form. By nesting this Block within our root Block, we've made it available to be pulled into our simple_page.html Template. Next, we'll use the Block's getChildHtml method in our simple_page.phtml file. Edit simple_page.html so it looks like this

```php
<body>
    <?= $this->getChildHtml('customer_form_register') ?>
</body>
```

Clear your Maho cache and reload the page and you should see the customer registration form on your red background. Maho also has a Block named top.links. Let's try including that. Change your simple_page.html file so it reads

```php
<body>
    <h1>Links</h1>
    <?= $this->getChildHtml('top.links') ?>
</body>
```

When you reload the page, you'll notice that your `<h1>Links</h1>` title is rendering, but nothing is rendering for top.links. That's because we didn't add it to local.xml. The getChildHtml method can only include Blocks that are specified as sub-Blocks in the Layout. This allows Maho to only instantiate the Blocks it needs, and also allows you to set difference Templates for Blocks based on context.

Let's add the top.links Block to our local.xml

```xml
<helloworld_index_index>
    <block type="page/html" name="root" output="toHtml" template="mahotutorial/helloworld/simple_page.phtml">
        <block type="page/template_links" name="top.links"/>
        <block type="customer/form_register" name="customer_form_register" template="customer/form/register.phtml"/>
    </block>
</helloworld_index_index>
```

Clear your cache and reload the page. You should now see the top.links module.

## Time for action

There is one more important concept to cover before we wrap up this lesson, and that is the <action /> tag. Using the <action /> tag enables us to call public PHP methods of the block classes. So instead of changing the template of the root block by replacing the block instance with our own, we can use a call to setTemplate instead.

```xml
<layout version="0.1.0">
    <helloworld_index_index>
        <reference name="root">
            <action method="setTemplate">
                <template>mahotutorial/helloworld/simple_page.phtml</template>
            </action>
            <block type="page/template_links" name="top.links"/>
            <block type="customer/form_register" name="customer_form_register" template="customer/form/register.phtml"/>
        </reference>
    </helloworld_index_index>
</layout>
```

This layout XML will first set the template property of the root block, and then will add the two blocks we use as child blocks. Once we clear the cache, the result should look just as before. The benefit of using the <action /> is the same block instance is used that was created earlier, and all other parent/child associations still exist. For that reason this is a more upgrade proof way of implementing our changes.

All arguments to the action's method need to be wrapped in an individual child node of the <action /> tag. The name of that node doesn't matter, only the order of the nodes. We could have written the action node from the previous example as follows with the same effect.

```xml
<action method="setTemplate">
    <some_new_template>mahotutorial/helloworld/simple_page.phtml</some_new_template>
</action>
```

This is just to illustrate that the action's argument node names are arbitrary.

## Wrapup

That covers Layout fundamentals. We have covered the tags `<block />`, `<reference />`, `<update />` and `<action />`, and also layout update handles like `<default />` and `<cms_index_index />`. These make up most of the layout configuration used in Maho. If you found it somewhat daunting, don't worry, you'll rarely need to work with layouts on such a fundamental level. Maho provides a number of pre-built layouts which can be modified and skinned to meet the needs of your store. Understanding how the entire Layout system works can be a great help when you're trouble shooting Layout issues, or adding new functionality to an existing Maho system.