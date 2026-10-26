---
description: Compare Maho's seven product types - simple, configurable, grouped, virtual, bundle, downloadable and gift card - to model your catalog correctly.
---

# Product Types

Understanding product types is essential for setting up your catalog effectively. Maho offers seven product types, each designed for different selling scenarios. Choosing the right type ensures your customers have the best shopping experience and you can manage inventory efficiently.

## Quick Comparison

| Product Type | Physical Shipping | Has Variations | Sold Individually | Best For |
|--------------|-------------------|----------------|-------------------|----------|
| **Simple** | Yes | No | Yes | Single items with no options |
| **Configurable** | Yes | Yes (one parent) | Via child products | Products with size, color, etc. |
| **Grouped** | Yes | Yes (collection) | Each item separate | Related items sold together |
| **Virtual** | No | No | Yes | Services, warranties |
| **Bundle** | Yes | Yes (customizable) | As a kit | Build-your-own kits |
| **Downloadable** | No | No | Yes | Digital files, software, media |
| **Gift Card** | No | No | Yes | Gift cards with email delivery |

## Simple Products

A Simple Product is the most basic and commonly used product type. It represents a single, physical item with no variations.

### When to Use

- The product has no options (no sizes, colors, or other choices)
- Each variation has its own SKU and should be listed separately
- You're selling books, single items, or products that don't need customization

### Real-World Examples

- A paperback book
- A coffee mug with a fixed design
- A pack of batteries
- A kitchen utensil
- A specific model of headphones (e.g., "Sony WH-1000XM5 - Black")

### Key Characteristics

- Has its own SKU and inventory count
- Can have custom options (like engraving text) but not configurable variations
- Straightforward to set up and manage
- Shows directly in search and category listings

---

## Configurable Products

A Configurable Product allows customers to choose from a set of variations (like size and color) before adding to cart. Behind the scenes, each variation is actually a Simple Product linked to a parent Configurable Product.

### When to Use

- The product comes in multiple variations (size, color, material, etc.)
- You want one product page where customers select their options
- Each variation has different inventory levels or SKUs
- You want a cleaner catalog without listing every variation separately

### Real-World Examples

- A t-shirt available in S, M, L, XL and in 5 colors
- Running shoes in different sizes
- A laptop with different RAM and storage configurations
- A phone case available in multiple colors
- Furniture available in different fabrics or finishes

### Key Characteristics

