---
description: Bulk manage Maho categories with CSV import and export - create, update and delete categories and handle multi-store data across your entire catalog.
---

# Category Import/Export

The Category Import/Export feature allows you to bulk manage categories in Maho using CSV files. This system supports creating new categories, updating existing ones, deleting categories, and managing multi-store data.

## GUI Usage

### Accessing Import/Export

1. Navigate to **System > Import/Export > Import** or **Export** in the admin panel
2. Select **Categories** from the Entity Type dropdown
3. Choose your import behavior and upload your CSV file

### Import Behaviors

- **Append Complex Data**: Updates existing categories and creates new ones (recommended)
- **Replace Existing Complex Data**: Works exactly the same as Append Complex Data for categories
- **Delete Entities**: Deletes categories specified in the CSV

### Export Options

- Navigate to **System > Import/Export > Export**
- Select **Categories** as Entity Type
- Configure filters if needed (optional)
- Download the generated CSV file

## CSV Format

### Required Columns

| Column | Description | Required | Example |
|--------|-------------|----------|---------|
| `category_id` | Category ID number | No* | `123` |
| `parent_id` | Parent category ID | No** | `2` (default category) |
| `_store` | Store view code | Yes | `""` (default) or `default` |

*Optional for UPDATE operations, ignored for CREATE operations  
**Optional, defaults to category ID 2 (Default Category) for new categories

### Standard Attributes

| Column | Description | Type | Example |
|--------|-------------|------|---------|
| `name` | Category name | Text | `Electronics` |
| `description` | Category description | Text | `Electronic products and accessories` |
| `is_active` | Active status | Boolean | `1` (active) or `0` (inactive) |
| `include_in_menu` | Show in navigation menu | Boolean | `1` (yes) or `0` (no) |
| `is_anchor` | Is anchor category | Boolean | `1` (yes) or `0` (no) |
| `url_key` | URL key (auto-generated if empty) | Text | `electronics` |
| `meta_title` | Meta title for SEO | Text | `Electronics - Shop Online` |
| `meta_description` | Meta description for SEO | Text | `Browse our electronics collection` |
| `meta_keywords` | Meta keywords for SEO | Text | `electronics, gadgets, tech` |
| `position` | Sort order | Integer | `10` |
| `image` | Category image filename | Text | `electronics.jpg` |

### Advanced Attributes

| Column | Description | Type | Example |
|--------|-------------|------|---------|
| `display_mode` | Display mode | Text | `PRODUCTS`, `PAGE`, `PRODUCTS_AND_PAGE` (or export labels: `Products only`, `Static block only`, `Static block and products`) |
| `is_dynamic` | Dynamic category | Boolean | `1` or `0` |
| `landing_page` | CMS block for landing page | Text | `electronics_landing` |
| `custom_design` | Custom design theme | Text | `default/modern` |
| `page_layout` | Page layout | Text | `one_column`, `two_columns_left`, `two_columns_right`, `three_columns` |
| `custom_design_from` | Custom design from date | Date | `2024-01-01` |
| `custom_design_to` | Custom design to date | Date | `2024-12-31` |

## Import Operations

### Import Behavior Details

Both **Append Complex Data** and **Replace Existing Complex Data** work identically for categories:
- **Update existing categories** if `category_id` is provided and exists
- **Create new categories** if no `category_id` is provided or if the ID doesn't exist
- **Preserve existing attributes** not mentioned in the CSV
- **Update provided attributes** with CSV values

### Creating New Categories

To create new categories, omit the `category_id` or provide a non-existent ID:

```csv
category_id,parent_id,_store,name,url_key,is_active,include_in_menu
,2,,Electronics,electronics,1,1
,2,,Mobile Phones,mobile-phones,1,1
,2,,Laptops,laptops,1,1
```

### Updating Existing Categories

To update existing categories, provide the `category_id`:

```csv
category_id,parent_id,_store,name,description
8,2,,Electronics,Updated description for electronics
15,8,,Smartphones,Now called smartphones
```

### Deleting Categories

Set Import Behavior to "Delete Entities" and provide the `category_id` of categories to delete:

