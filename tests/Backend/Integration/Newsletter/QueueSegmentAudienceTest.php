<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

const SEGMENT_AUDIENCE_PREFIX = 'queue-segment-test';

function segmentAudienceCampaign(array $segmentIds): Mage_Newsletter_Model_Queue
{
    /** @var Mage_Newsletter_Model_Template $template */
    $template = Mage::getModel('newsletter/template');
    $template->setTemplateCode(SEGMENT_AUDIENCE_PREFIX . ' ' . uniqid())
        ->setTemplateType(Mage_Newsletter_Model_Template::TYPE_HTML)
        ->setTemplateSubject('Campaign')
        ->setTemplateSenderName('Shop')
        ->setTemplateSenderEmail('shop@example.com')
        ->setTemplateText('<p>Hello</p>')
        ->save();

    /** @var Mage_Newsletter_Model_Queue $queue */
    $queue = Mage::getModel('newsletter/queue');
    $queue->setTemplateId($template->getId())
        ->setNewsletterType(Mage_Newsletter_Model_Template::TYPE_HTML)
        ->setNewsletterSubject('Campaign')
        ->setNewsletterSenderName('Shop')
        ->setNewsletterSenderEmail('shop@example.com')
        ->setNewsletterText('<p>Hello</p>')
        ->setCustomerSegmentIds(implode(',', $segmentIds))
        ->setQueueStatus(Mage_Newsletter_Model_Queue::STATUS_NEVER)
        ->setQueueStartAt(Mage::app()->getLocale()->formatDateForDb('now'))
        ->save();

    $queue->setStores([1])->save();

    return $queue;
}

function segmentAudienceCustomer(): Mage_Customer_Model_Customer
{
    /** @var Mage_Customer_Model_Customer $customer */
    $customer = Mage::getModel('customer/customer');
    $customer->setWebsiteId(1)
        ->setGroupId(1)
        ->setFirstname('Segment')
        ->setLastname('Member')
        ->setEmail(SEGMENT_AUDIENCE_PREFIX . '-' . uniqid() . '@example.com')
        ->save();

    return $customer;
}

function segmentAudienceSubscriber(int $customerId = 0): Mage_Newsletter_Model_Subscriber
{
    /** @var Mage_Newsletter_Model_Subscriber $subscriber */
    $subscriber = Mage::getModel('newsletter/subscriber');
    $subscriber->setStoreId(1)
        ->setCustomerId($customerId)
        ->setSubscriberEmail(SEGMENT_AUDIENCE_PREFIX . '-' . uniqid() . '@example.com')
        ->setSubscriberStatus(Mage_Newsletter_Model_Subscriber::STATUS_SUBSCRIBED)
        ->save();

    return $subscriber;
}

function segmentAudienceSegment(array $customerIds, string $websiteIds = '1'): Maho_CustomerSegmentation_Model_Segment
{
    /** @var Maho_CustomerSegmentation_Model_Segment $segment */
    $segment = Mage::getModel('customersegmentation/segment');
    $segment->setName(SEGMENT_AUDIENCE_PREFIX . ' ' . uniqid())
        ->setIsActive(1)
        ->setWebsiteIds($websiteIds)
        ->setCustomerGroupIds('0,1,2,3')
        ->setRefreshMode(Maho_CustomerSegmentation_Model_Segment::MODE_MANUAL)
        ->save();

    Mage::getResourceModel('customersegmentation/segment')->updateCustomerMembership($segment, $customerIds);

    return $segment;
}

function segmentAudienceLinkedIds(int $queueId): array
{
    $adapter = Mage::getSingleton('core/resource')->getConnection('core_read');
    $table = Mage::getSingleton('core/resource')->getTableName('newsletter/queue_link');

    return array_map('intval', $adapter->fetchCol(
        $adapter->select()->from($table, 'subscriber_id')->where('queue_id = ?', $queueId),
    ));
}