- Parent product is not sold directly (it's a container)
- Each variation (child) is a Simple Product with its own SKU and stock (see [Stock Status](stock-status.md#configurable-products))
- Customers select options using dropdowns or [swatches](configurable-swatches.md) on the product page
- Price can vary by option (e.g., larger size costs more), with an option price on the parent (see [Configurable Product Prices](configurable-product-prices.md))
- Only one unit is added to cart at a time

### Configurable vs. Simple with Custom Options

| Scenario | Use Configurable | Use Simple + Custom Options |
|----------|------------------|----------------------------|
| Need separate inventory per option | ✅ | ❌ |
| Price varies significantly by option | ✅ | ✅ (with price modifiers) |
| Options don't affect inventory | ❌ | ✅ |
| Example: Shirt sizes | ✅ | ❌ |
| Example: Gift wrap yes/no | ❌ | ✅ |

---

## Grouped Products

A Grouped Product displays multiple related Simple Products together on one page. Unlike Configurable Products, each item in a group is purchased separately with its own quantity.

### When to Use

- You're selling related items that are often bought together
- Customers should be able to choose quantities for each item
- Items can also be sold individually elsewhere in your store
- You want to showcase a collection without forcing bundle purchase

### Real-World Examples

- A furniture set (table + 4 chairs, each priced separately)
- A workout equipment collection (dumbbells, mat, resistance bands)
- A skincare routine set (cleanser, toner, moisturizer)
- Camera body shown with compatible lenses
- Wine collection (3 different bottles that complement each other)

### Key Characteristics

- Each item in the group is a Simple Product
- Customers enter quantity for each item (can choose 0 for items they don't want)
- Each item has its own price displayed
- Items can exist in the catalog independently
- No "parent" inventory - stock is tracked per individual item
- Great for cross-selling related products

### Grouped vs. Configurable

| Aspect | Grouped | Configurable |
|--------|---------|--------------|
| Customer selects | Quantity of each item | One option combination |
| Added to cart | Multiple line items | Single line item |
| Items sold separately | Yes, as Simple Products | Child products can be hidden |
| Use case | "Buy these together" | "Choose your options" |

---

## Virtual Products

A Virtual Product is a non-physical product that doesn't require shipping. It's perfect for services and intangible goods that aren't downloaded files.

### When to Use

- You're selling a service
- The product doesn't require shipping or download
- You need to track purchases of intangible items
- Combining with physical products in the same store

### Real-World Examples

- Extended warranty or insurance
- Installation service
- Online consultation or coaching session
- Event tickets
- Donation options

### Key Characteristics

- No weight or shipping dimensions
- Skips shipping step in checkout (if cart contains only virtual products)
- Can still require billing address
- Has inventory management (if you limit quantity, like event seats)
- Often used alongside physical products (add warranty to electronics)

---

## Bundle Products

A Bundle Product lets customers build their own product by choosing from a set of options. Think of it as "build your own kit" where some choices may be required and others optional.

### When to Use

- Customers should customize what's included in a package
- You want to offer a discount for buying items together
- Creating build-your-own or pick-and-choose products
- Offering tiered packages with different component options

### Real-World Examples

- Build-your-own computer (choose processor, RAM, storage, etc.)
- Custom gift basket (pick chocolates, wine, and accessories)
- Meal kit (choose protein, sides, and sauce)
- Camera kit (body + choice of lens + optional accessories)
- Customizable jewelry set (necklace + choice of pendant + chain length)
- Software bundle (base product + optional add-ons)

### Key Characteristics

- Contains "bundle options" - groups of products to choose from
- Options can be required or optional
- Can allow single or multiple selections per option group
- Pricing can be fixed or calculated from components ("dynamic pricing")
- Inventory can track individual components or the bundle itself
- Components are typically Simple or Virtual products

### Bundle Options Types

| Option Type | Description | Example |
|-------------|-------------|---------|
| **Drop-down** | Select one from a list | Choose your processor |
| **Radio Buttons** | Select one, displayed as buttons | Choose your size (S/M/L) |
| **Checkbox** | Select multiple | Add accessories (can pick several) |
| **Multi-Select** | Select multiple from dropdown | Choose included games |

### Bundle Pricing Options

- **Fixed Price**: Bundle has one set price regardless of selections
- **Dynamic Price**: Price calculated from selected components (allows showing "save X%")

---

## Downloadable Products

A Downloadable Product provides customers with files to download after purchase. It combines the storefront experience of physical products with automatic digital delivery.

### When to Use

- Selling digital files (software, music, ebooks, etc.)
- Customers should receive files immediately after payment
- You need to control download access (limits, expiration)
- Combining digital and physical products in one store

### Real-World Examples

- eBooks or PDF guides
- Software and applications
- Music albums or individual tracks
- Stock photos or design assets
- Video courses or tutorials
- Printable artwork or templates
- Fonts or icon packs

### Key Characteristics

- Attach multiple files to a single product (links)
- Provide free samples before purchase
- Set download limits (max number of downloads)
- Set link expiration (days after purchase)
- Customers access downloads from their account
- No shipping required (skips shipping in checkout)
- Can combine with physical shipping if needed (e.g., CD + digital download)

### Download Links vs. Samples

| Type | Purpose | Access |
|------|---------|--------|
| **Links** | The purchased content | After payment only |
| **Samples** | Preview content | Before purchase (free) |

**Example**: For an eBook, you might offer Chapter 1 as a free sample and the full book as the purchasable download link.

---

## Gift Card Products <span class="version-badge">v26.1+</span>

A Gift Card Product lets customers purchase a gift card that can be sent to a recipient via email and redeemed at checkout. Gift cards are a dedicated product type with built-in support for email delivery, scheduled sending, balance tracking, and flexible redemption.

### When to Use

- You want to sell gift cards with fixed or custom amounts
- Recipients should receive a redeemable code via email
- You need balance tracking, partial redemption, and expiration handling
- You want full integration with orders, invoices, and credit memos

### Real-World Examples

- Birthday or holiday gift cards in preset amounts ($25, $50, $100)
- Custom-amount gift cards for corporate gifting
- Promotional gift cards for marketing campaigns
- Admin-created gift cards for customer service situations

### Key Characteristics

- Virtual product (no shipping required, delivered via email)
- Supports fixed amounts, custom amounts, or both
- Generates unique redeemable codes upon invoice payment
- Customers can schedule delivery for a future date
- Personalized messages and optional QR codes/barcodes
- Partial redemption with balance carried forward
- Multiple gift cards can be combined on a single order
- Full audit trail with balance history

### Gift Card vs. Virtual Product

| Scenario | Use Gift Card | Use Virtual |
|----------|---------------|-------------|
| Redeemable gift card with balance | ✅ | ❌ |
| Code-based redemption at checkout | ✅ | ❌ |
| Email delivery with personalization | ✅ | ❌ |
| Balance tracking and partial use | ✅ | ❌ |
| Service or warranty (no redemption) | ❌ | ✅ |
| Event ticket or appointment | ❌ | ✅ |

For full details, see the [Gift Cards guide](gift-cards.md) and the [Gift Cards developer reference](../developer/guide/gift-cards.md).

---

## Choosing the Right Product Type

Use this decision guide to select the appropriate product type:

``` mermaid
flowchart TD
    A{Physical product?} -->|No| B{Gift card?}
    B -->|Yes| GC([Gift Card])
    B -->|No| C{Downloadable file?}
    C -->|Yes| DL([Downloadable])
    C -->|No| VI([Virtual])
    A -->|Yes| D{Has variations?}
    D -->|No| SI([Simple])
    D -->|Yes| E{How are variations purchased?}
    E -->|One at a time| CO([Configurable])
    E -->|Multiple items\nwith quantities| GR([Grouped])
    E -->|Build a custom kit| BU([Bundle])
```

### Common Scenarios

| Scenario | Recommended Type |
|----------|------------------|
| Single item, no options | Simple |
| Shirt with size/color dropdowns | Configurable |
| Living room furniture set | Grouped |
| Extended warranty add-on | Virtual |
| Build-your-own PC | Bundle |
| PDF course or eBook | Downloadable |
| Service appointment | Virtual |
| Store gift card with email delivery | Gift Card |
| Gift card for promotions or gifting | Gift Card |
| Gift box with customer-selected items | Bundle |
| Album available in CD + digital | Simple (CD) + Downloadable, or Bundle |

### Combining Product Types

Some scenarios work best with multiple product types:

- **Physical + Warranty**: Simple/Configurable product with Virtual warranty as a related product
- **Physical + Digital**: Bundle with a Simple product and Downloadable component
- **Service + Materials**: Grouped product with Virtual service and Simple physical items
- **Gift Card + Product**: Offer gift cards alongside regular products in bundles or as upsells at checkout
