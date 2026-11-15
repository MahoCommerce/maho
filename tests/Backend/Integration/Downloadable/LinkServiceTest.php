<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

function linkServiceProduct(): Mage_Catalog_Model_Product
{
    $id = (int) Mage::getResourceModel('catalog/product_collection')
        ->addAttributeToFilter('type_id', Mage_Downloadable_Model_Product_Type::TYPE_DOWNLOADABLE)
        ->setOrder('entity_id', 'ASC')
        ->setPageSize(1)
        ->getFirstItem()
        ->getId();
    if ($id === 0) {
        test()->markTestSkipped('The store has no downloadable product');
    }
    return Mage::getModel('catalog/product')->load($id);
}

/**
 * @return array<int, array{link_url: ?string, sample_url: ?string}> the stored URLs of each link of product $productId
 */
function linkServiceStoredUrls(int $productId): array
{
    $adapter = Mage::getSingleton('core/resource')->getConnection('core_read');
    $rows = $adapter->fetchAll(
        $adapter->select()
            ->from(Mage::getSingleton('core/resource')->getTableName('downloadable/link'), ['link_id', 'link_url', 'sample_url'])
            ->where('product_id = ?', $productId),
    );
    $urls = [];
    foreach ($rows as $row) {
        $urls[(int) $row['link_id']] = ['link_url' => $row['link_url'], 'sample_url' => $row['sample_url']];
    }
    return $urls;
}

function linkServiceNewLink(string $url): Mage_Downloadable_Model_Link
{
    return Mage::getModel('downloadable/link')
        ->setTitle('Pest link ' . uniqid())
        ->setPrice(0.0)
        ->setSortOrder(0)
        ->setNumberOfDownloads(0)
        ->setLinkType(Mage_Downloadable_Helper_Download::LINK_TYPE_URL)
        ->setData('is_shareable', Mage_Downloadable_Model_Link::LINK_SHAREABLE_CONFIG)
        ->setLinkUrl($url)
        ->setSampleType(Mage_Downloadable_Helper_Download::LINK_TYPE_URL)
        ->setSampleUrl($url);
}

/**
 * Delete the links of product $productId that are not in $keep.
 *
 * @param list<int> $keep
 */
function linkServiceRestore(int $productId, array $keep): void
{
    $added = array_diff(array_keys(linkServiceStoredUrls($productId)), $keep);
    if ($added !== []) {
        Mage::getResourceModel('downloadable/link')->deleteItems(array_values($added));
    }
}

it('refuses a link with a negative price', function () {
    $product = linkServiceProduct();
    $before = array_keys(linkServiceStoredUrls((int) $product->getId()));
    $link = linkServiceNewLink('https://files.example/a.zip')->setPrice(-1.0);

    try {
        $errors = [];
        try {
            Mage::getService('downloadable/link')->save($product, $link);
        } catch (Mage_Core_Exception_Input $e) {
            $errors = $e->getErrors();
        }

        expect($errors)->toBe([['field' => 'price', 'message' => 'Price must not be negative']])
            ->and(array_keys(linkServiceStoredUrls((int) $product->getId())))->toBe($before);
    } finally {
        linkServiceRestore((int) $product->getId(), $before);
    }
});

it('does not escape a stored URL again in a later write', function () {
    $product = linkServiceProduct();
    $productId = (int) $product->getId();
    $before = array_keys(linkServiceStoredUrls($productId));
    $service = Mage::getService('downloadable/link');
    $url = 'https://files.example/a.zip?a=1&b=2';
    $stored = ['link_url' => 'https://files.example/a.zip?a=1&amp;b=2', 'sample_url' => 'https://files.example/a.zip?a=1&amp;b=2'];

    try {
        $firstId = (int) $service->save($product, linkServiceNewLink($url))->getId();
        expect(linkServiceStoredUrls($productId)[$firstId])->toBe($stored);

        // A write of another link
        $service->save($product, linkServiceNewLink('https://files.example/b.zip'));
        expect(linkServiceStoredUrls($productId)[$firstId])->toBe($stored);

        // A write of the same link that leaves the URLs as they are
        $service->save($product, $service->getById($product, $firstId)->setSortOrder(5));
        expect(linkServiceStoredUrls($productId)[$firstId])->toBe($stored);

        // A write of the same link with the same URLs from the client
        $service->save($product, $service->getById($product, $firstId)->setLinkUrl($url)->setSampleUrl($url));
        expect(linkServiceStoredUrls($productId)[$firstId])->toBe($stored);
    } finally {
        linkServiceRestore($productId, $before);
    }
});
