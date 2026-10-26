---
description: GET parameters supported by Maho's legacy REST API - filter, page, order, dir, and limit - with syntax and examples.
---

# GET Filters

!!! warning "Legacy API"
    This is Maho's legacy REST API, inherited from Magento 1 and kept for backward compatibility. New integrations should use the modern [REST & GraphQL API (v2)](../v2/index.md) instead.

Some requests use GET parameters in the URL[^1]. These are as follows:

-   **filter** - specifies the filters for returned data
-   **page** - specifies the page number which items will be returned  
```https://mahohost/api/rest/products?page=1```
-   **order**, **dir** - specifies the sort order of returned items and the order direction: 'asc' - returns items in the ascending order; 'dsc' - returns items in the descending order.  
```https://mahohost/api/rest/products?order=name&dir=dsc```  
```https://mahohost/api/rest/products?order=name&dir=asc```
-   **limit** - limits the number of returned items in the response. Note that by default, 10 items are returned in the response. The maximum number is 100 items.  
```https://mahohost/api/rest/products?limit=2```
-   **neq** - "not equal to" - returns items with the specified attribute that is not equal to the defined value  
```https://mahohost/api/rest/products?filter[1][attribute]=entity_id&filter[1][neq]=3```
-   **in** - "equals any of" - returns items that are equal to the item(s) with the specified attribute(s)  
```https://mahohost/api/rest/products?filter[1][attribute]=entity_id&filter[1][in]=3```
-   **nin** - "not equals any of" - returns items excluding the item with the specified attribute  
```https://mahohost/api/rest/products?filter[1][attribute]=entity_id&filter[1][nin]=3```
-   **gt** - "greater than" - returns items with the specified attribute that is greater than the defined value  
```https://mahohost/api/rest/products?filter[1][attribute]=entity_id&filter[1][gt]=3```  
```https://mahohost/api/rest/products?filter[1][attribute]=price&filter[1][gt]=300```
-   **lt** - "less than" - returns items with the specified attribute that is less than the defined value  
```https://mahohost/api/rest/products?filter[1][attribute]=entity_id&filter[1][lt]=4```
-   **from**, **to** - specifies the range of attributes according to which items will be returned  
```https://mahohost/api/rest/products?filter[1][attribute]=entity_id&filter[1][from]=1&filter[1][to]=3```  
```https://mahohost/api/rest/products?filter[1][attribute]=price&filter[1][from]=150&filter[1][to]=350```

If the attribute value consists of several words separated by a whitespace, the '%20' sign is used:

```https://mahohost/api/rest/products?filter[1][attribute]=name&filter[1][in]=BlackBerry%208100%20Pearl```

For example, to filter products with the description equal to simple01:

```https://mahohost/api/rest/products/?order=entity_id&filter[0][attribute]=description&filter[0][in][0]=simple01```

To filter customers by email address:

```https://mahohost/api/rest/customers?filter[1][attribute]=email&filter[1][in][0]=test@test.com```


[^1]: JSON responses on this page contributed by Tim Reynolds