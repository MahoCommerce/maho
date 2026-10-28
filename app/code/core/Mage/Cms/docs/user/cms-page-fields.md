---
description: How to add a field to CMS pages - page fields, their input types, required and unique values, the Custom Fields tab, the import, the API, and how a theme shows a value.
admin_pages: [cms_page_attribute, cms_page]
---

# CMS Page Fields

A page field is a field that you add to every CMS page, for example a hero image URL, a
campaign code or a review date. The fields of the platform, such as **Title** and **Content**, are
not page fields.

## Add a field

1. Go to **CMS > Page Fields**.
2. Click **Add New Attribute**.
3. Enter the **Attribute Code**. Use only lowercase letters, numbers and underscores, and start
   with a letter. You cannot change the code later.
4. Select the **Input Type**: Text Field, Text Area, Date, Yes/No, Multiple Select or Dropdown. You
   cannot change the input type later.
5. Set **Values Required** to **Yes** if each page must have a value. Set **Unique Value** to
   **Yes** if two pages cannot have the same value.
6. On the **Manage Label / Options** tab, enter the label. For a Dropdown or a Multiple Select,
   add the options.
7. Click **Save**.

The code cannot be the name of a field of the platform, such as `title`, `identifier` or
`content`. It also cannot be `stores` or `store_id`.

## Fill in the field

Go to **CMS > Pages** and open a page. The **Custom Fields** tab shows all the page fields. The tab
does not show when there are no page fields.

A new page gets the **Default Value** of each field. A page field has one value for all the store
views of the page.

## Delete a field

You can delete a page field on its edit page. The pages keep their values in the database, but the
**Custom Fields** tab, the import and the API do not show the field any more.

If you add a field with the same code again, the pages show their old values again. Use the same
input type. A Dropdown or a Multiple Select does not get its old options back, so its old values
do not show.

## Show a value on the storefront

A page field does not show on the storefront by itself. A developer adds it to the theme. In a
template of the `Mage_Cms_Block_Page` block, `$this->getPage()->getData('your_code')` returns the
value. A Dropdown returns the option ID, and a Multiple Select returns the option IDs separated by
commas.

## Import

The CMS page import file can have a column for each page field, with the code of the field as its
name. A Dropdown or a Multiple Select takes the labels of the options. Separate more than one
label with commas. An empty cell keeps the value that the page has.

## The API

The CMS pages of the REST and GraphQL API have the `customAttributes` field: the values of the
page fields, by code. Only an admin and an API token can read and write it. A Dropdown takes the
option ID. A Multiple Select takes a list of option IDs, or the IDs separated by commas.
