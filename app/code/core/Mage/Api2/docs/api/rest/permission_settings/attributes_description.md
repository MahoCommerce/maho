---
description: Reference of the REST attributes available in the Maho admin panel for orders, stock items, customers, and products.
---

# REST Attributes Description

!!! warning "Legacy API"
    This is Maho's legacy REST API, inherited from Magento 1 and kept for backward compatibility. New integrations should use the modern [REST & GraphQL API (v2)](../../v2/index.md) instead.

The following table describes REST attributes that can be managed in the Maho Admin Panel.  
To access these attributes, go to **System > Web Services > REST Attributes** and select the type of the user for which attributes will be managed.

## Order/Orders

| Attribute Name | Attribute Description | Notes |
| --- | --- | --- |
| Order ID | Sales order ID |     |
| Order Date | Date when the sales order was placed |     |
| Order Status | Sales order status. Can have the following values: Pending, Processing, Complete, Closed, Holded, Pending PayPal, and Payment Review. |     |
| Shipping Method | Shipping method selected during the checkout process (e.g., Flat rate - Fixed) |     |
| Payment Method | Payment method selected during the checkout process (e.g., Check/money order) |     |
| Base Currency | Base currency code (e.g., USD) |     |
| Order Currency | Order currency code (e.g., EUR) |     |
| Store Name | Name of the store from which the order was placed |     |
| Placed from IP | IP address from which the order was placed |     |
| Store Currency to Base Currency Rate | Store currency to base currency rate |     |
| Subtotal | Subtotal amount in order currency (excluding shipping and tax) |     |
| Subtotal Including Tax | Subtotal amount including tax (in order currency) |     |
| Discount | Discount amount applied in the sales order in order currency |     |
| Grand Total to Be Charged | Total amount of money to be paid for the order in base currency (including tax) |     |
| Grand Total | Grand total amount in order currency (including tax and shipping) |     |
| Shipping | Shipping amount applied in the sales order in order currency |     |
| Shipping Including Tax | Shipping amount including tax (in order currency) |     |
| Shipping Tax | Tax amount for shipping in order currency |     |
| Tax Amount | Tax amount applied in the sales order in order currency |     |
| Tax Name | Name of the applied tax |     |
| Tax Rate | Tax rate applied in the order (in order currency) |     |
| Gift Cards Amount | Gift card pricing amount | This attribute is available only in Magento EE |
| Reward Points Balance | Reward points amount (that can be converted to currency) | This attribute is available only in Magento EE |
| Reward Currency Amount | Reward currency amount | This attribute is available only in Magento EE |
| Coupon Code | Coupon code that was applied in the order |     |
| Base Discount | Amount of applied discount in base currency |     |
| Base Subtotal | Subtotal amount for all products in the order in base currency (excluding tax and shipping) |     |
| Base Shipping | Amount of money to be paid for shipping in base currency |     |
| Base Shipping Tax | Tax amount for shipping in base currency |     |
| Base Tax Amount | Tax amount applied to the order items in base currency |     |
| Total Paid | Total amount paid for the order (in order currency) |     |
| Base Total Paid | Total amount paid for the order (in base currency) |     |
| Total Refunded | Total refunded amount in order currency |     |
| Base Total Refunded | Total amount refunded for the order (in base currency) |     |
| Base Subtotal Including Tax | Subtotal amount including tax but excluding the discount amount (in base currency) |     |
| Base Total Due | The rest of the money to be paid for the order in base currency (e.g., when partial invoice is applied) |     |
| Total Due | The rest of the money to be paid for the order in order currency (e.g., when partial invoice is applied) |     |
| Shipping Discount | Discount amount for shipping (in order currency) |     |
| Base Shipping Discount | Discount amount for shipping (in base currency) |     |
| Discount Description | Discount code (coupon code applied in the order) |     |
| Customer Balance | Customer balance (in order currency) |     |
| Base Customer Balance | Customer balance (in base currency) |     |
| Base Gift Cards Amount | Gift card pricing amount (in base currency) | This attribute is available only in Magento EE |
| Base Rewards Currency | Reward currency amount (in base currency) | This attribute is available only in Magento EE |