```csv
category_id,parent_id,_store
123,,
456,,
789,,
```

!!! warning "Deletion Warning"
    Deleting categories will also delete all subcategories and may affect products assigned to those categories.

## Export Operations

### Basic Export

1. Go to System > Import/Export > Export
2. Select "Categories" as Entity Type
3. Click "Continue" to download CSV

### Filtered Export

You can filter the export using the available filters:
- Category ID range
- Category name contains
- Store view selection

## Multi-Store Support

### Store Scope Rules

- **Empty `_store`**: Global/default scope (applies to all stores unless overridden)
- **Store code**: Specific store view (e.g., `default`, `french`, `german`)

### Multi-Store CSV Format

```csv
category_id,parent_id,_store,name,description
8,2,,Electronics,English description
8,2,french,Électronique,Description française
8,2,german,Elektronik,Deutsche Beschreibung
15,8,,Mobile Phones,English description
15,8,french,Téléphones,Description française
15,8,german,Telefone,Deutsche Beschreibung
```

### Store-Specific Updates

You can update only specific store views by providing the same category_id with different store codes:

```csv
category_id,parent_id,_store,name
8,2,french,Électronique Moderne
8,2,german,Moderne Elektronik
```

## Category Hierarchy System

### Hierarchy Structure

Categories are organized using parent-child relationships via `category_id` and `parent_id`:
- **Root Category (ID: 1)**: System root category (not visible)
- **Default Category (ID: 2)**: Main category for the store
- **Custom Categories**: Your actual category structure

### Hierarchy Rules

1. **Parent ID**: Must reference an existing category ID
2. **Default Parent**: If `parent_id` is empty, defaults to category ID 2 (Default Category)
3. **URL Key Format**: Auto-generated from name if not provided (lowercase, hyphens for spaces)
4. **Uniqueness**: Each `url_key` must be unique within the same parent category

### Hierarchy Examples

```csv
category_id,parent_id,_store,name,url_key
,2,,Clothing,clothing
,5,,Men's Clothing,mens-clothing
,5,,Women's Clothing,womens-clothing
,6,,Shirts,shirts
,6,,Pants,pants
,7,,Dresses,dresses
```

!!! note "Import Order"
    In this example, category 5 (Clothing), 6 (Men's Clothing), and 7 (Women's Clothing) would be created first, then their subcategories.

## Attribute Handling

### Boolean Attributes

Boolean attributes accept the following values:
- `1`, `yes`, `true` → True
- `0`, `no`, `false` → False
- Empty → Uses default value

### EAV Attributes

The system automatically handles:
- **Static attributes**: Stored directly in the category table
- **EAV attributes**: Stored in EAV tables with proper store scope
- **Required attributes**: Validates that required fields are present

### Custom Attributes

You can include custom category attributes by adding them as columns:

```csv
category_path,_store,name,custom_field,special_attribute
electronics,,Electronics,custom_value,special_value
```

## Error Handling

### Common Errors

1. **Missing category_path**: Required for creating/updating categories
2. **Invalid parent**: Parent category doesn't exist
3. **Duplicate paths**: Same path used multiple times in same scope
4. **Invalid store code**: Store view doesn't exist
5. **Missing required attributes**: Required fields are empty
6. **Invalid data types**: Wrong format for boolean/integer fields

### Error Messages

Errors are displayed in the admin panel after import with:
- **Error description**: What went wrong
- **Row numbers**: Which rows in your CSV have errors
- **Suggested fixes**: How to correct the issues

### Validation

The system validates:
- ✅ CSV format and structure
- ✅ Required fields presence
- ✅ Data type correctness
- ✅ Store scope validity
- ✅ Category path format
- ✅ Parent-child relationships

## Best Practices

### File Preparation

1. **Use UTF-8 encoding** for proper Unicode character support
2. **Test with small files** before importing large datasets
3. **Backup your database** before major imports
4. **Validate CSV format** using a spreadsheet application

### Import Strategy

