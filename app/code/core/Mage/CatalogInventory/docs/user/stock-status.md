---
description: Why a product with a quantity can still show as out of stock - quantity and stock availability, Manage Stock, backorders, the out-of-stock threshold, configurable products and the Stock Status index.
---

# Stock Status

A product has two stock values: the quantity (**Qty**) and the stock availability (**Stock
Availability**, In Stock or Out of Stock). They are separate fields. The store sells a product
only when its stock availability is In Stock, not when its quantity is above zero. Most "I set the
quantity and it is still out of stock" problems come from this difference.

Category pages and search results do not read these fields directly. They read the **Stock
Status** index, which the store builds from them. So an index that is not up to date can also show
the wrong status. The option dropdowns of a configurable product read the fields of each child
directly.

## Where you set the stock of a product

Go to **Catalog > Products**, open the product, and open the **Inventory** tab.

| Field | What it does |
|-------|--------------|
| Manage Stock | When No, the store does not track this product. The product is always in stock |
| Qty | The quantity in stock. Simple, virtual, downloadable and gift card products have this field |
| Qty for Item's Status to Become Out of Stock | The threshold. When the quantity is at or below this number, the product becomes out of stock |
| Backorders | Lets customers buy the product when the quantity is below the threshold |
| Stock Availability | In Stock or Out of Stock |

Each field with a **Use Config Settings** checkbox takes the store-wide value from
**System > Configuration > Catalog > Inventory** while the checkbox is selected. Stock values are
global: they are the same in every website and store view.

Configurable, grouped and bundle products have no **Qty** field. Their stock comes from their
children (see [Configurable products](#configurable-products)).

## How the store decides the stock availability

When you save a product that has a **Qty** field, or when an order changes its quantity, the store
sets the stock availability with these rules, in this order:

1. If **Manage Stock** is No, the store treats the product as in stock. The other rules have no
   effect.
2. If **Backorders** is **No Backorders** and the quantity is at or below
   **Qty for Item's Status to Become Out of Stock**, the store sets the product to Out of Stock.
3. If the product is Out of Stock and **Sync Stock Availability with Qty** is Yes, the store sets
   it back to In Stock.
4. In all other cases, the store keeps the **Stock Availability** that you selected.

So with **Sync Stock Availability with Qty** set to No, a product stays Out of Stock after you
raise its quantity, until you also set **Stock Availability** to In Stock.

With **Backorders** set to **Allow Qty Below 0** or **Allow Qty Below 0 and Notify Customer**,
rule 2 never applies. The quantity can go below zero and the product stays in stock.

## Store-wide stock settings

Go to **System > Configuration > Catalog > Inventory**.

| Setting | Group | What it does |
|---------|-------|--------------|
| Decrease Stock When Order is Placed | Stock Options | Lowers the quantity when a customer places an order |
| Set Items' Status to be In Stock When Order is Cancelled | Stock Options | When a canceled order returns the quantity, the product becomes In Stock again if the quantity is above the threshold |
| Sync Stock Availability with Qty | Stock Options | Rule 3 above. Default: Yes |
| Display Out of Stock Products | Stock Options | When No, out-of-stock products do not show in category pages and search results. They still open from a direct URL |
| Only X left Threshold | Stock Options | The product page shows "Only X left" when the quantity is above 0 and at or below this number. 0 turns the message off |
| Display products availability in stock in the frontend | Stock Options | Shows the availability of the product on the product page |
| Manage Stock | Product Stock Options | The default for products that use the config setting. Default: Yes |
| Backorders | Product Stock Options | The default backorder mode. Default: No Backorders |
| Qty for Item's Status to Become Out of Stock | Product Stock Options | The default threshold. Default: 0. The store does not accept a negative value |

When you save a product in the admin, the store uses the **Default Config** value of
**Sync Stock Availability with Qty**.

## Configurable products

A configurable product has no quantity of its own. In the Stock Status index, a configurable
product is in stock in a website only when both of these are true:

1. The **Stock Availability** of the configurable product itself is In Stock, or it does not
   manage stock.
2. At least one child product is in stock, is enabled, is assigned to that website and has no
   required custom options.

So a configurable product can be out of stock while its children have quantity, if its own
**Stock Availability** is Out of Stock. And it is out of stock when all its children are out of
stock, disabled, or missing from the website.

On the product page, the option dropdowns show only the values that have at least one in-stock
child. A value whose children are all out of stock or disabled is not in the list.

## The Stock Status index

The storefront shows the stock status from the **Stock Status** index, one value per product and
per website. Go to **System > Index Management** to see it.

- In **Update on Save** mode, the store updates the index when you save a product or when an order
  changes the stock. When a child changes, the store also updates its configurable parent.
- In **Manual Update** mode, the index changes only when someone runs a reindex. Until then the
  storefront shows the old status.
- A change of **Manage Stock** or of **Display Out of Stock Products** in the configuration marks
  the index as needing a reindex. A change of **Display Out of Stock Products** also marks the
  **Product Prices** and **Product Attributes** indexes, because these indexes leave out the
  out-of-stock products.

A product that is disabled, or that is not assigned to the website, is out of stock in the index
of that website.

## Troubleshooting

| What you see | Why | What to do |
|--------------|-----|------------|
| You set the quantity to 10 and the product still says out of stock | **Stock Availability** is Out of Stock and **Sync Stock Availability with Qty** is No | Set **Stock Availability** to In Stock, or set the sync setting to Yes and save the product again |
| The product goes out of stock with a quantity above 0 | The quantity is at or below **Qty for Item's Status to Become Out of Stock** | Lower the threshold on the **Inventory** tab or in the configuration |
| You set the product to Out of Stock, but after the save it is In Stock again | **Sync Stock Availability with Qty** is Yes and the quantity is above the threshold | Set the quantity to 0, or set the sync setting to No |
| A product with quantity 0 can still be bought | **Backorders** allows a quantity below 0, or **Manage Stock** is No | Set **Backorders** to No Backorders and **Manage Stock** to Yes |
| A configurable product is out of stock, but its children have stock | The **Stock Availability** of the configurable product is Out of Stock, or no in-stock child is enabled and in the website | Set the parent to In Stock, and check the status and the **Websites** tab of the children |
| An option is missing from the dropdown of a configurable product | The child for that option is out of stock or disabled | Give the child stock, or enable it |
| The admin shows In Stock, but the storefront shows Out of Stock | The **Stock Status** index is in **Manual Update** mode, or needs a reindex | Reindex in **System > Index Management** |
| The product is in stock in one website and out of stock in another | The product is disabled in a store view of that website, or is not assigned to that website | Check the status per store view and the **Websites** tab |
| Out-of-stock products still show in category pages | **Display Out of Stock Products** is Yes, or the indexes are not up to date after the change | Set it to No and reindex |

## Related

- [Product Types](product-types.md) for which types have a quantity.
- [Configurable Product Prices](configurable-product-prices.md) for the prices of configurable products.
