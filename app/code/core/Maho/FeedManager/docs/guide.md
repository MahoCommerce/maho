---
title: FeedManager User Guide
---

# Maho FeedManager User Guide

**Version 1.11**

---

## 1. Introduction

Welcome to **Maho FeedManager** - a powerful module for exporting your product catalog to shopping platforms and marketplaces. Whether you're selling on Google Shopping, Facebook, Pinterest, or regional platforms like Idealo and Trovaprezzi, FeedManager makes it easy to create, customize, and automate your product feeds.

### Supported Platforms

| Platform | Format |
|---|---|
| **Google Shopping** | XML (Atom) |
| **Google Local Inventory** | XML or CSV |
| **Facebook / Meta** | XML or CSV |
| **Bing Shopping** | XML |
| **Pinterest** | XML |
| **Idealo** | CSV (EU markets) |
| **Trovaprezzi** | XML (Italy) |
| **OpenAI Commerce** | JSONL |
| **Custom** | XML, CSV, or JSON |

### Key Features

- **Visual Mapping Builders** - Drag-and-drop interface for XML, CSV, and JSON feeds
- **Category Taxonomy Mapping** - Map store categories to platform taxonomies (e.g., Google Product Categories)
- **Dynamic Rules** - Conditional logic for computed values (stock status, sale prices, etc.)
- **Configurable Product Handling** - Smart parent/child product relationships
- **Transformers** - Modify data on-the-fly (price adjustments, text formatting)
- **Formats & Regional Settings** - Per-feed price formatting, currency, and tax configuration
- **Automated Scheduling** - Hourly, daily, or custom generation schedules
- **SFTP/FTP Uploads** - Automatically push feeds to destinations
- **Failure Notifications** - Email and admin inbox alerts when feeds fail

---

## 2. Getting Started

### Accessing FeedManager

Navigate to **Catalog > Feed Manager** in your Maho admin panel. You'll see four main sections:

- **Feeds** - Manage your product feeds
- **Dynamic Rules** - Create conditional attribute rules
- **Category Mapping** - Map store categories to platform taxonomies
- **Destinations** - Configure SFTP/FTP upload targets

### Module Configuration

Go to **System > Configuration > Catalog > Feed Manager** to configure:

| Setting | Description | Default |
|---|---|---|
| Enable Module | Turn FeedManager on/off | Yes |
| Output Directory | Where feed files are saved (relative to `media/`) | `feeds` |
| Batch Size | Products processed per batch (lower = less memory) | 500 |
| Log Retention (Days) | How long to keep generation logs | 30 |

---

## 3. Creating Your First Feed

### Step 1: Basic Settings

Click **"Add New Feed"** and fill in the General tab:

| Field | Description |
|---|---|
| Feed Name | A friendly name (e.g., "Google Shopping - Main Store") |
| Platform | Select your target platform - this pre-configures required fields |
| Store View | Which store's products and prices to export |
| File Format | XML, CSV, or JSON |
| Filename | Output filename (without extension) |

### Step 2: Product Selection

In the **Filters** tab, configure which products to include:

- **Configurable Mode**:
    - *Simple Products Only* - Export only simple products
    - *Children Only* - Export configurable children with item_group_id linking
    - *Both* - Export parent configurables and their children
