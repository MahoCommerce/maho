<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Newsletter
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * The newsletter tab of the admin customer page filters the queues by the subscriber of the
 * customer. A customer who never subscribed has no subscriber ID. The filter once compared the
 * integer column with '', which PostgreSQL rejects, so the customer page did not open.
 */
it('finds no queue for a customer who has no subscriber, without comparing the ID with an empty string', function () {
    $collection = Mage::getResourceModel('newsletter/queue_collection')
        ->addTemplateInfo()
        ->addSubscriberFilter(null);

    expect((string) $collection->getSelect())->not->toContain("subscriber_id = ''")
        ->and($collection->load()->count())->toBe(0);
});
