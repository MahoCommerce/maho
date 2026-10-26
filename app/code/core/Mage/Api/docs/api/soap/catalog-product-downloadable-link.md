---
description: Add, list, and remove links and samples for downloadable products over the Maho SOAP API.
---

# Catalog Product Downloadable Link

!!! warning "Legacy API"
    This is Maho's legacy SOAP/XML-RPC API, inherited from Magento 1 and kept for backward compatibility. New integrations should use the modern [REST & GraphQL API (v2)](../v2/index.md) instead.

## Introduction

### Resource

- `product_downloadable_link`

### Methods

- `product_downloadable_link.add` - Add a new link to the downloadable product.
- `product_downloadable_link.list` - Get the list of links for a downloadable product.
- `product_downloadable_link.remove` - Remove a link from a downloadable product.

## Add

### Method

- `product_downloadable_link.add` (SOAP V1)
- `catalogProductDownloadableLinkAdd` (SOAP V2)

Allows you to add a new link to a downloadable product.

### Arguments

| Type   | Name           | Description                                                                          |
|--------|----------------|--------------------------------------------------------------------------------------|
| string | sessionId      | Session ID                                                                           |
| string | productId      | Product ID                                                                           |
| array  | resource       | Array of catalogProductDownloadableLinkAddEntity                                     |
| string | resourceType   | Resource type. Can have one of the following values: 'sample' or 'link'.             |
| string | store          | Store view ID or code (optional)                                                     |
| string | identifierType | Type of the product identifier. Can have one of the following values: 'sku' or 'id'. |

### Return

| Type | Name   | Description                                         |
|------|--------|-----------------------------------------------------|
| int  | result | Result of adding a link to the downloadable product |

### Content `catalogProductDownloadableLinkAddEntity`

| Type   | Name                | Description                                                                    |
|--------|---------------------|--------------------------------------------------------------------------------|
| string | title               | Link title                                                                     |
| string | price               | Custom option value row price                                                  |
| int    | is_unlimited        | Defines whether the number of downloads is unlimited                           |
| int    | number_of_downloads | Maximum number of possible downloads                                           |
| int    | is_shareable        | Defines whether the link is shareable                                          |
| array  | sample              | Array of catalogProductDownloadableLinkAddSampleEntity                         |
| string | type                | Type of the data source. Can have one of the following values: 'file' or 'url' |
| array  | file                | Array of catalogProductDownloadableLinkFileEntity                              |
| string | link_url            | Link URL address                                                               |
| string | sample_url          | Sample URL address                                                             |
| int    | sort_order          | Link sort order                                                                |

### Content `catalogProductDownloadableLinkAddSampleEntity`

| Type   | Name | Description                                                                    |
|--------|------|--------------------------------------------------------------------------------|
| string | type | Type of the data source. Can have one of the following values: 'file' or 'url' |
| array  | file | Array of catalogProductDownloadableLinkFileEntity                              |
| string | url  | URL to upload                                                                  |

### Content `catalogProductDownloadableLinkFileEntity`

| Type   | Name           | Description         |
|--------|----------------|---------------------|
| string | name           | File name           |
| string | base64_content | BASE64 encoded file |

### Faults

| Fault Code | Fault Message                                    |
|------------|--------------------------------------------------|
| 414        | Unable to save action. Details in error message. |
| 415        | Validation error has occurred.                   |

### Examples

#### Request Example SOAP V1

```php
$proxy = new SoapClient('https://mahohost/api/soap/?wsdl');
$sessionId = $proxy->login('apiUser', 'apiKey');

$filesPath = '/var/www/ws/tests/WebService/etc/Modules/Downloadable/Product/Link';
$downloadableProductId = 'downloadable_demo_product';

$items = [
    'small' => [
        'link' => [
            'title' => 'Test file',
            'price' => '123',
            'is_unlimited' => '1',
            'number_of_downloads' => '111',
            'is_shareable' => '0',
            'sample' => [
                'type' => 'file',
                'file' => [
                    'filename' => 'files/test.txt',
                ],
                'url' => 'https://mahocommerce.com/assets/maho-logo.svg',
            ],
            'type' => 'file',
            'file' => [
                'filename' => 'files/test.txt',
            ],
            'link_url' => 'https://mahocommerce.com/assets/maho-logo.svg',
        ],
        'sample' => [
            'title' => 'Test sample file',
            'type' => 'file',
            'file' => [
                'filename' => 'files/image.jpg',
            ],
            'sample_url' => 'https://mahocommerce.com/assets/maho-logo.svg',
            'sort_order' => '3',
        ]
    ],
    'big' => [
        'link' => [
            'title' => 'Test url',
            'price' => '123',
            'is_unlimited' => '0',
            'number_of_downloads' => '111',
            'is_shareable' => '1',
            'sample' => [
                'type' => 'url',
                'file' => [
                    'filename' => 'files/book.pdf',
                ],
                'url' => 'https://mahocommerce.com/assets/maho-logo.svg',
            ],
            'type' => 'url',
            'file' => [
                'filename' => 'files/song.mp3',
            ],
            'link_url' => 'https://mahocommerce.com/assets/maho-logo.svg',
        ],
        'sample' => [
            'title' => 'Test sample url',
            'type' => 'url',
            'file' => [
                'filename' => 'files/image.jpg',
            ],
            'sample_url' => 'https://mahocommerce.com/assets/maho-logo.svg',
            'sort_order' => '3',
        ]
    ]
];

$result = true;
foreach ($items as $item) {
    foreach ($item as $key => $value) {
        if ($value['type'] == 'file') {
            $filePath = $filesPath . '/' . $value['file']['filename'];
            $value['file'] = [
                'name' => str_replace('/', '_', $value['file']['filename']),
                'base64_content' => base64_encode(file_get_contents($filePath)),
                'type' => $value['type']
            ];
        }
        if ($value['sample']['type'] == 'file') {
            $filePath = $filesPath . '/' . $value['sample']['file']['filename'];
            $value['sample']['file'] = [
                'name' => str_replace('/', '_', $value['sample']['file']['filename']),
                'base64_content' => base64_encode(file_get_contents($filePath))
            ];
        }
        if (!$proxy->call(
            $sessionId,
            'product_downloadable_link.add',
            [
                $downloadableProductId,
                $value,
                $key
            ]
        )
        ) {
            $result = false;
        }
    }
}
```