- **Exclude Disabled** - Skip disabled products
- **Exclude Out of Stock** - Skip products with no stock
- **Condition Groups** - Advanced filtering (see [Product Filtering](#10-product-filtering))

### Step 3: Attribute Mapping

The **Mapping** tab is where you define your feed structure. See the [Mapping Builders](#4-mapping-builders) section for details.

### Step 4: Preview & Generate

Use the **Preview** tab to test your feed with a sample of products. When satisfied, click **"Generate Now"** to create the full feed.

---

## 4. Mapping Builders

FeedManager provides visual builders for each output format. All builders share the same powerful options for data sources.

### Data Source Types

| Source Type | Description | Example |
|---|---|---|
| **Attribute** | Product attribute value | `name`, `price`, `sku` |
| **Static** | Fixed text value | `new`, `AU`, `in stock` |
| **Rule** | Dynamic Rule reference | `stock_status`, `sale_price` |
| **Combined** | Template with multiple values | `{{brand}} - {{name}}` |
| **Category Taxonomy** | Mapped platform category from [Category Mapping](#6-category-mapping) | `google`, `facebook` |

!!! tip "Category Taxonomy Source"
    When you select "Category Taxonomy" as the source type, set the source value to the platform code (e.g., `google`). FeedManager will automatically look up the deepest mapped category for each product and output the platform's category path. See [Category Mapping](#6-category-mapping) for setup.

### XML Structure Builder

Build nested XML structures with full control over elements, attributes, CDATA, and namespaces.

!!! example "Google Shopping Item"
    ```xml
    <item>
      <g:id>SKU123</g:id>
      <g:title><![CDATA[Product Name Here]]></g:title>
      <g:price>29.99 AUD</g:price>
      <g:availability>in stock</g:availability>
      <g:shipping>
        <g:country>AU</g:country>
        <g:price>9.95 AUD</g:price>
      </g:shipping>
    </item>
    ```

### CSV Column Builder

Define columns, their order, and data sources. Configure delimiter (comma, tab, pipe) and text enclosure.

!!! example "CSV Output"
    ```csv
    id,title,description,price,availability,link
    SKU123,"Product Name","Product description here",29.99,in stock,https://store.com/product
    ```

### JSON Structure Builder

Create nested JSON objects with arrays and proper data types.

!!! example "JSON Output"
    ```json
    {
      "products": [
        {
          "id": "SKU123",
          "title": "Product Name",
          "price": 29.99,
          "variants": [
            {"size": "S", "color": "Red"},
            {"size": "M", "color": "Blue"}
          ]
        }
      ]
    }
    ```

---

## 5. Using Parent Product Data

When exporting configurable product children, you often need data from the parent product. FeedManager's **"Use Parent"** feature handles this elegantly.

### Use Parent Modes

| Mode | Behavior | Best For |
|---|---|---|
| **Never** | Always use child product's value | SKU, variant-specific attributes |
| **If Empty** | Use parent's value only if child's is empty | Description, brand, images |
| **Always** | Always use parent's value, ignore child's | URL, main product image, category |

!!! example "Configurable T-Shirt with Size Variants"
    You have a configurable T-shirt with children for each size. Here's how to map attributes:

    | Attribute | Use Parent | Reason |
    |---|---|---|
    | SKU | Never | Each variant has its own SKU |
    | Name | If Empty | Use parent name if child doesn't have one |
    | Description | Always | All variants share the same description |
    | URL | Always | Link to main configurable page, not child |
    | Image | If Empty | Use child image if available, else parent's |
    | Price | Never | Each variant may have different pricing |
    | Size | Never | Variant-specific attribute |
    | Color | Never | Variant-specific attribute |
    | item_group_id | N/A | Automatically set to parent's entity_id |

!!! tip "Item Group ID"
    When using "Children Only" mode, FeedManager automatically adds `item_group_id` to link variants together. This tells platforms like Google Shopping that these products are variations of the same item.

---

## 6. Category Mapping

Most shopping platforms require products to be categorized using their own taxonomy. For example, Google Shopping uses the **Google Product Taxonomy** with over 6,000 categories. FeedManager lets you map your store categories to platform categories once, and all feeds using that platform will automatically use the correct values.

### Accessing Category Mapping

Navigate to **Catalog > Feed Manager > Category Mapping**. Select a platform from the dropdown at the top to begin mapping.

### How It Works

1. **Select a platform** from the dropdown (e.g., Google, Facebook, Bing)
2. Your store's category tree is displayed on the left
3. For each category, type into the search field to find the matching platform category
4. The search queries the bundled taxonomy file and shows matching results in a dropdown
5. Click a result to apply the mapping

Mappings are **global per platform** -- once you map a category for Google, all Google Shopping feeds will use that mapping automatically via the "Category Taxonomy" source type.

### Taxonomy Search

The search field supports multi-word queries. For example, typing `tennis shoes` will match any taxonomy entry containing both words. Results show the full category path and ID:

!!! example "Searching for 'tennis'"
    Results might include:

    - `3854` -- Sporting Goods > Athletics > Tennis
    - `3855` -- Sporting Goods > Athletics > Tennis > Tennis Racquets
    - `3856` -- Sporting Goods > Athletics > Tennis > Tennis Balls
    - `1648` -- Apparel > Shoes > Athletic Shoes > Tennis Shoes

### Bulk Mapping

To quickly apply the same platform category to multiple store categories:

1. Map one category first, then click its **Copy** button
2. The page enters **Bulk Mode** -- the source row highlights in blue
3. Click any other category row to apply the same mapping
4. Use **Shift+Click** to apply to a range of categories
5. Press **Escape** or click "Exit Bulk Mode" to finish

### Auto-Map Unmapped

Click the **"Auto-Map Unmapped"** button to automatically match unmapped store categories to platform taxonomy entries by name. This uses a simple name-based search and takes the first result, so it's best used as a starting point that you then review and correct manually.

### Using Category Mappings in Feeds

In your feed's attribute mapping, set the source type to **"Category Taxonomy"** and the source value to the platform code (e.g., `google`). FeedManager will automatically find the deepest (most specific) mapped category for each product and output the platform's category path.

!!! example "Google Product Category Mapping"
    | Feed Attribute | Source Type | Source Value |
    |---|---|---|
    | google_product_category | Category Taxonomy | google |

    If a product belongs to the store category "Tennis Racquets" which is mapped to Google's `Sporting Goods > Athletics > Tennis > Tennis Racquets`, the feed will output that full path.

### Supported Taxonomies

| Platform | Taxonomy |
|---|---|
| Google Shopping | Google Product Taxonomy (bundled) |
| Facebook / Meta | Google Product Taxonomy (Facebook uses the same taxonomy) |
| Bing Shopping | Google Product Taxonomy (Bing accepts Google-formatted feeds) |
| Pinterest | Google Product Taxonomy |
| Idealo | Google Product Taxonomy |
| Trovaprezzi | Google Product Taxonomy |

---

## 7. Dynamic Rules

Dynamic Rules let you create conditional, computed values based on product attributes. Think of them as "IF-THEN-ELSE" logic for your feed data.

### How Dynamic Rules Work

A Dynamic Rule consists of multiple **output rows**, evaluated top-to-bottom. The first row whose conditions match determines the output. If no conditions match, the last row (usually a default) is used.

### Pre-Built Rules

| Rule Code | Description | Output |
|---|---|---|
| `stock_status` | Stock availability check | "in_stock" or "out_of_stock" |
| `availability` | Google Shopping format | "in stock" or "out of stock" |
| `identifier_exists` | GTIN/MPN presence check | "yes" or "no" |
| `sale_price` | Special price when valid | special_price value or empty |

### Available Operators

| Operator | Description | Example |
|---|---|---|
| `eq` | Equals | status eq "1" |
| `neq` | Not equals | type_id neq "bundle" |
| `gt` / `lt` | Greater/Less than | qty gt "0" |
| `gteq` / `lteq` | Greater/Less than or equal | price gteq "10" |
| `in` / `nin` | In list / Not in list | category_ids in "5,10,15" |
| `like` / `nlike` | Contains / Not contains | name like "sale" |
| `null` / `notnull` | Is empty / Has value | gtin notnull |
| `gt_attr` / `lt_attr` | Compare to another attribute | price gt_attr "cost_price" |

### Creating Custom Rules

!!! example "Custom Label for High-Value Products"
    Create a rule to label products based on price tiers for Google Shopping custom labels:

    | Row | Condition | Output Type | Output Value |
    |---|---|---|---|
    | 1 | price > 500 | Static | premium |
    | 2 | price > 100 | Static | mid-range |
    | 3 | (default) | Static | budget |

!!! example "Limited Stock Warning"
    Show "limited_availability" when stock is low:

    | Row | Conditions | Output |
    |---|---|---|
    | 1 | qty > 10 | "in stock" |
    | 2 | qty > 0 AND qty <= 10 | "limited availability" |
    | 3 | (default) | "out of stock" |

!!! example "Image Fallback"
    Use a specific image attribute if available, otherwise fall back to base image:

    | Row | Condition | Output Type | Output |
    |---|---|---|---|
    | 1 | google_image notnull | Attribute | google_image |
    | 2 | facebook_image notnull | Attribute | facebook_image |
    | 3 | (default) | Attribute | image |

!!! example "Profitable Products Flag"
    Using attribute comparison to check if price is greater than cost:

    | Row | Condition | Output |
    |---|---|---|
    | 1 | price **gt_attr** cost_price | "profitable" |
    | 2 | (default) | "break-even" |

    The `gt_attr` operator compares the price attribute directly against the cost_price attribute.

---

## 8. Transformers

Transformers modify attribute values before they're added to your feed. You can chain multiple transformers together.

### Available Transformers

| Transformer | Description | Options |
|---|---|---|
| `strip_tags` | Remove HTML tags | allowed_tags (optional) |
| `truncate` | Limit text length | max_length, suffix, break_words |
| `format_price` | Format as currency | decimals, currency, include_currency |
| `map_values` | Replace values | mapping (key=value pairs) |
| `default_value` | Fallback if empty | default |
| `uppercase` | Convert to UPPERCASE | - |
| `lowercase` | Convert to lowercase | - |
| `combine_fields` | Merge multiple fields | template |

### Transformer Syntax

Chain transformers using the pipe character: `transformer1|transformer2:option=value`

!!! example "Clean Product Description for Google"
    Google Shopping has specific requirements for descriptions:

    ```
    strip_tags|truncate:max_length=5000,suffix=...
    ```

    This removes HTML tags and limits to 5000 characters with "..." suffix.

!!! example "Price Markup for Marketplace"
    Add 10% markup for a specific marketplace:

    ```
    format_price:decimals=2,multiply=1.10,currency=AUD
    ```

    **Input:** 100.00 --> **Output:** 110.00 AUD

!!! example "Map Stock Status Values"
    Convert numeric stock status to text:

    ```
    map_values:1=in stock,0=out of stock
    ```

    **Input:** 1 --> **Output:** in stock

!!! example "Combine Brand and Name"
    Create a formatted title:

    ```
    combine_fields:template={{brand}} - {{name}} ({{color}})
    ```

    **Output:** Nike - Air Max 90 (Black)

!!! example "Google Shopping Title Optimization"
    Format title with brand prefix and length limit:

    ```
    combine_fields:template={{brand}} {{name}}|truncate:max_length=150
    ```

!!! tip "Price Adjustments for Different Channels"
    Create separate feeds for each marketplace with different price multipliers:

    - **Google Shopping:** Base price
    - **eBay:** `format_price:multiply=1.15` (15% markup for fees)
    - **Amazon:** `format_price:multiply=1.20` (20% markup for fees)

---

## 9. Formats & Regional Settings

Each feed has its own formatting and regional settings, configured in the **Feed Content** tab under **Formats & Regional Settings**. These control how prices, numbers, and URLs are formatted in the output.

### Number Format Presets

Select a preset to quickly configure decimal and thousands separators, or choose "Custom" to set them manually.

| Preset | Format | Example |
|---|---|---|
| English | Period decimal, comma thousands | 1,234.56 |
| European | Comma decimal, period thousands | 1.234,56 |
| Swiss | Period decimal, apostrophe thousands | 1'234.56 |
| Indian | Period decimal, comma thousands | 1,23,456.78 |
| Custom | Manually configure separators | -- |

### Price Settings

| Setting | Description | Default |
|---|---|---|
| Price Currency | Currency code used for price output | Store's base currency |
| Price Decimals | Number of decimal places | 2 |
| Price Decimal Point | Character for decimal point (`.` or `,`) | `.` |
| Price Thousands Separator | Character for thousands grouping (or empty for none) | (empty) |
| Append Currency to Prices | Add currency code suffix to prices (e.g., "295.00 AUD") | Yes |

!!! example "Google Shopping (Australia)"
    Google requires prices in the format `XX.XX CUR`:

    | Setting | Value |
    |---|---|
    | Preset | English |
    | Currency | AUD |
    | Decimals | 2 |
    | Append Currency | Yes |

    **Output:** `295.00 AUD`

!!! example "Idealo (Germany)"
    Idealo expects European number formatting:

    | Setting | Value |
    |---|---|
    | Preset | European |
    | Currency | EUR |
    | Decimals | 2 |
    | Append Currency | No |

    **Output:** `295,00`

### Other Output Settings

| Setting | Description | Default |
|---|---|---|
| Tax Mode | Include or exclude tax from prices | Include Tax |
| Exclude Category from URL | Use direct product URLs without category path | Yes |
| No Image URL | Fallback image URL when a product has no image | (empty) |

---

## 10. Product Filtering

Use condition groups to precisely control which products appear in your feed.

### Condition Groups

Filters use **groups** with **OR** logic between groups, and **AND** logic within a group.

!!! example "Export Specific Categories with Price Threshold"
    Include products from category 10 OR category 15, but only if price > $20:

    **Group 1:**

    - category_ids IN "10"
    - AND price > "20"

    **OR**

    **Group 2:**

    - category_ids IN "15"
    - AND price > "20"

### Common Filter Patterns

| Use Case | Filter Configuration |
|---|---|
| Only products with images | image IS NOT EMPTY |
| Exclude certain brands | brand NOT IN "Brand1,Brand2" |
| Only visible products | visibility IN "2,4" (Catalog, Both) |
| Minimum price threshold | price > "10" |
| In-stock only | is_in_stock = "1" |
| Specific product types | type_id IN "simple,configurable" |

---

## 11. Upload Destinations

Automatically upload generated feeds to SFTP/FTP servers.

### Creating a Destination

Navigate to **Catalog > Feed Manager > Destinations** and click "Add New Destination".

| Field | Description |
|---|---|
| Name | Friendly name for the destination |
| Type | SFTP or FTP |
| Host | Server hostname or IP |
| Port | Connection port (22 for SFTP, 21 for FTP) |
| Username | Login username |
| Auth Type | Password or Private Key |
| Remote Path | Directory path on remote server |

### Linking Feeds to Destinations

In your feed's General tab, select the destination and enable "Auto Upload" to automatically push the feed after each generation.

---

## 12. Notifications

FeedManager can alert you when feed generation or upload fails. Notifications are configured **per-feed** in the feed edit form.

### Notification Settings

| Setting | Options | Description |
|---|---|---|
| Notification Method | None, Email Only, Admin Inbox Only, Both | How to deliver failure alerts |
| Notification Frequency | Every Failure, Once Until Success | How often to send alerts |
| Notification Email | (text field) | Recipient email(s), comma-separated. Defaults to store general contact. |

### Notification Methods

| Method | Behavior |
|---|---|
| **None** | No notifications sent (default) |
| **Email Only** | Sends an email with feed name, failure type, and error details |
| **Admin Inbox Only** | Adds a critical-level notification to the Maho admin inbox |
| **Both** | Sends both email and admin inbox notification |

### Notification Frequency

| Frequency | Behavior |
|---|---|
| **Every Failure** | Sends a notification on every failed generation or upload |
| **Once Until Success** | Sends one notification on the first failure, then suppresses further alerts until the feed generates successfully. After a success, alerts are re-enabled for the next failure. |

### What Triggers Notifications

- **Generation failure** -- Feed generation encounters errors (validation, product processing, etc.)
- **Upload failure** -- Feed file fails to upload to a configured destination
- **Timeout** -- Feed generation is stuck for more than 30 minutes

!!! tip "'Once Until Success' for Scheduled Feeds"
    If a feed runs hourly and fails repeatedly due to a configuration issue, "Once Until Success" prevents your inbox from being flooded. You'll get one alert, fix the issue, and the next successful run resets the flag automatically.

---

## 13. Scheduling & Automation

### Schedule Options

| Schedule | Description |
|---|---|
| Hourly | Generate every hour |
| Every 6 Hours | Generate four times per day |
| Daily | Generate once per day at midnight |
| Twice Daily | Generate at midnight and noon |
| Manual Only | No automatic generation |

### Cron Jobs

FeedManager registers these cron jobs:

- **feedmanager_generate_scheduled** - Runs hourly, generates feeds based on their schedules
- **feedmanager_cleanup_logs** - Runs daily at 3:30 AM, removes old logs

!!! warning "Cron Must Be Running"
    Scheduled generation requires Maho's cron system to be properly configured. Verify cron is running:

    ```bash
    ./maho cron:run
    ```

---

## 14. CLI Commands

FeedManager provides command-line tools for automation and debugging.

### Available Commands

```bash
# List all feeds with status
./maho feed:list

# Generate a specific feed
./maho feed:generate 4

# Generate all enabled feeds
./maho feed:generate:all

# Generate all feeds (including disabled)
./maho feed:generate:all --include-disabled

# Validate a feed without regenerating
./maho feed:validate 4
```

!!! example "Cron Script for Custom Scheduling"
    Add to your system crontab for custom generation times:

    ```bash
    # Generate Google feed at 2 AM and 2 PM daily
    0 2,14 * * * cd /var/www/html && ./maho feed:generate 4

    # Generate all feeds every 4 hours
    0 */4 * * * cd /var/www/html && ./maho feed:generate:all
    ```

---

## 15. Troubleshooting

### Common Issues

!!! warning "Feed shows 0 products"
    - Check your filter conditions -- they may be too restrictive
    - Verify "Exclude Disabled" and "Exclude Out of Stock" settings
    - Ensure products exist in the selected store view
    - Check the Configurable Mode setting

!!! warning "Feed generation times out"
    - Reduce batch size in System Configuration
    - Use CLI command instead of admin interface for large catalogs
    - Enable gzip compression to reduce file size

!!! warning "SFTP upload fails"
    - Verify credentials by testing connection
    - Check remote directory exists and is writable
    - Ensure server's IP is whitelisted on destination
    - Check PHP has required extensions (ssh2 for SFTP)

!!! warning "Dynamic Rule not working"
    - Verify rule is enabled
    - Check condition operators and values
    - Use Preview to test with sample products
    - Ensure attribute code matches exactly (case-sensitive)

!!! warning "Category Taxonomy shows empty in feed"
    - Verify you've mapped categories for the correct platform under Catalog > Feed Manager > Category Mapping
    - Check the source value is set to the platform code (e.g., `google`)
    - Ensure the product belongs to at least one mapped category
    - Use Preview to check individual product output

### Checking Logs

Generation logs are stored in the database and viewable in the feed's Logs tab. System errors are logged to:

```
var/log/system.log
var/log/exception.log
```

### Getting Help

For issues or feature requests, visit: [github.com/MahoCommerce/maho](https://github.com/MahoCommerce/maho)

---

*Maho FeedManager User Guide -- (c) 2025-2026 Maho Commerce -- [mahocommerce.com](https://mahocommerce.com)*
