<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * The website filter of the newsletter subscriber grid filters on the store views of the
 * website. It used to read "stores_ids", which no code sets, so the filter matched nothing.
 */
it('filters on the store views of the selected website', function () {
    $website = Mage::app()->getWebsite(1);
    $filter = Mage::app()->getLayout()->createBlock('adminhtml/newsletter_subscriber_grid_filter_website');
    $filter->setData('value', $website->getId());

    expect($website->getStoreIds())->not->toBeEmpty()
        ->and($filter->getCondition())->toBe(['in' => $website->getStoreIds()]);
});

it('does not filter when no website is selected', function () {
    $filter = Mage::app()->getLayout()->createBlock('adminhtml/newsletter_subscriber_grid_filter_website');

    expect($filter->getCondition())->toBeNull();
});
