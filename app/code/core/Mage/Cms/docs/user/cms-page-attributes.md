---
description: How to add an attribute to CMS pages - page attributes, their input types, required and unique values, the Attributes tab of a page, the import, the API, and how the storefront shows a value.
admin_pages: [eav_attribute, cms_page]
---

# CMS Page Attributes

A page attribute is a field that you add to every CMS page, for example a hero image URL, a
campaign code or a review date. The standard fields, such as **Title** and **Content**, are not
page attributes. CMS pages have no attribute sets: every page has all the page attributes.

## Add an attribute

1. Go to **System > Attributes > CMS Pages**.
2. Click **Add**.
3. Enter the **Attribute Code**. Use only lowercase letters, numbers and underscores, and start
   with a letter. You cannot change the code later.
4. Select the **Input Type**: Text Field, Text Area, Date, Yes/No, Multiple Select or Dropdown. You
   cannot change the input type later.
5. Set **Values Required** to **Yes** if each page must have a value. Set **Unique Value** to
   **Yes** if two pages cannot have the same value.
6. To show the value on the page, set **Visible on Frontend** to **Yes** in **Frontend
   Properties**.
7. On the **Manage Label / Options** tab, enter the label. For a Dropdown or a Multiple Select,
   add the options.
8. Click **Save**.

The code cannot be the name of a standard field, such as `title`, `identifier` or `content`. It also cannot be `stores` or `store_id`.

## Fill in the attribute

Go to **CMS > Pages** and open a page. The **Attributes** tab shows all the page attributes. The
tab does not show when there are no page attributes.

![The Attributes tab of a CMS page, with a text, a dropdown and a date attribute](images/cms-page-attributes-tab.webp)

A new page gets the **Default Value** of each attribute. A page attribute has one value for all the
store views of the page.

## Delete an attribute

You can delete a page attribute on its edit page. The store deletes the attribute and the values
of all the pages for it. You cannot undo this.

If you add an attribute with the same code again, the pages have no value for it.

## Show a value on the storefront

Set **Visible on Frontend** to **Yes** in the **Frontend Properties** of the attribute. The page
then shows the attribute and its value in a table below its content, with the title **Additional
Information**. A page with no value for the attribute does not show it. The attributes show in the
order in which you added them.

![A CMS page with the Additional Information table below its content](images/cms-page-attributes-storefront.webp)

A **Text Area** value shows under the table, with the label of the attribute as its title, because a
long text does not fit in a table.

Set **Allow HTML Tags on Frontend** to **Yes** to keep the HTML of a value. The store removes unsafe
HTML, such as a script. It also removes codes in braces, such as `{{store url=""}}`. With **No**,
the page shows the tags as text.

A theme can also show a value in another place of the page. The
[attribute admin pages](../developer/eav-attribute-admin.md#the-storefront-table) page tells a
developer how.

## Import

The CMS page import file can have a column for each page attribute, with the code of the attribute
as its name. A Dropdown or a Multiple Select takes the labels of the options. Separate more than
one label with commas. An empty cell keeps the value that the page has.

## The API

The CMS pages of the REST and GraphQL API have the `customAttributes` field: the values of the page
attributes, by code. Only an admin and an API token can read and write it. A Dropdown is the
option ID, a Multiple Select a list of option IDs, and a Yes/No `true` or `false`.

`GET /api/rest/v2/custom-attributes?entityType=cms_page` lists the attributes and their options.
