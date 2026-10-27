---
description: Why a CMS page or static block does not show in a store view - the Store View field, All Store Views, two pages with the same URL key, the status, and the home page setting per store view.
admin_pages: [cms_page, cms_block]
---

# CMS Pages and Blocks in Store Views

A CMS page or a static block shows in a store view only when two things are true:

1. Its **Store View** field contains that store view, or **All Store Views**.
2. Its **Status** is Enabled.

A page or a block has no values per store view. To have an Italian version of a page, create a
second page and assign it to the Italian store view.

## The Store View field

Go to **CMS > Pages** (or **CMS > Static Blocks**) and open the page or the block.

| Field | What it does |
|-------|--------------|
| Store View | The store views where the page or the block can show. You can select more than one. **All Store Views** means every store view, also the ones that you create later |
| Status | **Enabled** or **Disabled**. A disabled page or block shows nowhere |
| URL Key (pages) | The path of the page after the base URL of the store, for example `about-us` |
| Identifier (blocks) | The name that the store, a widget or a template uses to find the block |

When the installation has only one store view, the **Store View** field does not show.

## Which page wins

Two pages can have the same URL key. When a customer opens that URL in a store view, the store picks
the page in this order:

1. An enabled page with this URL key that is assigned to this store view.
2. An enabled page with this URL key that is assigned to **All Store Views**.
3. If there is none, the customer gets the "page not found" page.

So you can have one page for **All Store Views** and a second page with the same URL key for one
store view. The second page replaces the first one in that store view only. If you disable the
second page, the store view shows the **All Store Views** page again.

So before you change a page that does not show in a store view, look for the other pages with the
same URL key: in **CMS > Pages**, filter the **URL Key** column. A page of that store view hides the
page that you opened, also when you enable it.

The same order applies to static blocks with the same identifier.

When a URL rewrite in **Catalog > URL Rewrite Management** has the same path in the store view, the
rewrite wins over the CMS page.

## Rules for the URL key

The admin refuses to save a page when one of these rules fails:

| Rule | Error message |
|------|---------------|
| No other page with the same URL key has one of the same store views. **All Store Views** counts as its own choice | A page URL key for specified store already exists. |
| The URL key uses only lower case letters, numbers, `-`, `_` and `/`, with one optional extension such as `.html` | The page URL key contains capital letters or disallowed symbols. |
| The URL key is not only numbers | The page URL key cannot consist only of numbers. |

When the **URL Key** is empty, the store creates one from the **Page Title**.

For static blocks, the rule is the same: no other block with the same identifier can have one of
the same store views. The error message is "A block identifier with the same properties already
exists in the selected store."

## The home page per store view

The home page is a CMS page. Go to **System > Configuration > General > Web > Default Pages** and
look at **CMS Home Page**. The same group has **CMS No Route Page**, the page for "page not found".

1. In **Current Configuration Scope**, select the store view.
2. Clear **Use Website** (or **Use Default**) next to **CMS Home Page**.
3. Select the page and save.

The setting stores the URL key of the page, not the page itself. In each store view, the store then
shows the page with that URL key, with the order in [Which page wins](#which-page-wins). So if two
pages have the URL key `home`, one for **All Store Views** and one for the Italian store view, the
Italian store view shows its own page, even if you selected the other one in the dropdown.

If no enabled page with that URL key is assigned to the store view or to **All Store Views**, the
home page shows the text "There was no Home CMS page configured or found." with a "404 Not Found"
status.

The **CMS No Route Page** has no URL of its own. Its URL key, for example `no-route`, shows the
page with a "404 Not Found" status, as any other URL that does not exist. The XML sitemap does not
list it.

The admin keeps a page that a **CMS Home Page** or **CMS No Route Page** setting uses. You cannot
delete it, and when you disable it, the store keeps it enabled and shows a warning.

This protection does not always work. When two pages have the same URL key, the setting can store
the URL key with the page ID, for example `home|5`. The admin then does not find the page, and lets
you disable or delete it. Before you disable or delete a page, check these two settings in each
scope.

## Static blocks on a page

A static block shows where a template, a widget or a CMS page places it. In each store view, the
store looks for the block with the order in [Which page wins](#which-page-wins): first a block for
that store view, then a block for **All Store Views**. When it finds no enabled block, the place
stays empty. The store shows no error.

## Troubleshooting

| What you see | Why | What to do |
|--------------|-----|------------|
| The page is invisible in the Italian store view, and another page with the same URL key is assigned to the Italian store view | That page wins in the Italian store view | Edit that page, or remove the Italian store view from it |
| The page is invisible in the Italian store view | The **Store View** field does not contain the Italian store view or **All Store Views** | Add the Italian store view to the field, or select **All Store Views** |
| The page is invisible in every store view | The **Status** is Disabled | Set **Status** to Enabled |
| The Italian store view shows the English page | The Italian page is disabled, or is not assigned to the Italian store view, so the **All Store Views** page shows | Enable the Italian page and check its **Store View** field |
| "A page URL key for specified store already exists." | Another page with the same URL key has one of the same store views | Remove the store view from one of the two pages, or change one URL key |
| The URL of the page opens another page | A URL rewrite uses the same path in that store view | Remove or change the rewrite in **Catalog > URL Rewrite Management** |
| The home page of a store view shows "There was no Home CMS page configured or found." | No enabled page with the URL key of **CMS Home Page** is assigned to that store view or to **All Store Views** | Assign the page to the store view, or select another page for that store view |
| The home page shows another page than the one selected | The setting keeps the URL key only, and a page for that store view with the same URL key wins | Give the pages different URL keys, or assign only one of them to the store view |
| You cannot disable or delete a page | A **CMS Home Page** or **CMS No Route Page** setting uses it | Select another page in that setting first |
| A static block is missing in one store view | The block is not assigned to that store view or to **All Store Views**, or it is disabled | Check the **Store View** and **Status** fields of the block |

## Related

- [Scope](scope.md) for store view values in the configuration.
- [URL Rewrites](url-rewrites.md) for rewrites that can take the path of a page.
