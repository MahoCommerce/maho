<?php

/**
 * Snapshot of every readable API field carrying no property-level security gate.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

/*
 * PropertyAuthorizationTest compares the live surface against this file. A
 * property with no `#[ApiProperty(security: ...)]` is visible to every caller
 * the operation-level gates let through (for publicly readable resources, that
 * is anyone), so adding one to a DTO widens the exposed surface by default;
 * this snapshot turns that into a failing test until someone either gates the
 * property or records it here on purpose.
 *
 * Regenerate deliberately, never to silence a failure you have not read.
 */

return [
    'Address' => [
        'addressType', 'city', 'company', 'countryId', 'createdAt', 'customerAddressId', 'customerId',
        'email', 'extensions', 'fax', 'firstname', 'id', 'isDefaultBilling', 'isDefaultShipping',
        'lastname', 'middlename', 'postcode', 'prefix', 'region', 'regionId', 'street', 'suffix',
        'telephone', 'updatedAt', 'vatId',
    ],
    'AttributeSet' => [
        'attributeCodes', 'attributeSetName', 'extensions', 'groups', 'id',
    ],
    'AuthToken' => [
        'apiUser', 'cartId', 'cartItemsQty', 'cartMaskedId', 'customer', 'expiresIn', 'extensions',
        'id', 'message', 'permissions', 'success', 'token', 'tokenType',
    ],
    'BlogCategory' => [
        'createdAt', 'extensions', 'id', 'isActive', 'level', 'metaDescription', 'metaKeywords',
        'metaRobots', 'metaTitle', 'name', 'parentId', 'path', 'position', 'stores', 'updatedAt',
        'urlKey',
    ],
    'BlogPost' => [
        'categoryIds', 'content', 'createdAt', 'excerpt', 'extensions', 'id', 'image', 'imageUrl',
        'isActive', 'metaDescription', 'metaKeywords', 'metaRobots', 'metaTitle', 'publishDate',
        'shortContent', 'status', 'stores', 'title', 'updatedAt', 'urlKey',
    ],
    'BundleOption' => [
        'extensions', 'id', 'position', 'required', 'selections', 'title', 'type',
    ],
    'Cart' => [
        'appliedCoupon', 'appliedGiftcards', 'availablePaymentMethods', 'availableShippingMethods',
        'billingAddress', 'cartRecreated', 'createdAt', 'currency', 'customerEmail', 'customerId',
        'customerNote', 'extensions', 'giftMessage', 'id', 'isActive', 'items', 'itemsCount',
        'itemsQty', 'maskedId', 'prices', 'reservedOrderId', 'selectedPaymentMethod',
        'selectedShippingMethod', 'shippingAddress', 'storeId', 'updatedAt',
    ],
    'Category' => [
        'availableSortBy', 'children', 'childrenCount', 'childrenIds', 'cmsBlock', 'createdAt',
        'customApplyToProducts', 'customDesign', 'customDesignFrom', 'customDesignTo',
        'customLayoutUpdate', 'customUseParentSettings', 'defaultSortBy', 'description', 'displayMode',
        'extensions', 'filterPriceRange', 'id', 'image', 'includeInMenu', 'isActive', 'isAnchor',
        'landingPageId', 'level', 'metaDescription', 'metaKeywords', 'metaRobots', 'metaTitle', 'name',
        'pageLayout', 'parentId', 'path', 'position', 'productCount', 'updatedAt', 'urlKey', 'urlPath',
    ],
    'CmsBlock' => [
        'content', 'createdAt', 'extensions', 'id', 'identifier', 'isActive', 'status', 'stores',
        'title', 'updatedAt',
    ],
    'CmsPage' => [
        'content', 'contentHeading', 'createdAt', 'extensions', 'id', 'identifier', 'isActive',
        'metaDescription', 'metaKeywords', 'metaRobots', 'pageLayout', 'sortOrder', 'status', 'stores',
        'title', 'updatedAt',
    ],
    'ConfigurableSetup' => [
        'childProductIds', 'extensions', 'id', 'superAttributes',
    ],
    'ContactForm' => [
        'captchaProvider', 'captchaSiteKey', 'enabled', 'extensions', 'honeypotField', 'id', 'message',
        'success',
    ],
    'Country' => [
        'availableRegions', 'extensions', 'id', 'iso2Code', 'iso3Code', 'name',
    ],
    'Coupon' => [
        'applyToShipping', 'code', 'createdAt', 'customerGroupIds', 'description', 'discountAmount',
        'discountPreview', 'discountQty', 'discountStep', 'discountType', 'expirationDate',
        'extensions', 'fromDate', 'id', 'isActive', 'isPrimary', 'isValid', 'minimumSubtotal',
        'ruleId', 'ruleName', 'simpleFreeShipping', 'sortOrder', 'stopRulesProcessing', 'timesUsed',
        'toDate', 'type', 'usageLimit', 'usagePerCustomer', 'validationMessage', 'websiteIds',
    ],
    'CreditMemo' => [
        'adjustment', 'adjustmentNegative', 'adjustmentPositive', 'baseAdjustment', 'baseCurrencyCode',
        'baseDiscountAmount', 'baseGrandTotal', 'baseShippingAmount', 'baseSubtotal', 'baseTaxAmount',
        'comment', 'comments', 'createdAt', 'creditmemoStatus', 'currency', 'discountAmount',
        'discountDescription', 'emailSent', 'extensions', 'grandTotal', 'hiddenTaxAmount', 'id',
        'incrementId', 'invoiceId', 'items', 'orderCurrencyCode', 'orderId', 'orderIncrementId',
        'shippingAmount', 'shippingInclTax', 'shippingTaxAmount', 'state', 'storeId', 'subtotal',
        'subtotalInclTax', 'taxAmount', 'transactionId', 'updatedAt',
    ],
    'Currency' => [
        'code', 'exchangeRate', 'extensions', 'symbol',
    ],
    'Customer' => [
        'addresses', 'createdAt', 'createdIn', 'defaultBillingAddress', 'defaultShippingAddress',
        'disableAutoGroupChange', 'dob', 'email', 'extensions', 'firstname', 'fullName', 'gender',
        'groupId', 'id', 'isActive', 'isConfirmed', 'isSubscribed', 'lastname', 'middlename', 'prefix',
        'storeId', 'suffix', 'taxvat', 'updatedAt', 'websiteId',
    ],
    'CustomerGroup' => [
        'code', 'extensions', 'id', 'taxClassId', 'taxClassName',
    ],
    'DownloadableLink' => [
        'extensions', 'id', 'isShareable', 'linkType', 'linkUrl', 'numberOfDownloads', 'price',
        'sampleType', 'sampleUrl', 'sortOrder', 'title',
    ],
    'GiftCard' => [
        'balance', 'code', 'createdAt', 'currencyCode', 'emailScheduledAt', 'emailSentAt', 'expiresAt',
        'extensions', 'history', 'id', 'initialBalance', 'message', 'purchaseOrderId',
        'purchaseOrderItemId', 'recipientEmail', 'recipientName', 'senderEmail', 'senderName',
        'status', 'updatedAt', 'websiteIds',
    ],
    'GroupedProductLink' => [
        'childProductId', 'childProductName', 'childProductSku', 'extensions', 'id', 'position', 'qty',
    ],
    'Invoice' => [
        'baseDiscountAmount', 'baseGrandTotal', 'baseShippingAmount', 'baseSubtotal', 'baseTaxAmount',
        'canVoidFlag', 'comments', 'createdAt', 'currency', 'discountAmount', 'discountDescription',
        'emailSent', 'extensions', 'grandTotal', 'id', 'incrementId', 'isUsedForRefund', 'items',
        'orderId', 'orderIncrementId', 'pdfUrl', 'shippingAmount', 'shippingInclTax', 'state',
        'stateName', 'storeId', 'subtotal', 'subtotalInclTax', 'taxAmount', 'totalQty',
        'transactionId', 'updatedAt',
    ],
    'LayeredFilter' => [
        'code', 'extensions', 'label', 'multiple', 'options', 'position', 'type',
    ],
    'Media' => [
        'dimensions', 'directive', 'extensions', 'filename', 'path', 'size', 'url',
    ],
    'NewsletterSubscription' => [
        'changeStatusAt', 'confirmationRequired', 'customerId', 'email', 'extensions', 'isSubscribed',
        'message', 'status', 'storeId', 'subscriberId',
    ],
    'Order' => [
        'accessToken', 'accountToken', 'appliedRuleIds', 'baseCurrencyCode', 'billingAddress',
        'changeAmount', 'couponCode', 'couponRuleName', 'createdAt', 'currency', 'customerDob',
        'customerEmail', 'customerFirstname', 'customerGender', 'customerGroupId', 'customerId',
        'customerIsGuest', 'customerLastname', 'customerMiddlename', 'customerNote', 'customerPrefix',
        'customerSuffix', 'customerTaxvat', 'discountDescription', 'emailSent', 'extCustomerId',
        'extOrderId', 'extensions', 'giftcardCodes', 'globalCurrencyCode', 'holdBeforeState',
        'holdBeforeStatus', 'id', 'incrementId', 'isVirtual', 'items', 'paymentMethod',
        'paymentMethodTitle', 'prices', 'quoteId', 'shipments', 'shippingAddress',
        'shippingDescription', 'shippingMethod', 'state', 'status', 'statusHistory', 'storeId',
        'storeName', 'totalItemCount', 'totalQtyOrdered', 'updatedAt', 'weight',
    ],
    'Product' => [
        'additionalAttributes', 'attributeSetId', 'averageRating', 'barcode', 'bundleOptions',
        'categoryIds', 'configurableOptions', 'countryOfManufacture', 'createdAt', 'crosssellProducts',
        'currency', 'customOptions', 'description', 'downloadableLinks', 'extensions', 'finalPrice',
        'giftMessageAvailable', 'giftcardAmountCurrency', 'giftcardAmounts', 'giftcardIsMessageAllowed',
        'giftcardMaxAmount', 'giftcardMinAmount', 'giftcardType', 'groupedProducts', 'gtin',
        'hasRequiredOptions', 'id',
        'imageLabel', 'imageUrl', 'isActive', 'linksPurchasedSeparately', 'linksTitle', 'mediaGallery',
        'metaDescription', 'metaKeywords', 'metaRobots', 'metaTitle', 'minimalPrice', 'mpn', 'msrp',
        'msrpDisplayActualPriceType', 'msrpEnabled', 'name', 'newsFromDate', 'newsToDate',
        'optionsContainer', 'pageLayout', 'price', 'priceType', 'priceView', 'relatedProducts',
        'reviewCount', 'samplesTitle', 'shipmentType', 'shortDescription', 'sku', 'skuType',
        'smallImageLabel', 'smallImageUrl', 'specialFromDate', 'specialPrice', 'specialToDate',
        'status', 'stockItem', 'stockQty', 'stockStatus', 'taxClassId', 'thumbnailLabel',
        'thumbnailUrl', 'tierPrices', 'type', 'updatedAt', 'upsellProducts', 'urlKey', 'urlPath',
        'variants', 'visibility', 'websiteIds', 'weight', 'weightType',
    ],
    'ProductAttribute' => [
        'applyTo', 'attributeCode', 'backendType', 'defaultValue', 'extensions', 'frontendClass',
        'frontendInput', 'frontendLabel', 'id', 'isComparable', 'isConfigurable', 'isFilterable',
        'isFilterableInSearch', 'isGlobal', 'isHtmlAllowedOnFront', 'isRequired', 'isSearchable',
        'isUnique', 'isUsedForPriceRules', 'isUserDefined', 'isVisibleInAdvancedSearch',
        'isVisibleOnFront', 'isWysiwygEnabled', 'note', 'options', 'position', 'scope',
        'usedForSortBy', 'usedInProductListing',
    ],
    'ProductCustomOption' => [
        'extensions', 'fileExtensions', 'id', 'imageSizeX', 'imageSizeY', 'maxCharacters', 'price',
        'priceType', 'required', 'sku', 'sortOrder', 'title', 'type', 'values',
    ],
    'ProductGroupPrice' => [
        'customerGroupId', 'extensions', 'id', 'price', 'websiteId',
    ],
    'ProductLink' => [
        'extensions', 'id', 'linkedProductId', 'linkedProductName', 'linkedProductSku', 'position',
    ],
    'ProductMedia' => [
        'disabled', 'extensions', 'file', 'id', 'label', 'position', 'types', 'url',
    ],
    'ProductTierPrice' => [
        'customerGroupId', 'extensions', 'id', 'price', 'qty', 'websiteId',
    ],
    'Review' => [
        'createdAt', 'detail', 'extensions', 'id', 'nickname', 'productId', 'productName', 'rating',
        'ratings', 'status', 'stores', 'title',
    ],
    'RevocationRequest' => [
        'customerName', 'email', 'extensions', 'id', 'orderId', 'orderReference',
        'processedAt', 'processedStatus', 'reason', 'receivedAt', 'storeId', 'suppressedAt',
        'suppressedReason', 'verified',
    ],
    'Shipment' => [
        'comments', 'createdAt', 'emailSent', 'extensions', 'id', 'incrementId', 'items', 'orderId',
        'orderIncrementId', 'packages', 'shipmentStatus', 'storeId', 'totalQty', 'totalWeight',
        'tracks', 'updatedAt',
    ],
    'StockUpdate' => [
        'backorders', 'enableQtyIncrements', 'extensions', 'isInStock', 'isQtyDecimal', 'manageStock',
        'maxSaleQty', 'minQty', 'minSaleQty', 'notifyStockQty', 'previousQty', 'qty', 'qtyIncrements',
        'results', 'sku', 'success', 'useConfigBackorders', 'useConfigEnableQtyInc',
        'useConfigManageStock', 'useConfigMaxSaleQty', 'useConfigMinQty', 'useConfigMinSaleQty',
        'useConfigNotifyStockQty', 'useConfigQtyIncrements',
    ],
    'Store' => [
        'baseLinkUrl', 'baseMediaUrl', 'baseUrl', 'code', 'currency', 'extensions', 'groupId',
        'groupName', 'id', 'isActive', 'locale', 'name', 'rootCategoryId', 'success', 'websiteId',
    ],
    'StoreConfig' => [
        'allowedCountries', 'baseCurrencyCode', 'baseMediaUrl', 'baseUrl', 'cmsHomePage',
        'defaultDescription', 'defaultDisplayCurrencyCode', 'defaultTitle', 'extensions', 'id',
        'isGuestCheckoutAllowed', 'locale', 'logoAlt', 'logoUrl', 'newsletterEnabled',
        'reviewsEnabled', 'storeCode', 'storeName', 'timezone', 'weightUnit', 'wishlistEnabled',
    ],
    'TaxClass' => [
        'className', 'classType', 'extensions', 'id',
    ],
    'TaxRate' => [
        'code', 'extensions', 'id', 'rate', 'taxCountryId', 'taxPostcode', 'taxRegionId', 'titles',
        'zipFrom', 'zipIsRange', 'zipTo',
    ],
    'TaxRule' => [
        'calculateSubtotal', 'code', 'customerTaxClassIds', 'extensions', 'id', 'position', 'priority',
        'productTaxClassIds', 'taxRateIds',
    ],
    'UrlResolveResult' => [
        'extensions', 'id', 'identifier', 'redirectType', 'redirectUrl', 'type',
    ],
    'WishlistItem' => [
        'addedAt', 'description', 'extensions', 'id', 'inStock', 'productFinalPrice', 'productId',
        'productImageUrl', 'productName', 'productPrice', 'productSku', 'productType', 'productUrl',
        'qty',
    ],
];
