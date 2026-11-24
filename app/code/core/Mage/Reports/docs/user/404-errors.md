---
description: The 404 errors reports of Reports > Explorer list the storefront addresses that answered "404 Not Found", with the number of hits, the dates and the last referer, and open a redirect for each one.
admin_pages: [report_explorer]
---

# 404 Errors Report

The store records each storefront address that answers "404 Not Found". The **404 errors** reports
of **Reports > Explorer** show these addresses. Use them to find broken links and the pages that
send visitors to them. Then redirect each address to a page that exists.

The store does not need the visitor log for this. It counts every visitor, also search engine
crawlers and other bots.

## Run a report

1. Go to **Reports > Explorer**.
2. In the **Report** menu, under **404 errors**, select a report.

| Report | What it shows |
|--------|---------------|
| Missing pages | The addresses that have no redirect, with the most hits first |
| Pages that link to missing pages | The last referer of the addresses that have no redirect |
| 404 errors by store view | The hits and the addresses of each store view |

In **Data**, select **404 errors** to build a report of your own. The other controls of the page
work as in every report. See [Report Explorer](report-explorer.md).

The store keeps one row for each address and store view, with the total number of hits. It does
not keep the hits of each day. So the 404 errors reports have no period and no comparison. To keep
only the recent addresses, add a filter on **Last seen**.

## Fields

| Field | What it shows |
|-------|---------------|
| Address | The path of the address, without the domain and the query string, in lower case |
| Store view | The store view that answered 404 |
| Hits | The number of times that the address answered 404 |
| Last referer | The last page that linked to the address, if the browser sent it |
| First seen, Last seen | The first and the last time that the address answered 404 |
| Redirects to | The target path of the URL rewrite that redirects this address |
| Redirected | Yes when a URL rewrite redirects the address |

## Redirect an address

1. Click **Show the records** above the chart. The list of the addresses of the report opens.
   To list the addresses of one row only, click the row and then **Show the records**.
2. Click the address. The store opens **Catalog > URL Rewrite Management** in a new tab, with a
   new custom rewrite. **Store**, **ID Path** and **Request Path** have the values of the
   address, and **Redirect** is **Permanent (301)**.
3. Enter the **Target Path**, for example `women/dresses.html`.
4. Click **Save**.

After the save, the **Redirects to** field shows the target path, and the address is not a link
any more. The address does not answer 404 again, so its hits stop. See
[URL Rewrites](url-rewrites.md) for the fields of a rewrite.

The address is a link only for an administrator who can open **Catalog > URL Rewrite Management**.

A store view cannot have two URL rewrites with the same request path. When the store view already
has a URL rewrite with this request path, the address is not a link. This is also true when that
rewrite does not redirect, such as the rewrite of a disabled product. Find that rewrite in
**Catalog > URL Rewrite Management**.

## What the store does not count

- A 404 of a file that the web server answers itself, such as a missing image, CSS file or
  JavaScript file. The request never reaches the store.
- The answer "410 Gone" of a deleted product (see [URL Rewrites](url-rewrites.md)).
- A 404 of the admin, of the API and of the `.well-known` paths.
- An address longer than 255 characters. A URL rewrite cannot hold it.
- The query string. `/old-page?utm_source=mail` and `/old-page` are one row.

## Settings

The settings are in **System > Configuration > General > Reports > 404 Errors**.

| Setting | What it does |
|---------|--------------|
| Record 404 Errors | Turns the recording on or off. With No, the store records no new rows |
| Pages Recorded per Hour per Visitor | Above this number, a visitor still gets the 404 page, but the store does not count it. This stops a scanner from filling the report. 0 means no limit |
| Delete Addresses Not Seen For (days) | Every hour, the store deletes the addresses that did not answer 404 in this number of days. 0 keeps them |
| Maximum Addresses | Every hour, the store keeps only this number of addresses, the ones seen last. 0 means no limit |

The report needs the **Reports > 404 Errors** permission of the role.