afterEach(function () {
    // Campaigns, links and store links all cascade from the template
    $templates = Mage::getResourceModel('newsletter/template_collection')
        ->addFieldToFilter('template_code', ['like' => SEGMENT_AUDIENCE_PREFIX . '%']);
    foreach ($templates as $template) {
        $template->delete();
    }

    $subscribers = Mage::getResourceModel('newsletter/subscriber_collection')
        ->addFieldToFilter('subscriber_email', ['like' => SEGMENT_AUDIENCE_PREFIX . '%']);
    foreach ($subscribers as $subscriber) {
        $subscriber->delete();
    }

    $segments = Mage::getResourceModel('customersegmentation/segment_collection')
        ->addFieldToFilter('name', ['like' => SEGMENT_AUDIENCE_PREFIX . '%']);
    foreach ($segments as $segment) {
        $segment->delete();
    }

    $customers = Mage::getResourceModel('customer/customer_collection')
        ->addFieldToFilter('email', ['like' => SEGMENT_AUDIENCE_PREFIX . '%']);
    foreach ($customers as $customer) {
        $customer->delete();
    }
});

it('links only the subscribers belonging to the campaign segments', function () {
    $member = segmentAudienceCustomer();
    $outsider = segmentAudienceCustomer();

    $memberSubscriber = segmentAudienceSubscriber((int) $member->getId());
    $outsiderSubscriber = segmentAudienceSubscriber((int) $outsider->getId());
    $guestSubscriber = segmentAudienceSubscriber();

    $segment = segmentAudienceSegment([(int) $member->getId()]);
    $queue = segmentAudienceCampaign([(int) $segment->getId()]);

    $queue->getResource()->materializeRecipients($queue);

    $linked = segmentAudienceLinkedIds((int) $queue->getId());
    expect($linked)->toContain((int) $memberSubscriber->getId());
    expect($linked)->not->toContain((int) $outsiderSubscriber->getId());
    expect($linked)->not->toContain((int) $guestSubscriber->getId());
});

it('links every store subscriber when the campaign names no segment', function () {
    $member = segmentAudienceCustomer();
    $memberSubscriber = segmentAudienceSubscriber((int) $member->getId());
    $guestSubscriber = segmentAudienceSubscriber();

    segmentAudienceSegment([(int) $member->getId()]);
    $queue = segmentAudienceCampaign([]);

    $queue->getResource()->materializeRecipients($queue);

    $linked = segmentAudienceLinkedIds((int) $queue->getId());
    expect($linked)->toContain((int) $memberSubscriber->getId());
    expect($linked)->toContain((int) $guestSubscriber->getId());
});

it('counts the segmented audience in the grid, column and filter alike', function () {
    $member = segmentAudienceCustomer();
    segmentAudienceSubscriber((int) $member->getId());
    segmentAudienceSubscriber();

    $segment = segmentAudienceSegment([(int) $member->getId()]);
    $queue = segmentAudienceCampaign([(int) $segment->getId()]);

    $displayed = (int) Mage::getResourceModel('newsletter/queue_collection')
        ->addSubscribersInfo()
        ->addFieldToFilter('main_table.queue_id', $queue->getId())
        ->getFirstItem()
        ->getSubscribersTotal();

    expect($displayed)->toBe(1);

    $filtered = Mage::getResourceModel('newsletter/queue_collection')
        ->addFieldToFilter('subscribers_total', ['eq' => 1]);
    expect(array_map('intval', array_keys($filtered->getItems())))->toContain((int) $queue->getId());
});

it('names the segments that cover none of the selected stores', function () {
    $covering = segmentAudienceSegment([], '1');
    $foreign = segmentAudienceSegment([], '9999');

    $outside = Mage::helper('customersegmentation')
        ->getSegmentsOutsideStores([(int) $covering->getId(), (int) $foreign->getId()], [1]);

    expect($outside)->toBe([$foreign->getName()]);
});
