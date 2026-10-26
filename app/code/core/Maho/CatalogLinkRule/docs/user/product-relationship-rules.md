---
description: Automatically generate related products, up-sells and cross-sells in Maho with condition-based rules instead of linking every product by hand.
---

# Product Relationship Rules <span class="version-badge">v26.1+</span>

Product Relationship Rules in Maho enable you to automatically manage Related Products, Up-sells, and Cross-sells based on powerful, condition-based rules. Instead of manually assigning product relationships one by one, define rule-based criteria to dynamically generate these links across your entire catalog.

## Overview

Product Relationship Rules automate the tedious process of manually linking products, allowing you to:

- **Automate product linking**: Automatically generate related, up-sell, and cross-sell relationships
- **Rule-based conditions**: Use sophisticated product attribute matching with source and target conditions
- **Priority system**: Control which rules take precedence when multiple rules apply
- **Smart sorting**: Order linked products by price, name, date, or random
- **Flexible matching**: Include a special "Source Match" condition to link products with matching attributes
- **Performance optimized**: Batch processing with configurable cron scheduling
- **Per-type rules**: Create separate rules for related, up-sell, and cross-sell products

## Key Benefits

### For Merchandisers
- **Time savings**: Set up rules once instead of manually linking thousands of products
- **Consistency**: Ensure all products follow the same relationship logic
- **Merchandising strategy**: Implement sophisticated cross-selling strategies at scale
- **Dynamic updates**: Product relationships automatically update as catalog changes
- **A/B testing**: Test different relationship strategies with priority-based rules

### For E-commerce Managers
- **Revenue optimization**: Maximize cross-sell and up-sell opportunities
- **Inventory management**: Promote complementary or alternative products automatically
- **Category-based targeting**: Link products within or across categories
- **Seasonal campaigns**: Use date ranges to activate promotional relationships
- **Performance insights**: Consistent rule-based relationships enable better analytics

### For Business Owners
- **Increased AOV**: Strategic product relationships drive higher average order values
- **Reduced manual work**: Eliminate hours of tedious product relationship management
- **Scalability**: Handle catalogs of any size without manual intervention
- **Competitive advantage**: Enterprise-level merchandising automation
- **Flexibility**: Adapt relationship strategies quickly as business needs change

## How It Works

Product Relationship Rules operate on a two-stage matching system:

1. **Source Product Conditions**: Define which products should have relationships (e.g., "All products in the Electronics category")
2. **Target Product Conditions**: Define which products should be linked to the source products (e.g., "Products in the Accessories category")

!!! warning "Important: Rule Processing Replaces Existing Links"
    When rules are processed, they **replace ALL existing product links** for the configured link type. This means:

    - **Related Products rules** replace all existing Related Product links
    - **Up-sell rules** replace all existing Up-sell links
    - **Cross-sell rules** replace all existing Cross-sell links

    If you have manually-created product links you want to preserve, either:

    1. Exclude those products from your rule conditions
    2. Only use rules for link types that don't have manual links
    3. Back up your database before processing rules

### Special Feature: Source Match Conditions

The unique **Source Match** condition in target conditions allows you to link products that share attributes with the source product. For example:

- Link products in the **same category** as the source product
- Link products with the **same manufacturer** as the source product
- Link products with the **same color** as the source product
- Link products with **matching custom attributes**

This powerful feature enables context-aware product relationships that adapt to each product's specific attributes.

## Rule Components

### Basic Information

| Field | Description | Example |
|-------|-------------|---------|
| **Rule Name** | Descriptive name for the rule | "Cross-sell: Same Category Accessories" |
| **Description** | Optional notes about the rule's purpose | "Show accessories from the same category" |
| **Status** | Active or Inactive | Active |
| **Link Type** | Type of relationship to create | Related Products / Up-sells / Cross-sells |
| **Priority** | Lower number = higher priority (0-999+) | 10 |
| **Sort Order** | How to order the linked products | Price: Low to High |
| **Maximum Links** | Limit number of linked products (optional) | 4 |
| **From Date** | Optional start date for rule activation | 2026-01-01 |
| **To Date** | Optional end date for rule deactivation | 2025-12-31 |

### Link Types

Maho supports three types of product relationships:

| Link Type | Admin Display Location | Frontend Usage | Purpose |
|-----------|------------------------|----------------|---------|
| **Related Products** | "Related Products" tab | Product page, sidebar | Show complementary products |
| **Up-sell Products** | "Up-sells" tab | Product page, cart | Suggest premium alternatives |
| **Cross-sell Products** | "Cross-sells" tab | Shopping cart | Suggest additional purchases |

