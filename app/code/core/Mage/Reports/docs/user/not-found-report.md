---
description: The Not Found report lists the storefront addresses that answered "404 Not Found", with the number of hits, the dates and the last referer, and opens a redirect for each one.
admin_pages: [report_notfound]
---

# Not Found Report

The **Reports > Not Found** report lists each storefront address that answered "404 Not Found".
Use it to find broken links and the pages that send visitors to them. Then redirect each address
to a page that exists.

The report does not need the visitor log. It counts every visitor, also search engine crawlers
and other bots.

## What the report shows

| Column | What it shows |
|--------|---------------|
| Address | The path of the address, without the domain and the query string, in lower case |
| Store View | The store view that answered 404 |
| Hits | The number of times that the address answered 404 |
| Last Referer | The last page that linked to the address, if the browser sent it |
| First Seen, Last Seen | The first and the last time that the address answered 404 |
| Redirects To | The target path of the URL rewrite that redirects this address |

The report shows the addresses with the most hits first. **Export** saves the report as a CSV file.

## Redirect an address

1. Click **Redirect** in the row of the address.
2. The store opens **Catalog > URL Rewrite Management** with a new custom rewrite. **Store**,
   **ID Path** and **Request Path** have the values of the address, and **Redirect** is
   **Permanent (301)**.
3. Enter the **Target Path**, for example `women/dresses.html`.
4. Click **Save**.

After the save, the **Redirects To** column shows the target path, and the **Redirect** link goes
away. The address does not answer 404 again, so its hits stop. See [URL Rewrites](url-rewrites.md)
for the fields of a rewrite.

A store view cannot have two URL rewrites with the same request path. When the store view already
has a rewrite for the address that does not redirect, such as the rewrite of a disabled product,
the row has no **Redirect** link. Find that rewrite in **Catalog > URL Rewrite Management**.

To delete rows, select them, choose **Delete** in **Actions** and click **Submit**. An address
that answers 404 again gets a new row.

## What the report does not count

- A 404 of a file that the web server answers itself, such as a missing image, CSS file or
  JavaScript file. The request never reaches the store.
- The answer "410 Gone" of a deleted product (see [URL Rewrites](url-rewrites.md)).
- A 404 of the admin, of the API and of the `.well-known` paths.
- An address longer than 255 characters. A URL rewrite cannot hold it.
- The query string. `/old-page?utm_source=mail` and `/old-page` are one row.

## Settings

The settings are in **System > Configuration > General > Reports > Not Found Report**.

| Setting | What it does |
|---------|--------------|
| Record Pages Not Found | Turns the report on or off. With No, the store records no new rows |
| Pages Recorded per Hour per Visitor | Above this number, a visitor still gets the 404 page, but the report does not count it. This stops a scanner from filling the report. 0 means no limit |
| Delete Addresses Not Seen For (days) | Every hour, the store deletes the addresses that did not answer 404 in this number of days. 0 keeps them |
| Maximum Addresses | Every hour, the store keeps only this number of addresses, the ones seen last. 0 means no limit |
