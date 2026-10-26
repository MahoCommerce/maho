---
description: Which price a configurable product shows, why the price changes when a customer picks an option, what the option prices on the Associated Products tab do, and what the Dynamic Price Change swatch setting does.
---

# Configurable Product Prices

A configurable product has one price source: the configurable product itself (the parent). The
store does not use the price of the child products (the variants). When the price changes after a
customer picks a size or a color, an option price on the parent product causes the change.

## Which prices the store uses

| Price | Where you set it | Used for the configurable product |
|-------|------------------|-----------------------------------|
| Price, Special Price, Tier Price, Group Price of the parent | The configurable product, **Prices** tab | Yes. This is the base price |
| Option price of a value (Fixed or Percentage) | The configurable product, **Associated Products** tab | Yes. The store adds it when the customer selects that value |
| Catalog price rules | **Promotions > Catalog Price Rules** | Yes, on the base price. On the option prices only when **Enable Discount to Subproducts** is Yes |
| Price, Special Price and tier prices of a child product | The simple product, **Prices** tab | No |

The cart uses the same rule. A configurable product in the cart is one line that the store prices
from the parent. The child product on that line only tells the store which variant to ship and
which stock to use.

## How the store calculates the price

The store calculates the price of a configurable product in this order:

1. It starts with the **Price** of the configurable product.
2. It takes the lowest of these values: the Price, the **Special Price** (inside its dates), the
   tier price for the quantity, and the price for the customer group.
3. It applies the catalog price rules that match the product and the customer group.
4. For each option that the customer selected, it adds the option price of the selected value. A
   **Fixed** option price is an amount. A **Percentage** option price is a percent of the price
   from step 3.
5. It adds the prices of the custom options, if the product has custom options.
6. If the result is below zero, the price is zero.

For the tier price in step 2, the store counts all cart lines of the same configurable product
together. For example, 3 red shirts and 2 blue shirts of the same configurable product count as a
quantity of 5.

## Setting option prices

1. Go to **Catalog > Products** and open the configurable product.
2. Open the **Associated Products** tab.
3. In **Super product attributes configuration**, each value of each configurable attribute has a
   **Price** field and a **Fixed** or **Percentage** selector.
4. Enter the amount that this value adds to the price. Enter 0 when the value must not change the
   price. A negative amount lowers the price.
5. Save the product.

Enter option prices in the base currency of the store. The admin shows this note above the list.

When **Catalog Price Scope** is **Website** (see [Scope](scope.md)), you can give an option price a
different value per website. Select a store view of that website with the store view switcher. A
**Use Default Value** checkbox then shows next to each option price.

!!! note "There is no setting to use the child prices"
    Maho has no option that prices a configurable product from its children. To charge more for
    one size, give that size an option price on the parent. To show a different price per variant,
    use a separate simple product, or a different product type.

## What the customer sees

| Place | Price shown |
|-------|-------------|
| Product page, before the customer picks an option | The price of the configurable product after steps 1 to 3 |
| Product page, after the customer picks an option | That price plus the option prices of the selected values |
| Category pages and search results | The price of the configurable product |
| Category pages, with Dynamic Price Change on | The price changes when the customer clicks a swatch of the listing attribute (see below) |
| Cart and order | The price from the full calculation above |
| Structured data, for each variant | The price of the configurable product plus the option prices of the values of the variant (see [Structured Data](structured-data.md)) |
| Product feeds, for each variant (v26.9.3+) | The same price as the structured data (see [Feed Manager](feed-manager.md#variant-prices)) |

A configurable product appears in category pages and search results only when at least one of its
children is enabled, is in the same website and has no required custom options. Without such a
child, the product prices index has no row for the configurable product, and listings do not show
it.

## Dynamic Price Change for swatches

The setting is in **System > Configuration > Catalog > Configurable Swatches**, with the name
**Dynamic Price Change for Swatches in Product Listing**. It works only when Configurable Swatches
is enabled.

When the setting is **Yes**, a customer who clicks a swatch in a category page or in search
results sees the price change in that listing. The new price is the price of the configurable
product plus the option price of the clicked value. The store uses only the attribute in
**Product Attribute to Use for Swatches in Product Listing**.

This setting does not read the prices of the child products either. If no value of the listing
attribute has an option price, the price does not change when a customer clicks a swatch. See
[Configurable Swatches](configurable-swatches.md) for the other swatch settings.

## Troubleshooting

| What you see | Why | What to do |
|--------------|-----|------------|
| The price changes when the customer picks a size, but you set a price only on the parent | The selected value has an option price on the **Associated Products** tab | Open the configurable product, **Associated Products** tab, and set the Price of that value to 0 |
| You changed the price of a child product, but the store still shows the old price | The store does not use child prices for a configurable product | Change the price of the configurable product, or the option price of the value |
| The Special Price of a child product is ignored | The same rule: only the parent prices count | Set the Special Price on the configurable product |
| A Percentage option price gives an unexpected amount | The percent applies to the price after the special price, the tier price and the catalog price rules | Use a Fixed option price when you want an exact amount |
| The price in the cart is lower than on the product page | Tier prices count all lines of the same configurable product in the cart together | This is expected behavior |
| A catalog price rule discounts the parent but not the option prices | **Enable Discount to Subproducts** is No in the rule | Open the rule in **Promotions > Catalog Price Rules** and set the option to Yes |
| The listing price does not change when a customer clicks a swatch | **Dynamic Price Change for Swatches in Product Listing** is No, or the clicked value has no option price | Turn the setting on, and give the values an option price |
| The configurable product is missing from category pages | No child is enabled and in the website, or the product prices index is not up to date | Enable a child, assign it to the website, and check **System > Index Management** |

## Related

- [Product Types](product-types.md) for the configurable product type.
- [Configurable Swatches](configurable-swatches.md) for the swatch settings.
- [Stock Status](stock-status.md) for when a configurable product is in stock.
- [Scope](scope.md) for the Catalog Price Scope setting.