#### Request Example SOAP V2 (WS-I Compliance Mode)

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl');
$sessionId = $proxy->login((object)['username' => 'apiUser', 'apiKey' => 'apiKey']);

$result = $proxy->catalogProductDownloadableLinkAdd(
    (object)[
        'sessionId' => $sessionId->result,
        'productId' => '3',
        'resourceType' => 'link',
        'resource' => (object)[
            'title' => 'link',
            'price' => '10.99',
            'sample' => [
                'type' => 'url',
                'url' => 'https://mahocommerce.com'
            ]
        ]
    ]
);
var_dump($result->result);
```

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl');
$sessionId = $proxy->login((object)['username' => 'apiUser', 'apiKey' => 'apiKey']);

$result = $proxy->catalogProductDownloadableLinkAdd(
    (object)[
        'sessionId' => $sessionId->result,
        'productId' => '3',
        'resourceType' => 'link',
        'resource' => (object)[
            'title' => 'link_2',
            'price' => '11.99',
            'type' => 'file',
            'file' => [
                'name' => 'file_test',
                'base64_content' => 'R0lGODlhAQABAIAAAAAAAAAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw=='
            ]
        ]
    ]
);
var_dump($result->result);
```

## List

### Method

- `product_downloadable_link.list` (SOAP V1)
- `catalogProductDownloadableLinkList` (SOAP V2)

Allows you to retrieve a list of links of a downloadable product.

### Arguments

| Type   | Name           | Description                                                    |
|--------|----------------|----------------------------------------------------------------|
| string | sessionId      | Session ID                                                     |
| string | productId      | Product ID or SKU                                              |
| string | store          | Store view ID or code (optional)                               |
| string | identifierType | Defines whether the product ID or SKU is passed in the request |

### Return

| Type  | Name   | Description                                       |
|-------|--------|---------------------------------------------------|
| array | result | Array of catalogProductDownloadableLinkListEntity |

### Content `catalogProductDownloadableLinkListEntity`

| Type  | Name    | Description                                         |
|-------|---------|-----------------------------------------------------|
| array | links   | Array of catalogProductDownloadableLinkEntity       |
| array | samples | Array of catalogProductDownloadableLinkSampleEntity |

### Content `catalogProductDownloadableLinkEntity`

| Type   | Name                | Description                                                                         |
|--------|---------------------|-------------------------------------------------------------------------------------|
| string | link_id             | Link ID                                                                             |
| string | title               | Link title                                                                          |
| string | price               | Downloadable link price value                                                       |
| int    | number_of_downloads | Maximum number of possible downloads                                                |
| int    | is_unlimited        | Defines whether the number of downloads is unlimited                                |
| int    | is_shareable        | Defines whether the link is shareable                                               |
| string | link_url            | Link URL address                                                                    |
| string | link_type           | Type of the link data source. Can have one of the following values: 'file' or 'url' |
| string | sample_file         | Sample file name                                                                    |
| string | sample_url          | Sample URL                                                                          |
| string | sample_type         | Type of sample data source. Can have one of the following values: 'file' or 'url'   |
| int    | sort_order          | Link sort order                                                                     |
| array  | file_save           | Array of catalogProductDownloadableLinkFileInfoEntity                               |
| array  | sample_file_save    | Array of catalogProductDownloadableLinkFileInfoEntity                               |

### Content `catalogProductDownloadableLinkSampleEntity`

