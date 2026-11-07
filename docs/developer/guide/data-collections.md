---
description: Work with Maho data collections, array-like objects that hold model instances with iteration, counting and filtering, one provided free with every Maho model.
---

# Data Collections

## Introduction

Originally, as a PHP programmer, if you wanted to collect together a group of related variables you had one choice, the venerable [Array](http://us.php.net/manual/en/language.types.array.php). While it shares a name with C's array of memory addresses, a PHP array is a general purpose dictionary like object combined with the behaviors of a numerically indexed mutable array.

In other languages the choice isn't so simple. You have [multiple data structures](https://en.wikipedia.org/wiki/List_of_data_structures#Linear_data_structures) to chose from, each offering particular advantages in storage, speed and semantics. The PHP philosophy was to remove this choice from the client programmer and give them one useful data structure that was "good enough".

All of this is galling to a certain type of software developer, so PHP also offers built-in classes and interfaces (`ArrayObject`, `Iterator`, `Countable`, and friends) that let you build your own data structures.

```php
$array = new ArrayObject();
class MyCollection extends ArrayObject{...}
$collection = new MyCollection();
$collection[] = 'bar';
```

While this is still galling to a certain type of software developer, as you don't have access to low level implementation details, you do have the ability to create array-like Objects with methods that encapsulate specific functionality. You can also setup rules to offer a level of type safety by only allowing certain kinds of Objects into your Collection.

It should come as no surprise that Maho offers you a number of these Collections. In fact, every Model object that follows the Maho interfaces gets a Collection type for free. Understanding how these Collections work is a key part to being an effective Maho programmer. We're going to take a look at Maho Collections, starting from the bottom and working our way up. Set up a [controller action](controller-dispatch.md) where you can run arbitrary code, and let's get started.

## A Collection of Things

First, we're going to create a few new Objects.

```php
$thing_1 = new \Maho\DataObject();
$thing_1->setName('Richard');
$thing_1->setAge(24);

$thing_2 = new \Maho\DataObject();
$thing_2->setName('Jane');
$thing_2->setAge(12);

$thing_3 = new \Maho\DataObject();
$thing_3->setName('Spot');
$thing_3->setLastName('The Dog');
$thing_3->setAge(7);
```

The \Maho\DataObject class defines the object all Maho Models inherit from. This is a common pattern in object oriented systems, and ensures you'll always have a way to easily add methods/functionally to **every** object in your system without having to edit every class file. (The legacy name Varien_Object still works as a backward-compatible class alias.)

Any Object that extends from \Maho\DataObject has magic getter and setters that can be used to set data properties. Give this a try

```php
var_dump($thing_1->getName());
```

If you don't know what the property name you're after is, you can pull out all the data as an array

```php
var_dump($thing_3->getData());
```

The above will give you an array something like

```php
array
'name' => string 'Spot' (length=4)
'last_name' => string 'The Dog' (length=7)
'age' => int 7
```

Notice the property named "last_name"? If there's an underscore separated property, you camel case it if you want to use the getter and setter magic.

```php
$thing_1->setLastName('Smith');
```

The ability to do these kinds of things is part of the power of object-oriented PHP, the development style a certain class of people mean when they say "Object Oriented Programming".

So, now that we have some Objects, let's add them to a Collection. Remember, a Collection is like an Array, but is defined by a PHP programmer.

```php
$collection_of_things = new \Maho\Data\Collection();
$collection_of_things
    ->addItem($thing_1)
    ->addItem($thing_2)
    ->addItem($thing_3);
```

The \Maho\Data\Collection is the Collection that most Maho data Collections inherit from. Any method you can call on a \Maho\Data\Collection you can call on Collections higher up the chain (We'll see more of this later). (The legacy name Varien_Data_Collection still works as a backward-compatible class alias.)

What can we do with a Collection? For one, with can use foreach to iterate over it

```php
foreach($collection_of_things as $thing) {
    var_dump($thing->getData());
}
```

There are also shortcuts for pulling out the first and last items

```php
var_dump($collection_of_things->getFirstItem()->getData());
var_dump($collection_of_things->getLastItem()->getData());
```

Want your Collection data as XML? There's a method for that

```php
var_dump( $collection_of_things->toXml() );
```

Only want a particular field?

```php
var_dump($collection_of_things->getColumnValues('name'));
```

The team at Maho have even given us some rudimentary filtering capabilities.

```php
var_dump($collection_of_things->getItemsByColumnValue('name','Spot'));
```

Neat stuff.

## Model Collections

So, this is an interesting exercise, but why do we care?

We care because all of Maho's built in data Collections inherit from this object. That means if you have, say, a product Collection you can do the same sort of things. Let's take a look

```php
public function testAction()
{
    $collection_of_products = Mage::getModel('catalog/product')->getCollection();
    var_dump($collection_of_products->getFirstItem()->getData());
}
```

Most Maho Model objects have a method named getCollection which will return a collection that, by default, is initialized to return every Object of that type in the system.

**A Quick Note:** Maho's Data Collections contain a lot of complicated logic that handles when to use an index or cache, as well as the logic for the EAV entity system. Successive method calls to the same Collection over its life can often result in unexpected behavior. Because of that, all the of the following examples are wrapped in a single method action. I'd recommend doing the same while you're experimenting. Also, [XDebug's](http://xdebug.org/) var_dump is a godsend when working with Maho Objects and Collections, as it will (usually) intelligently short circuit showing hugely recursive Objects, but still display a useful representation of the Object structure to you.

The products Collection, as well as many other Maho Collections, also have the \Maho\Data\Collection\Db class in their ancestor chain. This gives us a lot of useful methods. For example, if you want to see the select statement your Collection is using

```php
public function testAction()
{
    $collection_of_products = Mage::getModel('catalog/product')->getCollection();
    var_dump($collection_of_products->getSelect()); //might cause a segmentation fault
}
```

The output of the above will be

```php
object(Maho\Db\Select)[94]
  protected '_bind' =>
    array
      empty
  protected '_adapter' =>
...
```

Whoops! Since Maho's database access goes through a custom \Maho\Db adapter layer (built on Doctrine DBAL), your Select is also an Object. Let's see that as a more useful string.

```php
public function testAction()
{
    $collection_of_products = Mage::getModel('catalog/product')->getCollection();
    //var_dump($collection_of_products->getSelect()); //might cause a segmentation fault
    var_dump(
        (string) $collection_of_products->getSelect()
    );
}
```

Sometimes this is going to result in a simple select

```sql
'SELECT `e`.* FROM `catalog_product_entity` AS `e`'
```

Other times, something a bit more complex

```sql
string 'SELECT `e`.*, `price_index`.`price`, `price_index`.`final_price`, IF(`price_index`.`tier_price`, 
LEAST(`price_index`.`min_price`, `price_index`.`tier_price`), `price_index`.`min_price`) AS `minimal_price`, 
`price_index`.`min_price`, `price_index`.`max_price`, `price_index`.`tier_price` FROM `catalog_product_entity` 
AS `e` INNER JOIN `catalog_product_index_price` AS `price_index` ON price_index.entity_id = e.entity_id AND 
price_index.website_id = '1' AND price_index.customer_group_id = 0'
```

The discrepancy depends on which attributes you're selecting, as well as the aforementioned indexing and cache. If you've been following along with the other articles in this series, you know that many Maho models (including the Product Model) use an [EAV](https://en.wikipedia.org/wiki/EAV) system. By default, a EAV Collection will not include all of an Object's attributes. You can add them all by using the addAttributeToSelect method

```php
$collection_of_products = Mage::getModel('catalog/product')
    ->getCollection()
    ->addAttributeToSelect('*');  //the asterisk is like a SQL SELECT * FROM ...
```

Or, you can add just one

```php
//or just one
$collection_of_products = Mage::getModel('catalog/product')
    ->getCollection()
    ->addAttributeToSelect('meta_title');
```

or chain together several

```php
//or just one
$collection_of_products = Mage::getModel('catalog/product')
    ->getCollection()
    ->addAttributeToSelect('meta_title')
    ->addAttributeToSelect('price');
```

## Lazy Loading

One thing that will trip up PHP developers new to Maho's ORM system is **when** Maho makes its database calls. When you're writing literal SQL, or even when you're using a basic ORM system, SQL calls are often made immediately when instantiating an Object.

```php
$model = new Customer();
//SQL Calls being made to Populate the Object
echo 'Done'; //execution continues
```

Maho doesn't work that way. Instead, the concept of [Lazy Loading](https://en.wikipedia.org/wiki/Lazy_loading) is used. In simplified terms, Lazy loading means that no SQL calls are made until the client-programmer needs to access the data. That means when you do something something like this

```php
$collection_of_products = Mage::getModel('catalog/product')
    ->getCollection();
```

Maho actually hasn't gone out to the database yet. You can safely add attributes later

```php
$collection_of_products = Mage::getModel('catalog/product')
    ->getCollection();
$collection_of_products->addAttributeToSelect('meta_title');
```

and not have to worry that Maho is making a database query each time a new attribute is added. The database query will not be made until you attempt to access an item in the Collection.

In general, try not to worry too much about the implementation details in your day to day work. It's good to know that there's s SQL backend and Maho is doing SQLy things, but when you're coding up a feature try to forget about it, and just treat the objects as block boxes that do what you need.

## Filtering Database Collections

The most important method on a database Collection is addFieldToFilter. This adds your WHERE clauses to the SQL query being used behind the scenes. Consider this bit of code, run against the sample data database (substitute your own SKU is you're using a different set of product data)

```php
public function testAction()
{
    $collection_of_products = Mage::getModel('catalog/product')
        ->getCollection();
    $collection_of_products->addFieldToFilter('sku','n2610');

    // collections implement Countable, so you can pass them straight to count()
    echo "Our collection now has " . count($collection_of_products) . ' item(s)';
    var_dump($collection_of_products->getFirstItem()->getData());
}
```

The first parameter of addFieldToFilter is the attribute you wish to filter by. The second is the value you're looking for. Here's we're adding a sku filter for the value n2610.

The second parameter can also be used to specify the **type** of filtering you want to do. This is where things get a little complicated, and worth going into with a little more depth.

So by default, the following

```php
$collection_of_products->addFieldToFilter('sku','n2610');
```

is (essentially) equivalent to

`WHERE sku = "n2610"`

Take a look for yourself. Running the following

```php
public function testAction()
{
    var_dump(
    (string)
    Mage::getModel('catalog/product')
    ->getCollection()
    ->addFieldToFilter('sku','n2610')
    ->getSelect());
}
```

will yield

```sql
SELECT `e`.* FROM `catalog_product_entity` AS `e` WHERE (e.sku = 'n2610')'
```

Keep in mind, this can get complicated fast if you're using an EAV attribute. Add an attribute

```php
var_dump(
    (string)
    Mage::getModel('catalog/product')
        ->getCollection()
        ->addAttributeToSelect('*')
        ->addFieldToFilter('meta_title','my title')
        ->getSelect()
);
```

and the query gets gnarly.

```sql
SELECT `e`.*, IF(_table_meta_title.value_id>0, _table_meta_title.value, _table_meta_title_default.value) AS `meta_title`
FROM `catalog_product_entity` AS `e`
INNER JOIN `catalog_product_entity_varchar` AS `_table_meta_title_default`
    ON (_table_meta_title_default.entity_id = e.entity_id) AND (_table_meta_title_default.attribute_id='103')
    AND _table_meta_title_default.store_id=0
LEFT JOIN `catalog_product_entity_varchar` AS `_table_meta_title`
    ON (_table_meta_title.entity_id = e.entity_id) AND (_table_meta_title.attribute_id='103')
    AND (_table_meta_title.store_id='1')
WHERE (IF(_table_meta_title.value_id>0, _table_meta_title.value, _table_meta_title_default.value) = 'my title')
```

Not to belabor the point, but try not to think too much about the SQL if you're on deadline.

## Other Comparison Operators

I'm sure you're wondering "what if I want something other than an equals by query"? Not equal, greater than, less than, etc. The addFieldToFilter method's second parameter has you covered there as well. It supports an alternate syntax where, instead of passing in a string, you pass in a single element Array.

The key of this array is the **type** of comparison you want to make. The value associated with that key is the value you want to filter by. Let's redo the above filter, but with this explicit syntax

```php
public function testAction()
{
    var_dump(
        (string)
        Mage::getModel('catalog/product')
            ->getCollection()
            ->addFieldToFilter('sku', ['eq'=>'n2610'])
            ->getSelect()
    );
}
```

Calling out our filter

```php
addFieldToFilter('sku',['eq'=>'n2610'])
```

As you can see, the second parameter is a PHP Array. Its key is eq, which stands for _equals_. The value for this key is n2610, which is the value we're filtering on.

Maho has a number of these english language like filters that will bring a [tear of remembrance](http://www.shlomifish.org/lecture/Perl/Newbies/lecture1/conditionals/string.html) (and perhaps pain) to any old perl developers in the audience.

Listed below are all the filters, along with an example of their SQL equivalents.

```
["eq"=>'n2610']
WHERE (e.sku = 'n2610')

["neq"=>'n2610']
WHERE (e.sku != 'n2610')

["like"=>'n2610']
WHERE (e.sku like 'n2610')

["nlike"=>'n2610']
WHERE (e.sku not like 'n2610')

["is"=>'n2610']
WHERE (e.sku is 'n2610')

["in"=>['n2610']]
WHERE (e.sku in ('n2610'))

["nin"=>['n2610']]
WHERE (e.sku not in ('n2610'))

["notnull"=>true]
WHERE (e.sku is NOT NULL)

["null"=>true]
WHERE (e.sku is NULL)

["gt"=>'n2610']
WHERE (e.sku > 'n2610')

["lt"=>'n2610']
WHERE (e.sku < 'n2610')

["gteq"=>'n2610']
WHERE (e.sku >= 'n2610')

["moreq"=>'n2610'] // a legacy alias for greater-than-or-equal; no longer wired up, use "gteq"
WHERE (e.sku >= 'n2610')

["lteq"=>'n2610']
WHERE (e.sku <= 'n2610')

["finset"=>['n2610']]
WHERE (find_in_set('n2610',e.sku))

['from'=>'10','to'=>'20']
WHERE e.sku >= '10' and e.sku <= '20'
```

Most of these are self explanatory, but a few deserve a special callout

### in, nin, find_in_set

The in, nin and finset conditionals allow you to pass in an Array of values. That is, the value portion of your filter array is itself allowed to be an array.

```
["in"=>['n2610','ABC123']]
WHERE (e.sku in ('n2610','ABC123'))
```

### notnull, null

The keyword NULL is special in most flavors of SQL. It typically won't play nice with the standard equality (=) operator. Specifying notnull or null as your filter type will get you the correct syntax for a NULL comparison while ignoring whatever value you pass in

```
["notnull"=>true]
WHERE (e.sku is NOT NULL)
```

### from - to filter

This is another special format that breaks the standard rule. Instead of a single element array, you specify a two element array. One element has the key from, the other element has the key to. As the keys indicated, this filter allows you to construct a from/to range without having to worry about greater than and less than symbols

```php
public function testAction
{
    var_dump(
        (string)
        Mage::getModel('catalog/product')
            ->getCollection()
            ->addFieldToFilter('price',['from'=>'10','to'=>'20'])
            ->getSelect()
    );
}
```

The above yields

```sql
WHERE (_table_price.value >= '10' and _table_price.value <= '20')'
```

## AND or OR, or is that OR and AND?

Finally, we come to the boolean operators. It's the rare moment where we're only filtering by one attribute. Fortunately, Maho's Collections have us covered. You can chain together multiple calls to addFieldToFilter to get a number of "AND" queries.

```php
function testAction()
{
    echo
        (string)
        Mage::getModel('catalog/product')
            ->getCollection()
            ->addFieldToFilter('sku',['like'=>'a%'])
            ->addFieldToFilter('sku',['like'=>'b%'])
            ->getSelect();
}
```

By chaining together multiple calls as above, we'll produce a where clause that looks something like the following

```sql
WHERE (e.sku like 'a%') AND (e.sku like 'b%')
```

To those of you that just raised your hand, yes, the above example would always return 0 records. No sku can begin with BOTH an a and a b. What we probably want here is an OR query. This brings us to another confusing aspect of addFieldToFilter's second parameter.

If you want to build an OR query, you need to pass an Array of filter Arrays in as the second parameter. I find it's best to assign your individual filter Arrays to variables

```php
public function testAction()
{
    $filter_a = ['like'=>'a%'];
    $filter_b = ['like'=>'b%'];
}
```

and then assign an array of all my filter variables

```php
public function testAction()
{
    $filter_a = ['like'=>'a%'];
    $filter_b = ['like'=>'b%'];
    echo
        (string)
        Mage::getModel('catalog/product')
            ->getCollection()
            ->addFieldToFilter('sku', [$filter_a, $filter_b])
            ->getSelect();
}
```

In the interest of being explicit, here's the aforementioned array of filter arrays.

`[$filter_a, $filter_b]`

This will gives us a WHERE clause that looks something like the following

`WHERE (((e.sku like 'a%') or (e.sku like 'b%')))`

## Wrap Up

You're now a Maho developer walking around with some serious firepower. Without having to write a single line of SQL you now know how to query Maho for any Model your store or application might need.