### Order Addresses

| Attribute Name | Attribute Description |
| --- | --- |
| Customer Last Name | Customer last name |
| Customer First Name | Customer first name |
| Customer Middle Name | Customer middle name or initial |
| Customer Prefix | Customer prefix |
| Customer Suffix | Customer suffix |
| Company | Company name |
| Street | Street address |
| City | City |
| State | State |
| ZIP/Postal Code | ZIP or postal code |
| Country | Country name |
| Phone Number | Customer phone number |
| Address Type | Address type. Can have the following values: billing or shipping |

### Order Items

| Attribute Name | Attribute Description |
| --- | --- |
| Base Discount Amount | Discount amount applied to the row in base currency |
| Base Item Subtotal | Row subtotal in base currency |
| Base Item Subtotal Including tax | Row subtotal including tax in base currency |
| Base Original Price | Original item price in base currency |
| Base Price | Item price in base currency |
| Base Price Including tax | Item price including tax in base currency |
| Base Tax Amount | Tax amount applied to the row in base currency |
| Canceled Qty | Number of canceled order items |
| Discount Amount | Discount amount applied to the row in order currency |
| Invoiced Qty | Number of invoiced order items |
| Item Subtotal | Row subtotal in order currency |
| Item Subtotal Including Tax | Row subtotal including tax in order currency |
| Order Item ID | Order item ID |
| Ordered Qty | Number of ordered items |
| Original Price | Original item price in order currency |
| Parent Order Item ID | ID of the configurable product to which the simple product is assigned |
| Price | Item price in order currency |
| Price Including Tax | Item price including tax in order currency |
| Product and Custom Options Name | Name of the product (custom options name) |
| Refunded Qty | Number of refunded order items |
| SKU | Product SKU |
| Shipped Qty | Number of shipped order items |
| Tax Amount | Tax amount applied to the row in order currency |
| Tax Percent | Tax percent applied to the row |

## Stock Item

| Attribute Name | Attribute Description |
| --- | --- |
| Automatically Return Credit Memo Item to Stock | Defines whether products can be automatically returned to stock when the refund for an order is created |
| Backorders | Defines whether the customer can place the order for products that are out of stock at the moment. Can have the following values: 0 - No Backorders, 1 - Allow Qty Below 0, and 2 - Allow Qty Below 0 and Notify Customer |
| Can Be Divided into Multiple Boxes for Shipping | Defines whether the stock items can be divided into multiple boxes for shipping |
| Enable Qty Increments | Defines whether the customer can add products only in increments to the shopping cart |
| Item ID | Stock item ID |
| Low Stock Date | Date when the number of stock items became lower than the number defined in the Notify for Quantity Below option |
| Manage Stock | Choose whether to view and specify the product quantity and availability and whether the product is in stock management. Can have the following values: 0 - No, 1 - Yes |
| Maximum Qty Allowed in Shopping Cart | Maximum number of items in the shopping cart to be sold |
| Minimum Qty Allowed in Shopping Cart | Minimum number of items in the shopping cart to be sold |
| Notify for Quantity Below | The number of inventory items below which the customer will be notified via the RSS feed |
| Product ID | Product ID |
| Qty | Quantity of stock items for the current product |
| Qty Increments | The product quantity increment value |
| Qty Uses Decimals | Choose whether the product can be sold using decimals (e.g., you can buy 2.5 product) |
| Qty for Item's Status to Become Out of Stock | Quantity for stock items to become out of stock |
| Stock Availability | Defines whether the product is available for selling. Can have the following values: 0 - Out of Stock, 1 - In Stock |
| Stock ID | Stock ID |
| Use Config Settings for Backorders | Choose whether the Config settings will be applied for the Backorders option |
| Use Config Settings for Enable Qty Increments | Choose whether the Config settings will be applied for the Enable Qty Increments option |
| Use Config Settings for Manage Stock | Choose whether the Config settings will be applied for the Manage Stock option |
| Use Config Settings for Maximum Qty Allowed in Shopping Cart | Choose whether the Config settings will be applied for the Maximum Qty Allowed in Shopping Cart option |
| Use Config Settings for Minimum Qty Allowed in Shopping Cart | Choose whether the Config settings will be applied for the Minimum Qty Allowed in Shopping Cart option |
| Use Config Settings for Notify for Quantity Below | Choose whether the Config settings will be applied for the Notify for Quantity Below option |
| Use Config Settings for Qty Increments | Choose whether the Config settings will be applied for the Qty Increments option |
| Use Config Settings for Qty for Item's Status to Become Out of Stock | Choose whether the Config settings will be applied for the Qty for Item's Status to Become Out of Stock option |

