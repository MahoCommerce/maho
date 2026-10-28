---
description: How to add a field to blog posts and blog categories - blog attributes, blog attribute sets, the set of each post and category, the storefront, the import and the API.
admin_pages: [blog_post_attribute, blog_post_set, blog_category_attribute, blog_category_set, blog_post, blog_category]
---

# Blog Attributes and Attribute Sets

A blog attribute is a field of a blog post or a blog category, for example a reading time or an
author. An attribute set is a list of attributes in groups. Each post and each category uses one
set. The fields of the blog itself, such as **Title** and **Content**, are not attributes that you
can edit.

## Add an attribute

1. Go to **CMS > Blog > Manage Post Attributes**, or to **Manage Category Attributes** for a
   category field.
2. Click **Add New Attribute**.
3. Enter the **Attribute Code** and select the **Input Type**. You cannot change them later.
4. On the **Manage Label / Options** tab, enter the label, and the options of a Dropdown or a
   Multiple Select.
5. Click **Save**.

A blog attribute has one value for all store views.

## Put the attribute in a set

A new attribute is in no set. The edit page of a post shows it only after you add it to a set.

1. Go to **CMS > Blog > Post Attribute Sets**, or to **Category Attribute Sets**.
2. Click the set, for example **Default**.
3. Drag the attribute from **Unassigned Attributes** to a group. To add a group, click **Add New**.
4. Click **Save Attribute Set**.

Each group of the set that holds one of your attributes is a tab of the edit page of the post or
the category.

When there is more than one set, a new post or category shows the **Attribute Set** field. Select
the set before the first save. The admin does not change the set after the first save.

Every night, the store deletes the value of an attribute that is not in the set of its post or
category. So the value of an attribute shows and stays only when the attribute is in the set.

## Show the attribute on the storefront

Set **Visible on Frontend** to **Yes** in the **Frontend Properties** of the attribute. The post
page or the category page then shows the attribute and its value in a table. A post with no value
for the attribute does not show it. **Position** sets the order of the attributes in the table.

The table shows HTML only when **Allow HTML Tags on Frontend** is **Yes**.

## Import

The blog post import reads two more kinds of columns:

- `attribute_set`: the name of the set of the post.
- A column with the code of an attribute that you added. A Dropdown or a Yes/No takes the label of
  the option. A Multiple Select takes labels with commas between them.

An import refuses a value for an attribute that is not in the set of the post.

## The API

The blog posts and blog categories of the REST and GraphQL API have two more fields. Only an admin
and an API token can read and write them:

- `attributeSetId`: the set of the post or the category. A new one without a set takes the default
  set. An update can change the set. The next night, the store deletes the values of the
  attributes that are not in the new set.
- `customAttributes`: the values of the attributes that you added, by attribute code. A write
  refuses a code that is not in the set of the record, and a value that is not an option of a
  Dropdown.