| Type   | Name          | Description                                                        |
|--------|---------------|--------------------------------------------------------------------|
| string | sample_id     | Sample ID                                                          |
| string | product_id    | Product ID                                                         |
| string | sample_file   | Sample file name                                                   |
| string | sample_url    | Sample URL                                                         |
| string | sample_type   | Sample type. Can have one of the following values: 'file' or 'url' |
| string | sort_order    | Sort order                                                         |
| string | default_title | Default title                                                      |
| string | store_title   | Store title                                                        |
| string | title         | Sample title                                                       |

### Content `catalogProductDownloadableLinkFileInfoEntity`

| Type   | Name   | Description |
|--------|--------|-------------|
| string | file   | File        |
| string | name   | File name   |
| int    | size   | File size   |
| string | status | Status      |

### Faults

_No Faults._

### Examples

#### Request Example SOAP V1

```php
$proxy = new SoapClient('https://mahohost/api/soap/?wsdl');
$sessionId = $proxy->login('apiUser', 'apiKey');

$downloadableProductId = '5';

$resultList = $proxy->call(
    $sessionId,
    'product_downloadable_link.list',
    [$downloadableProductId]
);
```

#### Request Example SOAP V2

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl'); // replace with your store's WSDL URL
$sessionId = $proxy->login('apiUser', 'apiKey'); // replace with your API user and key

$result = $proxy->catalogProductDownloadableLinkList($sessionId, '5', null, 'sku');
var_dump($result);
```

#### Request Example SOAP V2 (WS-I Compliance Mode)

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl');
$sessionId = $proxy->login((object)['username' => 'apiUser', 'apiKey' => 'apiKey']);

$result = $proxy->catalogProductDownloadableLinkList(
    (object)[
        'sessionId' => $sessionId->result,
        'productId' => '5'
    ]
);
var_dump($result->result);
```

#### Response Example SOAP V1

```php
array
  'links' =>
    array
      0 =>
        array
          'link_id' => string '1' (length=1)
          'title' => string 'link 1' (length=11)
          'price' => string '30.0000' (length=7)
          'number_of_downloads' => string '0' (length=1)
          'is_shareable' => string '1' (length=1)
          'link_url' => null
          'link_type' => string 'file' (length=4)
          'sample_file' => string '/s/o/software.jpg' (length=17)
          'sample_url' => null
          'sample_type' => string 'file' (length=4)
          'sort_order' => string '1' (length=1)
          'file_save' =>
            array
              ...
          'sample_file_save' =>
            array
              ...
          'is_unlimited' => int 1
  'samples' =>
    array
      0 =>
        array
          'sample_id' => string '1' (length=1)
          'product_id' => string '5' (length=1)
          'sample_url' => null
          'sample_file' => string '/s/o/software.jpg' (length=17)
          'sample_type' => string 'file' (length=4)
          'sort_order' => string '2' (length=1)
          'default_title' => string 'Sample 1' (length=8)
          'store_title' => string 'Sample 1' (length=8)
          'title' => string 'Sample 1' (length=8)
```

## Remove

### Method

- `product_downloadable_link.remove` (SOAP V1)
- `catalogProductDownloadableLinkRemove` (SOAP V2)

Allows you to remove a link/sample from a downloadable product.

### Arguments

| Type   | Name            | Description                                                             |
|--------|-----------------|-------------------------------------------------------------------------|
| string | sessionId       | Session ID                                                              |
| string | linkId/sampleId | Link/sample ID                                                          |
| string | resourceType    | Resource type. Can have one of the following values: 'sample' or 'link' |

### Return

| Type    | Description                                                    |
|---------|----------------------------------------------------------------|
| boolean | True if the link/sample is removed from a downloadable product |

### Faults

| Fault Code | Fault Message                                    |
|------------|--------------------------------------------------|
| 412        | Link or sample with specified ID was not found.  |
| 415        | Validation error has occurred.                   |
| 416        | Unable to remove link. Details in error message. |

### Examples

#### Request Example SOAP V1

```php
$proxy = new SoapClient('https://mahohost/api/soap/?wsdl');
$sessionId = $proxy->login('apiUser', 'apiKey');

$sampleId = 7;
$linkId = 9;

$resultSample = $proxy->call(
    $sessionId,
    'product_downloadable_link.remove',
    [$sampleId, 'sample']
);

$resultLink = $proxy->call(
    $sessionId,
    'product_downloadable_link.remove',
    [$linkId, 'link']
);
```

#### Request Example SOAP V2

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl'); // replace with your store's WSDL URL
$sessionId = $proxy->login('apiUser', 'apiKey'); // replace with your API user and key

$result = $proxy->catalogProductDownloadableLinkRemove($sessionId, '7', 'sample');
var_dump($result);
```

#### Request Example SOAP V2 (WS-I Compliance Mode)

```php
$proxy = new SoapClient('https://mahohost/api/v2_soap/?wsdl');
$sessionId = $proxy->login((object)['username' => 'apiUser', 'apiKey' => 'apiKey']);

$result = $proxy->catalogProductDownloadableLinkRemove(
    (object)[
        'sessionId' => $sessionId->result,
        'linkId' => '7',
        'resourceType' => 'sample'
    ]
);
var_dump($result->result);
```
