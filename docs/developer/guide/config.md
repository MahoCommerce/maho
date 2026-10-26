---
description: Learn how Maho's module system and config.xml work by building a module, from the directory structure under app/code/local to wiring configuration into the platform.
---

# The Maho Config

## Setting up a Module Directory Structure

We're going to be creating a Maho module. A module is a group of php and xml files meant to extend the system with new functionality, or override core system behavior. This may meaning adding additional data models to track sales information, changing the behavior of existing classes, or adding entirely new features.

It's worth noting that most of the base Maho system is built using the same module system you'll be using. If you look in

`app/code/core/Mage`

each folder is a separate module built by the Maho team. Together, these modules form the community shopping cart system you're using. Your modules should be placed in the following folder

app/code/local/Packagename

"Packagename" should be a unique string to Namespace/Package your code. It's an unofficial convention that this should be the name of your company. The idea is to pick a string that no one else in the world could possibly be using.

`app/code/local/Companyname`

We'll use "Mahotutorial".

So, to add a module to your Maho system, create the following directory structure

```
app/code/local/Mahotutorial/Configviewer/Block
app/code/local/Mahotutorial/Configviewer/controllers
app/code/local/Mahotutorial/Configviewer/etc
app/code/local/Mahotutorial/Configviewer/Helper
app/code/local/Mahotutorial/Configviewer/Model
app/code/local/Mahotutorial/Configviewer/sql
```

You won't need all these folder for every module, but setting them all up now is a smart idea.

Next, there's two files you'll need to create. The first, config.xml, goes in the etc folder you just created.

`app/code/local/Mahotutorial/Configviewer/etc/config.xml`

The second file should be created at the following location

`app/etc/modules/Mahotutorial_Configviewer.xml`

The naming convention for this files is Packagename_Modulename.xml.

The config.xml file should contain the following XML. Don't worry too much about what all this does for now, we'll get there eventually

```xml
<config>
    <modules>
        <Mahotutorial_Configviewer>
            <version>0.1.0</version>
        </Mahotutorial_Configviewer>
    </modules>
</config>
```

Finally, Mahotutorial_Configviewer.xml should contain the following xml.

```xml
<config>
    <modules>
        <Mahotutorial_Configviewer>
            <active>true</active>
            <codePool>local</codePool>
        </Mahotutorial_Configviewer>
    </modules>
</config>
```

That's it. You now have a bare bones module that won't do anything, but that Maho will be aware of. To make sure you've done everything right, do the following:

1.  Clear your Maho cache
2.  In the Maho Admin, go to **System->Configuration->Advanced**
3.  In the "Disable modules output" panel verify that Mahotutorial_Configviewer shows up

Congratulations, you've built your first Maho module!

## Creating a Module Config

Of course, this module doesn't do anything yet. When we're done, our module will

1.  Check for the existence of a "showConfig" query string variable
2.  If showConfig is present, display our Maho config and halt normal execution
3.  Check for the existence of an additional query string variable, showConfigFormat that will let us specify text or xml output.

First, we're going to add the following <global> section to our config.xml file.

```xml
<config>
    <modules>...</modules>
    <global>
        <events>
            <controller_front_init_routers>
                <observers>
                    <Mahotutorial_configviewer_model_observer>
                        <type>singleton</type>
                        <class>Mahotutorial_Configviewer_Model_Observer</class>
                        <method>checkForConfigRequest</method>
                    </Mahotutorial_configviewer_model_observer>
                </observers>
            </controller_front_init_routers>
        </events>
    </global>
</config>
```

!!! tip "Modern alternative"
    XML observer registration still works and is what this tutorial uses, but since v26.5 the recommended way is the `#[Maho\Config\Observer]` PHP attribute placed directly on the observer method. See [Event observers](../observers.md).

Then, create a file at

`Mahotutorial/Configviewer/Model/Observer.php`

and place the following code inside

```php
class Mahotutorial_Configviewer_Model_Observer
{
    const FLAG_SHOW_CONFIG = 'showConfig';
    const FLAG_SHOW_CONFIG_FORMAT = 'showConfigFormat';

    private $request;

    public function checkForConfigRequest($observer)
    {
        $this->request = $observer->getEvent()->getData('front')->getRequest();
        if($this->request->{self::FLAG_SHOW_CONFIG} === 'true'){
            $this->setHeader();
            $this->outputConfig();
        }
    }

    private function setHeader()
    {
        $format = isset($this->request->{self::FLAG_SHOW_CONFIG_FORMAT}) ?
        $this->request->{self::FLAG_SHOW_CONFIG_FORMAT} : 'xml';
        switch($format){
            case 'text':
                header("Content-Type: text/plain");
                break;
            default:
                header("Content-Type: text/xml");
        }
    }

    private function outputConfig()
    {
        die(Mage::app()->getConfig()->getNode()->asXML());
    }
}
```

That's it. Clear your Maho cache again and then load any Maho URL with a showConfig=true query string

http://maho.example.com/?showConfig=true

## What am I looking at?

You should be looking at a giant XML file. This describes the state of your Maho system. It lists all modules, models, classes, event listeners or almost anything else you could think of.

For example, consider the config.xml file you created above. If you search the XML file in your browser for the text Configviewer_Model_Observer you'll find your class listed. Every module's config.xml file is parsed by Maho and included in the global config.

## Why Do I Care?

Right now this may seem esoteric, but this config is key to understanding Maho. Every module you'll be creating will add to this config, and anytime you need to access a piece of core system functionality, Maho will be referring back to the config to look something up.

A quick example: As an MVC developer, you've likely worked with some kind of helper class, instantiated something like

```php
$helper_sales = new HelperSales();
```

One of the things Maho has done is abstract away PHP's class declaration. In Maho, the above code looks something like

```php
$helper_sales = Mage::helper('sales');
```

In plain english, the static helper method will:

1.  Look in the `<helpers />` section of the Config.
2.  Within `<helpers />`, look for a `<sales />` section
3.  Within the `<sales />` section look for a `<class />` section
4.  Append the part after the slash to the value found in #3 (defaulting to data in this case)
5.  Instantiate the class found in #4 (Mage_Sales_Helper_Data)

While this seems like a lot of work (and it is), the key advantage is by always looking to the config file for class names, we can override core Maho functionality **without** changing or adding to the core code. This level of meta programming, not usually found in PHP, allows you to cleanly extend only the parts of the system you need to.