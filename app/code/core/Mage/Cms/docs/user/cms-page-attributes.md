---
description: How to add an attribute to CMS pages - page attributes, their input types, required and unique values, the Attributes tab of a page, the import, the API, and how a theme shows a value.
admin_pages: [eav_attribute, cms_page]
---

# CMS Page Attributes

A page attribute is a field that you add to every CMS page, for example a hero image URL, a
campaign code or a review date. The fields of the platform, such as **Title** and **Content**, are
not page attributes. CMS pages have no attribute sets: every page has all the page attributes.

## Add an attribute

1. Go to **System > Attributes > CMS Pages**.
2. Click **Add**.
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

## Fill in the attribute

Go to **CMS > Pages** and open a page. The **Attributes** tab shows all the page attributes. The
tab does not show when there are no page attributes.

A new page gets the **Default Value** of each attribute. A page attribute has one value for all the
store views of the page.

## Delete an attribute

You can delete a page attribute on its edit page. The pages keep their values in the database, but
the **Attributes** tab, the import and the API do not show the attribute any more.

If you add an attribute with the same code again, the pages show their old values again. Use the
same input type. A Dropdown or a Multiple Select does not get its old options back, so its old
values do not show.

## Show a value on the storefront

A page attribute does not show on the storefront by itself. A developer adds it to the theme. In a
template of the `Mage_Cms_Block_Page` block, `$this->getPage()->getData('your_code')` returns the
value. A Dropdown returns the option ID, and a Multiple Select returns the option IDs separated by
commas.

## Import

The CMS page import file can have a column for each page attribute, with the code of the attribute
as its name. A Dropdown or a Multiple Select takes the labels of the options. Separate more than
one label with commas. An empty cell keeps the value that the page has.

## The API

The CMS pages of the REST and GraphQL API have the `customAttributes` field: the values of the page
attributes, by code. Only an admin and an API token can read and write it. A Dropdown takes the
option ID. A Multiple Select takes a list of option IDs, or the IDs separated by commas.