1. **Import hierarchy top-down**: Create parent categories before children
2. **Use consistent naming**: Follow your URL key conventions
3. **Handle store scopes carefully**: Understand global vs. store-specific data
4. **Monitor import results**: Check for errors and validate imported data

### Performance Tips

1. **Batch imports**: Split large files into smaller chunks (500-1000 rows)
2. **Avoid frequent updates**: Batch multiple changes together
3. **Use appropriate behavior**: Choose the right import behavior for your needs
4. **Clear cache**: Clear cache after imports to see changes

### Data Integrity

1. **Consistent paths**: Use the same path format throughout your CSV
2. **Proper escaping**: Escape commas and quotes in text fields
3. **Complete data**: Include all required attributes
4. **Logical hierarchy**: Ensure category relationships make sense

## Examples

### Example 1: Basic Category Structure

```csv
category_id,parent_id,_store,name,url_key,is_active,include_in_menu,description
,2,,Electronics,electronics,1,1,Electronic products and gadgets
,2,,Home & Garden,home-garden,1,1,Home and garden products
,5,,Computers,computers,1,1,Desktop and laptop computers
,5,,Mobile Phones,phones,1,1,Smartphones and mobile devices
,7,,Laptops,laptops,1,1,Portable laptop computers
,7,,Desktop Computers,desktops,1,1,Desktop computer systems
,6,,Furniture,furniture,1,1,Home furniture and decor
,6,,Garden Tools,tools,1,1,Tools for gardening
```

!!! note "Creation Order"
    Electronics (ID 5), Home & Garden (ID 6), and Computers (ID 7) would be created first in this example.

### Example 2: Multi-Store Categories

```csv
category_id,parent_id,_store,name,description
8,2,,Electronics,Electronic products
8,2,french,Électronique,Produits électroniques
8,2,spanish,Electrónicos,Productos electrónicos
15,8,,Mobile Phones,Smartphones and mobile devices
15,8,french,Téléphones,Smartphones et appareils mobiles
15,8,spanish,Teléfonos,Teléfonos inteligentes y dispositivos móviles
```

### Example 3: Updating Existing Categories

```csv
category_id,parent_id,_store,name,description,meta_title
8,2,,Electronics,Updated: Latest electronic products,Electronics - Buy Online
15,8,,Smartphones,Updated: Latest smartphone models,Smartphones - Best Deals
```

### Example 4: Advanced Attributes

```csv
category_id,parent_id,_store,name,display_mode,custom_design,page_layout,position
8,2,,Electronics,PRODUCTS,,two_columns_left,10
25,8,,Featured Electronics,PRODUCTS_AND_PAGE,default/modern,three_columns,5
```

### Example 5: Category Deletion

Set Import Behavior to "Delete Entities":

```csv
category_id,parent_id,_store
45,,
67,,
89,,
```

## Troubleshooting

### Common Issues

**Q: Categories not appearing in frontend menu**  
A: Check that `include_in_menu` is set to `1` and `is_active` is set to `1`

**Q: Multi-store data not working**  
A: Ensure store codes are correct and match your store view configuration

**Q: Hierarchy not created properly**  
A: Import parent categories before child categories, or include all hierarchy levels

**Q: Special characters not displaying correctly**  
A: Ensure your CSV file is saved with UTF-8 encoding

**Q: Boolean values showing as text**  
A: Use `1`/`0` instead of `yes`/`no` or `true`/`false`

**Q: Do both import behaviors (Append and Replace) work the same for categories?**  
A: Yes, both "Append Complex Data" and "Replace Existing Complex Data" work identically for categories. They update existing categories and create new ones without deleting any data.

**Q: How do I specify the parent category for new categories?**  
A: Use the `parent_id` column to specify the parent category ID. If omitted, new categories will be created under the Default Category (ID: 2).

**Q: Can I import exported CSV files directly?**  
A: Yes, exported CSV files can be imported directly. The export uses human-readable labels (like "Products only" for display_mode) which the import automatically converts to database values.

**Q: What's the difference between category_id and parent_id?**  
A: `category_id` identifies the category being updated (leave empty for new categories), while `parent_id` specifies where new categories should be created in the hierarchy.