**Notes**: The Admin user type has restrictions concerning the WRITE operations for definite stock item attributes. These are as follows:

| Attribute Name | Admin |
| --- | --- |
| Item ID | No  |
| Product ID | No  |
| Stock ID | No  |
| Low Stock Date | No  |

However, these attributes are available for READ operations.

## Customer

| Attribute Name | Attribute Description |
| --- | --- |
| Customer ID | Customer ID |
| Last Logged In | Date when the customer was logged in last |
| Is Confirmed | Defines whether the email confirmation is sent to the customer |
| Created At | Date when the customer was created |
| Associate to Website | Website ID to which the customer is associated |
| Created From | Store view from which the customer was created |
| Group | Customer group ID |
| Disable automatic group change | Defines whether the automatic group change will be applied to the customer |
| Prefix | Customer prefix |
| First Name | Customer first name |
| Middle Name/Initial | Customer middle name or initial |
| Last Name | Customer last name |
| Suffix | Customer suffix |
| Email | Customer email address |
| Date Of Birth | Customer date of birth |
| Tax/VAT Number | Customer tax or VAT number |
| Gender | Customer gender (male or female) |

### Customer Address

| Attribute Name | Attribute Description |
| --- | --- |
| City | City name |
| Company | Company name |
| Country | Country |
| Customer Address ID | Customer address ID |
| Fax | Fax number |
| First Name | Customer first name |
| Is Default Billing Address | Defines whether the address is a default one for billing |
| Is Default Shipping Address | Defines whether the address is a default one for shipping |
| Last Name | Customer last name |
| Middle Name/Initial | Customer middle name or initial |
| Prefix | Customer prefix |
| State/Province | Customer state/region |
| Street Address | Customer street address |
| Suffix | Customer suffix |
| Telephone | Customer phone number |
| VAT Number | Customer VAT number |
| ZIP/Postal Code | Customer ZIP or postal code |

## Product

Attributes for the product resource are divided into those available for the Admin type of user and those available for the Customer and Guest types of user.