### Sort Orders

Control how linked products are ordered:

| Sort Order | Description | Use Case |
|------------|-------------|----------|
| **Random** | Shuffle products randomly | Provide variety on each page load |
| **Price: Low to High** | Cheapest products first | Encourage add-on purchases |
| **Price: High to Low** | Most expensive products first | Promote premium options |
| **Name: A-Z** | Alphabetical ascending | Organized browsing |
| **Name: Z-A** | Alphabetical descending | Reverse alphabetical |
| **Newest First** | Most recently added products | Highlight new arrivals |
| **Oldest First** | Longest-standing products | Promote established products |

### Priority System

When multiple rules could apply to a product:

1. Rules are evaluated in order of **priority** (lowest number first)
2. The **first matching rule** for each product wins
3. Only **one rule** per product per link type is applied
4. Higher priority rules override lower priority rules

**Example**:
- Rule A (Priority 10): Links all "T-Shirts" to "Jeans"
- Rule B (Priority 20): Links all "Blue T-Shirts" to "Shorts"
- Result: Blue T-Shirts get linked to Jeans (Rule A wins due to lower priority number)

## Source Product Conditions

Define which products should receive relationship links using standard product attribute conditions:

### Common Condition Types

| Condition | Operators | Example |
|-----------|-----------|---------|
| **Category** | is, is not, contains, does not contain | Category is "Electronics/Laptops" |
| **Attribute Set** | is, is not | Attribute Set is "Clothing" |
| **SKU** | is, is not, contains, starts with, ends with | SKU starts with "SHIRT-" |
| **Name** | is, is not, contains | Name contains "Wireless" |
| **Price** | equals, not equals, greater than, less than, between | Price is greater than 100 |
| **Status** | is, is not | Status is "Enabled" |
| **Visibility** | is, is not | Visibility is "Catalog, Search" |
| **Type** | is, is not | Type is "Simple" |
| **Stock Status** | is, is not | Stock Status is "In Stock" |
| **Manufacturer** | is, is not, is one of | Manufacturer is "Acme Corp" |
| **Custom Attributes** | Various depending on type | Color is "Blue" |

    ## Target Product Conditions

Define which products should be linked to the matched source products using the same condition types PLUS the special **Source Match** condition:

### Standard Conditions

All the same condition types as Source Product Conditions apply here (Category, Price, SKU, etc.)

### Source Match Condition

The **Source Match** condition creates context-aware relationships by matching target products to source product attributes:

| Match Attribute | Operator | Description | Example Use Case |
|----------------|----------|-------------|------------------|
| **Category** | matches source / does not match | Target shares at least one category | "Related products from the same category" |
| **Category** | does not match source | Target is in different categories | "Cross-sell products from other categories" |
| **Attribute Set** | matches source / does not match | Target has same attribute set | "Up-sell similar product types" |
| **Manufacturer** | matches source / does not match | Target has same brand | "More from the same manufacturer" |
| **Color** | matches source / does not match | Target has same color attribute | "Matching color accessories" |
| **Size** | matches source / does not match | Target has same size | "Coordinating sized items" |
| **Material** | matches source / does not match | Target has same material | "Similar material products" |
| **Any Custom Attribute** | matches source / does not match | Match any product attribute | Context-specific relationships |

**Example - Same Category Related Products**:
```
Source Conditions:
  - Category is "Clothing/T-Shirts"

Target Conditions:
  IF ALL of these conditions are TRUE:
    - Category (Source Match) matches source
    - Type is "Simple"
    - Stock Status is "In Stock"

Result: Each T-shirt gets related to other T-shirts in the same category
```

**Example - Cross-Category Up-sells**:
```
Source Conditions:
  - Category is "Electronics/Cameras"
  - Price is less than 1000

Target Conditions:
  IF ALL of these conditions are TRUE:
    - Category is "Electronics/Cameras"
    - Price is greater than source product price
    - Stock Status is "In Stock"

Result: Each camera gets up-sold to more expensive cameras
```

## Rule Examples

### Example 1: Same-Category Related Products

**Goal**: Show other products from the same category as related products

**Configuration**:
- **Name**: "Related: Same Category Products"
- **Status**: Active
- **Link Type**: Related Products
- **Priority**: 10
- **Sort Order**: Random
- **Max Links**: 6

**Source Conditions**:
```
IF ALL of these conditions are TRUE:
  - Status is "Enabled"
  - Visibility is "Catalog, Search"
```

**Target Conditions**:
```
IF ALL of these conditions are TRUE:
  - Category (Source Match) matches source
  - Stock Status is "In Stock"
```

