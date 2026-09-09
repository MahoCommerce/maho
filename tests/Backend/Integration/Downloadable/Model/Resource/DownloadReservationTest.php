<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

function downloadReservationItem(int $downloadsBought): Mage_Downloadable_Model_Link_Purchased_Item
{
    $purchased = Mage::getModel('downloadable/link_purchased');
    $purchased->setOrderId(null)
        ->setOrderIncrementId('reservation-' . uniqid())
        ->setCustomerId(null)
        ->setProductName('Reservation product')
        ->setProductSku('reservation-sku')
        ->save();

    $item = Mage::getModel('downloadable/link_purchased_item');
    $item->setPurchasedId($purchased->getId())
        ->setOrderItemId(null)
        ->setProductId(null)
        ->setLinkHash(bin2hex(random_bytes(16)))
        ->setNumberOfDownloadsBought($downloadsBought)
        ->setNumberOfDownloadsUsed(0)
        ->setLinkId(0)
        ->setLinkTitle('Reservation link')
        ->setLinkType(Mage_Downloadable_Helper_Download::LINK_TYPE_URL)
        ->setLinkUrl('https://example.com/file.zip')
        ->setStatus(Mage_Downloadable_Model_Link_Purchased_Item::LINK_STATUS_AVAILABLE)
        ->save();
    return $item;
}

describe('downloadable download reservation', function () {
    test('stops at the number of downloads bought and expires the link', function () {
        $item = downloadReservationItem(2);
        $resource = Mage::getResourceModel('downloadable/link_purchased_item');
        $itemId = (int) $item->getId();

        expect($resource->reserveDownload($itemId))->toBeTrue();
        expect($resource->reserveDownload($itemId))->toBeTrue();
        expect($resource->reserveDownload($itemId))->toBeFalse();

        $item->load($itemId);
        expect((int) $item->getNumberOfDownloadsUsed())->toBe(2);
        expect($item->getStatus())->toBe(Mage_Downloadable_Model_Link_Purchased_Item::LINK_STATUS_EXPIRED);
    });

    test('is unlimited when no download count was bought', function () {
        $item = downloadReservationItem(0);
        $resource = Mage::getResourceModel('downloadable/link_purchased_item');
        $itemId = (int) $item->getId();

        for ($i = 0; $i < 3; $i++) {
            expect($resource->reserveDownload($itemId))->toBeTrue();
        }

        $item->load($itemId);
        expect((int) $item->getNumberOfDownloadsUsed())->toBe(3);
        expect($item->getStatus())->toBe(Mage_Downloadable_Model_Link_Purchased_Item::LINK_STATUS_AVAILABLE);
    });

    test('release gives the reserved download back', function () {
        $item = downloadReservationItem(1);
        $resource = Mage::getResourceModel('downloadable/link_purchased_item');
        $itemId = (int) $item->getId();

        expect($resource->reserveDownload($itemId))->toBeTrue();
        $resource->releaseDownload($itemId);

        $item->load($itemId);
        expect((int) $item->getNumberOfDownloadsUsed())->toBe(0);
        expect($item->getStatus())->toBe(Mage_Downloadable_Model_Link_Purchased_Item::LINK_STATUS_AVAILABLE);
        expect($resource->reserveDownload($itemId))->toBeTrue();
    });
});