| Attribute Name | Attribute Description | Notes |
| --- | --- | --- |
| Product ID | Product ID | Available only for Admin |
| name | Product Name |     |
| Product Type | Product type. Can have the following values: Simple, Grouped, Configurable, Virtual, Bundle, or Downloadable |     |
| Attribute Set Name | Name of the attribute set which the product is based on | Available only for Admin |
| sku | Product SKU |     |
| price | Product price |     |
| visibility | Product visibility in the store. Can have the following values: Catalog, Search; Search; Catalog; Not Visible Individually | Available only for Admin |
| description | Product description |     |
| short_description | Product short description |     |
| weight | Product weight | Available only for Admin |
| news_from_date | Date starting from which the product is promoted as a new product | Available only for Admin |
| news_to_date | Date till which the product is promoted as a new product | Available only for Admin |
| status | Product status in the store. Can have the following values: Enabled or Disabled | Available only for Admin |
| url_key | A friendly URL path for the product | Available only for Admin |
| Create Permanent Redirect for Old URL | Defines whether the redirect to an original URL will be applied (when the existing URL for a product is edited) | Available only for Admin; available only for product update |
| country_of_manufacture | Product country of manufacture | Available only for Admin |
| is_returnable | Defines whether the product can be returned | Available only for Admin |
| special_price | Product special price | Available only for Admin |
| special_from_date | Date starting from which the special price will be applied for the product | Available only for Admin |
| special_to_date | Date till which the special price will be applied for the product | Available only for Admin |
| group_price | Product group price | Available only for Admin |
| tier_price | Product tier price |     |
| msrp_enabled | The Apply MAP option. Defines whether the price in the catalog in the frontend is substituted with a Click for price link | Available only for Admin |
| msrp_display_actual_price_type | Defines how the price will be displayed in the frontend. Can have the following values: In Cart, Before Order Confirmation, and On Gesture | Available only for Admin |
| msrp | The Manufacturer's Suggested Retail Price option. The price that a manufacturer suggests to sell the product at | Available only for Admin |
| enable_googlecheckout | Defines whether the product can be purchased with the help of the Google Checkout payment service. Can have the following values: Yes and No | Available only for Admin |
| tax_class_id | The product tax class to which the product will be associated | Available only for Admin |
| meta_title | Product meta title |     |
| meta_keyword | Product meta keywords |     |
| meta_description | Product meta description |     |
| custom_design | Custom design applied for the product page | Available only for Admin |
| custom_design_from | Date starting from which the custom design will be applied for the product page | Available only for Admin |
| custom_design_to | Date till which the custom design will be applied for the product page | Available only for Admin |
| custom_layout_update | An XML block to alter the page layout | Available only for Admin |
| page_layout | Page template that can be applied to the product page | Available only for Admin |
| options_container | Defines how the custom options for the product will be displayed. Can have the following values: Block after Info Column or Product Info Column | Available only for Admin |
| gift_message_available | Defines whether the gift message is available for the product | Available only for Admin |
| Use Config Settings for Allow Gift Message | Defines whether the configuration settings will be used for the Allow Gift Message option | Available only for Admin |
| gift_wrapping_available | Defines whether the gift wrapping is available for the product | Available only for Admin. This attribute is available in Magento EE |
| Use Config Settings for Allow Gift Wrapping | Defines whether the configuration settings will be used for the Allow Gift Wrapping option | Available only for Admin. This attribute is available in Magento EE |
| gift_wrapping_price | Price for the gift wrapping (available in Magento EE) | Available only for Admin |
| Inventory Data | Product inventory data | Available only for Admin |
| Custom attr | Product custom attributes | The customer can see only attributes that are set as visible on frontend |
| Regular Price | The original product price displayed in the frontend | Available only for Customer and Guest |
| Final Price | The final product price | Available only for Customer and Guest |
| Final Price with Tax | The final product price with tax | Available only for Customer and Guest |
| Final Price Without Tax | The final product price without tax | Available only for Customer and Guest |
| Stock Status | The product stock status (availability) | Available only for Customer and Guest |
| Product Is Saleable | Defines whether the product can be sold | Available only for Customer and Guest |
| Total Reviews Number | The number of all reviews for a product | Available only for Customer and Guest |
| Product URL Link | A link to the product without the assigned category | Available only for Customer and Guest |
| Buy Now Link | A link that adds a product to the shopping cart | Available only for Customer and Guest |
| Product Has Custom Options | Defines whether the product has custom options or not | Available only for Customer and Guest |
| Default Product Image | Default product image | Available only for Customer and Guest |

### Product Category

| Attribute Name | Attribute Description |
| --- | --- |
| Category ID | ID of the category to which the product is assigned |

### Product Image

| Attribute Name | Attribute Description | Notes |
| --- | --- | --- |
| Exclude | Defines whether the image will associate only to one of the three image types. |     |
| ID  | Image file ID | Available only for READ operations |
| Label | A label that will be displayed on the frontend when pointing to the image |     |
| Position | The Sort Order option. The order in which the images are displayed in the MORE VIEWS section. |     |
| Type | Image type. The standard value is Main Image. A write that names Small Image or Thumbnail sets Main Image. |     |
| URL | Image file URL path | Available only for READ operations |
| File Content | Image file content (base_64 encoded) | Available only for WRITE operations |
| File MIME Type | File MIME type. Can have the following values: image/jpeg, image/png, image/gif, etc. | Available only for WRITE operations |
| File Name | Image file name | Available only for WRITE operations |
