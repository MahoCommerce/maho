---
description: Why a category ignores the positions of its products - which sort order the category page uses, the sort order that a customer picks and the store remembers, anchor categories, equal positions and the Category Products index.
admin_pages: [catalog_category]
---

# The Order of Products in a Category

You give each product of a category a position on the **Category Products** tab of the category.
The storefront uses these positions only when the category page sorts by **Position**. Another
sort order, a sort order that the customer picked, or an index that is not up to date can each
hide your positions.

## Where you set the positions

Go to **Catalog > Categories**, select the category, and open the **Category Products**
tab. The **Position** column holds the position of each product in this category. A lower number
comes first when the direction is ascending, which is the default. Save the category.

The position belongs to the product in this category. The same product can have another position
in another category.

## Which sort order the category page uses

The category page takes the first sort order that applies, in this order:

1. The sort order that the customer picks in the **Sort By** list of the category page.
2. The sort order that the customer picked before. The store remembers it in the session of the
   customer, also on the next category pages, until the customer picks the default sort order of a
   category again.
3. **Default Product Listing Sort By** on the **Display Settings** tab of the category, when it is
   one of the sort orders that the category offers.
4. **Product Listing Sort by** in **System > Configuration > Catalog > Catalog**, group
   **Frontend**. Its default is Position.
5. The first sort order that the category offers.

So your positions show only when the result is **Position**.

The direction counts too. The arrow next to the **Sort By** list changes it, and the store
remembers the direction that the customer picked in the same way as the sort order. With the
descending direction, the highest position comes first.

## The sort settings of a category

On the **Display Settings** tab of the category:

| Field | What it does |
|-------|--------------|
| Available Product Listing Sort By | The sort orders that the **Sort By** list of the category offers. With **Use All Available Attributes**, it offers every attribute that is used for sorting |
| Default Product Listing Sort By | The sort order of the category page before the customer picks one. With **Use Config Settings**, it is the value of **Product Listing Sort by** in the configuration |

Both fields have the scope **[STORE VIEW]**: a store view can have its own value (see
[Scope](scope.md)). If **Default Product Listing Sort By** is not in the available sort orders of
the category, the store uses the configuration value instead.

## Anchor categories

When **Is Anchor** is Yes, the category page also shows the products of its subcategories. The
products of the category itself come first, by their positions. The products of the subcategories
come after them. For each of these products, the store calculates a sort number from the position
and the level of its subcategory, and from the position of the product in that subcategory. With
positions below 10000, the products of one subcategory stay together, and among the subcategories
of one level, a lower position comes first. A subcategory on a deeper level can come before a
subcategory on a higher level. To move a product of a subcategory to the top, assign the product to
the anchor category too, and give it a position there.

## Equal positions

Products with the same position, for example 0 for every product, are sorted by their product ID.
Give each product its own position when the order matters.

## The Category Products index

Category pages read the positions from the **Category Products** index, not from the category
directly. In **Update on Save** mode, the store updates the index when you save the category. In
**Manual Update** mode, the category page keeps the old order until someone reindexes it in
**System > Index Management**.

## Search results

Search results do not use the positions of a category. They sort by **Relevance** by default.

## Troubleshooting

| What you see | Why | What to do |
|--------------|-----|------------|
| The category ignores your positions | **Default Product Listing Sort By** of the category, or **Product Listing Sort by** in the configuration, is Name or Price | Set the category, or the configuration, to Position |
| You see another order than your customers | You picked a sort order in the **Sort By** list before, and the store remembers it for your session | Pick the default sort order again, or open the page in a private window |
| The products show in the reverse order | The direction is descending: someone clicked the arrow next to **Sort By**, and the store remembers it for that session | Click the arrow again, or open the page in a private window |
| One store view shows another order | That store view has its own value of the sort settings of the category | Select the store view and check **Use Config Settings** and the value |
| The products of a subcategory are always at the end | The category is an anchor category, and those products are not in the category itself | Assign the products to the category and give them a position |
| The new positions do not show | The **Category Products** index is in **Manual Update** mode, or needs a reindex | Reindex in **System > Index Management** |
| Products with the same position change places | Products with equal positions are sorted by product ID | Give each product its own position |

## Related

- [Scope](scope.md) for values per store view.
- [URL rewrites](url-rewrites.md) for the URLs of categories.
