---
description: How Maho builds product and category URLs from the URL key, why a URL can get a number at the end, what happens to old links when a URL key changes, custom rewrites, and URL keys per store view.
---

# URL Rewrites

A product or category page normally has a readable URL, such as `/women/dresses/summer-dress`. The
store builds this URL from the **URL Key** of the product or category and saves it as a URL
rewrite. When a customer opens the URL, the store looks up the rewrite and shows the page.

The store creates and updates these rewrites itself, when you save a product or a category. You
can see them, and add your own, in **Catalog > URL Rewrite Management**.

## How the store builds a URL

| Page | URL |
|------|-----|
| Category | the URL keys of the parent categories, then the URL key of the category, then the **Category URL Suffix** |
| Product | the URL key of the product, then the **Product URL Suffix** |
| Product in a category | the URL path of the category, then the URL key of the product, then the **Product URL Suffix** |

For example, a category `dresses` under `women`, and a product `summer-dress` in it, give these
URLs: `/women/dresses`, `/summer-dress` and `/women/dresses/summer-dress`.

The root category of the store is not part of the URL.

The settings are in **System > Configuration > Catalog > Catalog > Search Engine Optimizations**:

| Setting | What it does |
|---------|--------------|
| Product URL Suffix | Text at the end of every product URL, for example `.html`. Empty by default |
| Category URL Suffix | Text at the end of every category URL. Empty by default |
| Use Categories Path for Product URLs | When Yes (the default), a product gets one extra URL for each category it is in. When No, a product has only its short URL |
| Create Permanent Redirect for URLs if URL Key Changed | The default of the redirect checkbox on the product and category pages. Default: Yes |
| Purge 410 Gone URL Records After (days) | How long the store remembers the URLs of deleted products (see [Deleted products](#deleted-products)) |

## Where the URL key comes from

1. If the product or category has a **URL Key**, the store uses it.
2. If the URL Key is empty, the store creates one from the name: lower case, with spaces and
   symbols turned into hyphens.

## Why a URL is not the one you chose

The URL of a product must be unique in its store view. When the URL that the store builds is
already in use, by another product, a category or a custom rewrite, the store picks another one:

1. It tries the URL key with the product ID added, for example `summer-dress-42`.
2. If that is also in use, it takes the highest number already in use after the URL key in that
   store view, and adds 1. For a product, the number is above the product ID, for example
   `summer-dress-43`.

For a category, the store goes directly to step 2.

The product link that a customer clicks also depends on the page:

- In a category page, with **Use Categories Path for Product URLs** set to Yes, the link uses the
  URL under that category.
- In other places, such as search results, the link uses the short URL of the product.
- When a product has no rewrite in the store view, the link is the technical address of the
  product page, which contains the product ID. This happens, for example, before the
  **Catalog URL Rewrites** index has run for a new store view.

## What happens to old links when you change a URL key

On the product page and on the category page, the **URL Key** field has a checkbox under it:
**Create Permanent Redirect for old URL**. The checkbox shows only when the URL Key already has a
value. It starts selected when **Create Permanent Redirect for URLs if URL Key Changed** is Yes.

| Checkbox | What happens to the old URL |
|----------|-----------------------------|
| Selected | The store keeps the old URL as a custom rewrite that redirects to the new URL with a permanent (301) redirect. Old links and search engine results keep working |
| Cleared | The store removes the old URL. Old links give a "page not found" error |

When the URL key of a category changes, the store also changes the URLs of its subcategories and of
the products under it, with the same redirect choice.

## Custom rewrites

Go to **Catalog > URL Rewrite Management** and click **Add**. In **Create URL Rewrite**, pick what
the rewrite is for: **For category**, **For product** or **Custom**. For a category or a product,
the store fills in the paths for you.

| Field | What it does |
|-------|--------------|
| Store | The store view of the rewrite. A rewrite works only in its store view |
| Request Path | The URL that the customer opens, without the domain |
| Target Path | Where the store sends the customer |
| Redirect | **No** shows the target page at the request path. **Temporary (302)** and **Permanent (301)** send the browser to the target path |
| Description | A note for the admin users |

The store creates the rewrites of products and categories with the type **System**. The redirects
for old URL keys have the type **Custom**.

A custom rewrite takes its request path. If a product later needs the same URL, the product gets a
URL with a number (see [Why a URL is not the one you chose](#why-a-url-is-not-the-one-you-chose)).

## URL keys per store view

The **URL Key** has the scope **[STORE VIEW]**. Each store view can have its own URL key for the
same product or category, for example `summer-dress` in English and `abito-estivo` in Italian.

1. Open the product or category.
2. Select the store view with the store view switcher at the top of the page.
3. Clear **Use Default Value** next to **URL Key**, and enter the URL key for that store view.
4. Save.

Each store view has its own set of rewrites. A product gets URLs only in the store views of the
websites that it is assigned to, on its **Websites** tab.

## Deleted products

When you delete a product, the store remembers its URLs. A customer who opens one of these URLs
gets the answer "410 Gone" instead of "404 Not Found". This tells search engines that the page
was removed on purpose.

If a new product or a rewrite takes the same URL later, the URL works again as usual. The store
deletes these records after the number of days in **Purge 410 Gone URL Records After (days)**.
With 0, it keeps them forever.

## When the store updates the rewrites

- When you save a product and its URL key, its categories or its websites changed.
- When you save a category and its URL key or its products changed, or when you move it.
- After some changes, the **Catalog URL Rewrites** index needs a reindex: a new store view, a store
  view moved to another store, a new root category or website for a store, and a change of
  **Product URL Suffix**, **Category URL Suffix** or **Use Categories Path for Product URLs**.
  Reindex in **System > Index Management**.

## Troubleshooting

| What you see | Why | What to do |
|--------------|-----|------------|
| The product URL ends with the product ID, such as `summer-dress-42` | Another product, a category or a custom rewrite already uses `summer-dress` in that store view | Find the other URL in **Catalog > URL Rewrite Management** and change one of the two URL keys |
| The product URL ends with a number above the product ID, such as `summer-dress-43`, or the category URL ends with a number, such as `dresses-1` | The same reason: the plain URL key, and for a product the URL key with the ID, are already in use | The same action as above |
| The product URL contains the category path | **Use Categories Path for Product URLs** is Yes and the customer came from a category page | Set the setting to No if you want only short product URLs |
| The URL contains `catalog/product/view/id/` | The product has no rewrite in this store view | Check that the product is assigned to the website, then reindex **Catalog URL Rewrites** |
| An old link gives "page not found" after you changed the URL key | **Create Permanent Redirect for old URL** was cleared when you saved | Add a custom rewrite from the old URL to the new one, with **Redirect** set to **Permanent (301)** |
| An old link of a deleted product gives "410 Gone" | The store remembers the URLs of deleted products | Add a custom rewrite with a redirect to a replacement page, if you have one |
| The Italian store view shows the English URL key | The URL Key in the Italian store view uses the default value | Select the Italian store view, clear **Use Default Value** and enter the Italian URL key |
| A new suffix such as `.html` does not show | The rewrites still use the old setting | Reindex **Catalog URL Rewrites** and flush the cache |

## Related

- [SEO](seo.md) for canonical tags and the other search engine settings.
- [Scope](scope.md) for store view values and **Use Default Value**.
- [CMS Pages and Blocks in Store Views](cms-store-views.md) for a CMS page that has the same path as
  a rewrite.
