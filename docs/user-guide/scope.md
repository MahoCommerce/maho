---
description: Which value wins between the default, the website and the store view - the scope switcher in the configuration and on products, the Use Default and Use Website checkboxes, global, website and store view attributes, and the Catalog Price Scope.
admin_pages: [system_config/edit, catalog_product/edit, catalog_category/edit, system_store]
---

# Scope: Default, Website and Store View Values

A Maho installation has three levels, called scopes:

| Scope | What it is | Example |
|-------|------------|---------|
| Default | The values of the whole installation | One value for every shop |
| Website | A group of store views. Some values, such as the product status, apply to a whole website | `example.com` and `example.de` as two websites |
| Store view | One language or one version of a store, inside a website | English and Italian store views |

You set up websites and store views in **System > Manage Stores**.

A value can exist at more than one scope. The rule is the same everywhere: **the most specific
value wins**. A store view value wins over a website value, and a website value wins over the
default value. A scope without its own value uses the value of the scope above it.

## Configuration values

### Which value wins

For a setting in **System > Configuration**, the store looks for a value in this order:

1. The value saved for the store view.
2. The value saved for the website of that store view.
3. The value saved in **Default Config**.
4. The default value that comes with Maho.

The first value that exists wins. So when a store view has its own value, a change at website level
or at **Default Config** has no effect on that store view.

### The scope switcher

At the top left of **System > Configuration**, the **Current Configuration Scope** dropdown selects
the scope that you edit: **Default Config**, a website, or a store view.

Each setting shows a label next to it. The label is the most specific scope where you can set it:

| Label | Where you can set the value |
|-------|-----------------------------|
| [GLOBAL] | Only in **Default Config** |
| [WEBSITE] | In **Default Config** and per website |
| [STORE VIEW] | In **Default Config**, per website and per store view |

A setting that you cannot change at the selected scope does not show. For example, a [GLOBAL]
setting is not in the page when you select a website.

### Use Default and Use Website

When you select a website or a store view, each setting has a checkbox on its right:

| Selected scope | Checkbox | When it is selected |
|----------------|----------|---------------------|
| Website | **Use Default** | The website uses the value of **Default Config** |
| Store view | **Use Website** | The store view uses the value of its website. The checkbox has this name when the setting exists at website level |
| Store view | **Use Default** | The store view uses the value of **Default Config**. The checkbox has this name when the setting does not exist at website level |

To give a scope its own value, clear the checkbox, change the value and save. To remove the value
of a scope, select the checkbox again and save: the store deletes the value of that scope, and the
value of the scope above it applies again.

!!! note "Values set by the server"
    The hosting can also set a configuration value with an environment variable. A value set this
    way wins over the value in the admin, and a change in the admin has no effect on it. See
    [Override configurations with ENV variables](../developer/override-configurations.md) for the
    names of the variables.

## Product and category values

Products and categories use the same idea, with one difference: there is no website level in the
switcher. You edit the default values or one store view.

### The store view switcher

The product page has a **Choose Store View** dropdown at the top. The category page has the same
dropdown above the category tree. The dropdown has **All Store Views** and each store view. With
**All Store Views** you edit the default values.

### The scope of an attribute

Each attribute has a scope. Go to **Catalog > Attributes > Manage Attributes**, open the attribute,
and look at **Scope**.

| Scope | Label on the product page | How the value works |
|-------|---------------------------|---------------------|
| Global | [GLOBAL] | One value for every website and store view. In a store view the field is read-only |
| Website | [WEBSITE] | One value per website. When you save it in a store view, the store saves it for every store view of that website |
| Store View | [STORE VIEW] | One value per store view |

For example, the **Name** and the **URL Key** are [STORE VIEW] attributes, so each language can
have its own. The **Status** is a [WEBSITE] attribute, so you enable or disable a product for a
whole website.

### Which value wins

For a product or category in a store view, the store looks for a value in this order:

1. The value saved for that store view. For a [WEBSITE] attribute, this is the value that you saved
   in any store view of the same website.
2. The default value, saved with **All Store Views**.

### Use Default Value

When you select a store view, each [WEBSITE] and [STORE VIEW] field has a **Use Default Value**
checkbox.

- Selected: the store view uses the default value. The field is read-only.
- Cleared: you can enter a value for this store view. For a [WEBSITE] attribute, the value applies
  to every store view of the website.

When you select **Use Default Value** again and save, the store deletes the value of the store
view. For a [WEBSITE] attribute, it deletes the value of every store view of the website.

## Prices

Prices follow the **Catalog Price Scope** setting in **System > Configuration > Catalog >
Catalog > Price**. You can change it only in **Default Config**.

| Catalog Price Scope | What it does |
|---------------------|--------------|
| Global | One price for all websites. Price fields show [GLOBAL] and are read-only in a store view. All websites use the base currency of **Default Config** |
| Website | Each website can have its own prices. Price fields show [WEBSITE]. Each website can have its own base currency |

The Catalog Price Scope applies to the **Price**, **Special Price**, **Cost** and
**Manufacturer's Suggested Retail Price** fields. It also decides if tier prices, group prices,
custom option prices and the option prices of configurable products can have a value per website.

To set a price for one website, with **Catalog Price Scope** set to **Website**:

1. Open the product.
2. In **Choose Store View**, select a store view of that website.
3. Clear **Use Default Value** next to the price and enter the price.
4. Save. The price applies to every store view of that website.

## Only one store view

When the installation has only one store view, Maho hides the store view switcher on products and
categories and the scope labels. It saves all product and category values as default values.

## Troubleshooting

| What you see | Why | What to do |
|--------------|-----|------------|
| You changed a setting in **Default Config**, but one store view does not change | The store view, or its website, has its own value | Select that store view in **Current Configuration Scope**, select **Use Website** or **Use Default**, and save. Do the same for the website |
| A setting is missing when you select a store view | The setting is [GLOBAL] or [WEBSITE] | Select **Default Config** or the website |
| You changed a setting in the admin, but the store does not use the new value | The hosting sets this value with an environment variable | Ask the hosting to change or remove the variable |
| A product field is read-only in a store view | The attribute is [GLOBAL], or **Use Default Value** is selected | Select **All Store Views** for a [GLOBAL] attribute, or clear **Use Default Value** |
| You changed a value in one store view, and another store view also changed | The attribute is [WEBSITE], and both store views are in the same website | Change the **Scope** of the attribute to Store View, if each store view needs its own value |
| You disabled a product in the Italian store view, and the English store view also hides it | **Status** is a [WEBSITE] attribute | Put the store views in different websites, or accept one status per website |
| The price fields cannot be changed in a store view | **Catalog Price Scope** is Global | Set it to Website, if the websites need different prices |
| A product shows the English name in the Italian store view | The Italian store view has no own value and uses the default | Select the Italian store view, clear **Use Default Value** and enter the name |

## Related

- [URL Rewrites](url-rewrites.md) for URL keys per store view.
- [CMS Pages and Blocks in Store Views](cms-store-views.md) for content per store view.
- [Configurable Product Prices](configurable-product-prices.md) for option prices per website.
