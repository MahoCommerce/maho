---
description: How to add a field to categories - category attributes, category attribute sets, the set of each category, and how a field shows on the category page.
admin_pages: [catalog_category_attribute, catalog_category_set, catalog_category]
---

# Category Attributes and Attribute Sets

A category attribute is a field of a category, such as **Name** or **Description**. You can add
your own attributes, for example a brand story or a season. An attribute set is a list of
attributes in groups. Each group is a tab on the category page in the admin. Each category uses
one attribute set.

## Add an attribute

1. Go to **Catalog > Attributes > Manage Category Attributes**.
2. Click **Add New Attribute**.
3. On the **Properties** tab, enter the **Attribute Code**. Use only lowercase letters, numbers and
   underscores, and start with a letter. You cannot change the code later.
4. Select the **Input Type**: Text Field, Text Area, Date, Yes/No, Multiple Select, Dropdown or
   Image. You cannot change the input type later.
5. Select the **Scope**. **Store View** lets each store view have its own value. **Website** gives
   all the store views of a website one value, and each website can have its own value.
   **Global** gives all the websites one value.
6. On the **Manage Label / Options** tab, enter the label. For a Dropdown or a Multiple Select,
   add the options.
7. Click **Save**.

A new attribute is in no set. The category page shows it only after you add it to a set.

You can delete only the attributes that you added. The attributes of the platform show **Yes** in
the **System** column of the grid.

## Put the attribute in a set

1. Go to **Catalog > Attributes > Category Attribute Sets**.
2. Click the set, for example **Default**.
3. Drag the attribute from **Unassigned Attributes** to a group. To make a new tab, click **Add**
   above the groups.
4. Click **Save**.

The groups of the set are the tabs of the category page. A group with no attributes shows no tab.

## Use more than one set

Make a set for each kind of category. For example, a **Brand** set adds a logo and a brand story,
and the **Default** set does not show them.

1. Go to **Catalog > Attributes > Category Attribute Sets** and click **Add**.
2. Enter the name, and select the set in **Based On**. The new set starts with the groups and the
   attributes of that set.
3. Click **Save**, then change the groups and the attributes.

When a store has more than one category set, the first tab of a new category shows the
**Attribute Set** field. A new category starts with the set of its parent. Select another set and
save the category. The tabs of the set show after the save. The admin does not change the set of a
category after the first save, the same as for a product.

Every night, the store deletes the value of an attribute that is not in the set of its category. So
the value of an attribute shows and stays only when the attribute is in the set.

You cannot delete the default set. When you delete another set, its categories move to the default
set.

## Show the attribute on the category page

Set **Visible on Category Page on Front-end** to **Yes** in the **Frontend Properties** of the
attribute. The category page then shows the attribute and its value in a table under the category
description. A category with no value for the attribute does not show it. **Position** sets the
order of the attributes in the table.

The table shows HTML only when **Allow HTML Tags on Frontend** or **Enable WYSIWYG** is **Yes**.
It does not show Image attributes.

## The API

The categories of the REST and GraphQL API have two more fields. Only an admin and an API token can
read them:

- `attributeSetId`: the set of the category. A new category without one takes the set of its
  parent. An update can change the set, as for a product. The next night, the store deletes the
  values of the attributes that are not in the new set.
- `customAttributes`: the values of the attributes that you added, by attribute code. To write
  these values, use `customAttributesWrite`.