### Example 2: Brand-Specific Up-sells

**Goal**: Up-sell higher-priced products from the same manufacturer

**Configuration**:
- **Name**: "Up-sell: Same Brand Premium"
- **Status**: Active
- **Link Type**: Up-sell Products
- **Priority**: 20
- **Sort Order**: Price: Low to High
- **Max Links**: 4

**Source Conditions**:
```
IF ALL of these conditions are TRUE:
  - Type is "Simple"
  - Price is less than 500
```

**Target Conditions**:
```
IF ALL of these conditions are TRUE:
  - Manufacturer (Source Match) matches source
  - Price is greater than source product price
  - Stock Status is "In Stock"
```

### Example 3: Accessory Cross-sells

**Goal**: Cross-sell accessories from a specific category

**Configuration**:
- **Name**: "Cross-sell: Camera Accessories"
- **Status**: Active
- **Link Type**: Cross-sell Products
- **Priority**: 15
- **Sort Order**: Price: Low to High
- **Max Links**: 8

**Source Conditions**:
```
IF ALL of these conditions are TRUE:
  - Category is "Electronics/Cameras"
  - Status is "Enabled"
```

**Target Conditions**:
```
IF ALL of these conditions are TRUE:
  - Category is "Electronics/Camera Accessories"
  - Stock Status is "In Stock"
  - Price is less than 100
```

### Example 4: Seasonal Promotions

**Goal**: Promote winter accessories with winter clothing

**Configuration**:
- **Name**: "Related: Winter Accessories"
- **Status**: Active
- **Link Type**: Related Products
- **Priority**: 5
- **Sort Order**: Newest First
- **Max Links**: 5
- **From Date**: 2026-11-01
- **To Date**: 2027-03-31

**Source Conditions**:
```
IF ANY of these conditions is TRUE:
  - Category is "Clothing/Winter Coats"
  - Category is "Clothing/Winter Jackets"
```

**Target Conditions**:
```
IF ANY of these conditions is TRUE:
  - Category is "Accessories/Scarves"
  - Category is "Accessories/Gloves"
  - Category is "Accessories/Hats"
```

### Example 5: Matching Color Coordination

**Goal**: Show products in matching colors as related items

**Configuration**:
- **Name**: "Related: Matching Colors"
- **Status**: Active
- **Link Type**: Related Products
- **Priority**: 30
- **Sort Order**: Random
- **Max Links**: 4

**Source Conditions**:
```
IF ALL of these conditions are TRUE:
  - Category is "Clothing"
  - Color attribute exists
```

**Target Conditions**:
```
IF ALL of these conditions are TRUE:
  - Category (Source Match) does not match source
  - Color (Source Match) matches source
  - Stock Status is "In Stock"
```

## Admin Interface

Navigate to: **Catalog > Product Relationship Rules**

## Processing Rules

Product Relationship Rules are processed automatically via cron job, but can also be triggered manually.

### Automatic Processing (Cron)

By default, rules are processed daily at 3:00 AM (server time) via the cron job:

```
cataloglinkrule_apply_all
```

### Manual Processing (CLI)

Process all rules immediately from the command line:

```bash
# Process all active rules for all link types
./maho cron:run cataloglinkrule_apply_all

# View the cron job status
./maho cron:list
```

### How Processing Works

1. **Load Active Rules**: Fetches all active rules within their date ranges, ordered by priority (lowest number first)
2. **Group by Link Type**: Groups rules by link type (related, up-sell, cross-sell) to process each type separately
3. **Build Product-Rule Map**: For each rule in priority order, finds all products matching source conditions and assigns them to the first matching rule (highest priority wins)
4. **Batch Products**: Splits matched products into batches of 100 for memory-efficient processing
5. **Process Each Batch** (within transaction):
   - **Delete Old Links**: Removes all existing product links for products in this batch for this link type
   - **For Each Product**:
     - Loads the source product with all attributes (needed for Source Match conditions)
     - Matches target products based on target conditions (sorting is applied during matching)
     - Applies max links limit if configured
     - Excludes self-links (product linking to itself)
     - Creates new link records with sequential position values
6. **Commit Transaction**: All changes for the batch are committed atomically (or rolled back on error)

## Technical Details

Rules are stored in the `catalog_product_link_rule` table.

## Best Practices

### Rule Organization

1. **Set maximum links**: Use reasonable limits (4-8 typically) to prevent oversized relationship sets
2. **Schedule off-peak**: Run cron during low-traffic hours
3. **Use specific conditions**: Narrow conditions improve processing speed
4. **Monitor rule count**: Fewer, well-designed rules perform better than many overlapping rules
