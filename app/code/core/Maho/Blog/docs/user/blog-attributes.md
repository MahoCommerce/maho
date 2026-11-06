---
description: How to add a field to blog posts and blog categories - blog attributes, blog attribute sets, the set of each post and category, the storefront, the import and the API.
admin_pages: [eav_attribute, eav_set, blog_post, blog_category]
---

# Blog Attributes and Attribute Sets

A blog attribute is a field of a blog post or a blog category, for example a reading time or an
author. An attribute set is a list of attributes in groups. Each post and each category uses one
set. The fields of the blog itself, such as **Title**, **Image** and **Content**, are not
attributes, so the attribute grid and the sets show only the attributes that you add.

## Add an attribute

1. Go to **System > Attributes > Blog Posts**, or to **Blog Categories** for a category field.
2. Click **Add**.
3. Enter the **Attribute Code** and select the **Input Type**. You cannot change them later.
4. On the **Manage Label / Options** tab, enter the label, and the options of a Dropdown or a
   Multiple Select.
5. Click **Save**.

A blog attribute has one value for all store views.

A new attribute goes into the first group of the **Default** set. The edit page of a post shows
it at once, on the **Content** tab, below the fields of the blog. On a category, it shows on the
**General** tab.

## Move the attribute in a set

1. Go to **System > Attributes > Blog Posts**, or to **Blog Categories**, and click
   **Attribute Sets**.
2. Click the set, for example **Default**.
3. Drag the attribute to another group, or from **Unassigned Attributes** to a group. To add a
   group, click **Add** above the groups.
4. Click **Save**.

The attributes of the first group of the set show on the **Content** tab of a post and on the
**General** tab of a category. Each other group that holds an attribute is a tab of its own, with
the name of the group.

When there is more than one set, a new post or category shows the **Attribute Set** field. Select
the set before the first save. The admin does not change the set after the first save.

Every night, the store deletes the value of an attribute that is not in the set of its post or
category. So the value of an attribute shows and stays only when the attribute is in the set.

## Show the attribute on the storefront

Set **Visible on Frontend** to **Yes** in the **Frontend Properties** of the attribute. The post
page or the category page then shows the attribute and its value in a table. A post with no value
for the attribute does not show it.

Each group of the set is a table of its own, in the order of the groups. The table of the first
group has the title **Additional Information**. Each other table has the name of its group in the
store view. In each table, the attributes follow the order of the group. To change the order,
drag the attribute in the set.

## Name a group for each store view

1. On the set page, click a group and click **Rename**, or click **Add** to make a group.
2. Enter the name in **Admin**. The admin uses it, and it must be unique in the set.
3. To show another name in a store view, enter it in the row of that store view. A store view with
   no name shows the Admin name.
4. Click **Save** in the window, then click **Save** on the set page.

The table shows HTML when **Allow HTML Tags on Frontend** or **Enable WYSIWYG** is **Yes**. It removes unsafe tags and attributes. The store runs template directives, such as a store URL, only when both are **Yes**. In other cases, it removes them.

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
  Dropdown. A new record must get each required attribute. A Dropdown is an option ID, a Multiple
  Select a list of option IDs, and a Yes/No `true` or `false`.

`GET /api/rest/v2/custom-attributes?entityType=blog_post` (or `blog_category`) lists the attributes, their options and their sets.
