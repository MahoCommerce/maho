<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

const SEGMENT_AUDIENCE_PREFIX = 'queue-segment-test';
const SEGMENT_AUDIENCE_FOREIGN_CODE = 'queue_segment_test';

function segmentAudienceCampaign(array $segmentIds, array $storeIds = [1]): Mage_Newsletter_Model_Queue
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

    $queue->setStores($storeIds)->save();

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

function segmentAudienceSubscriber(int $customerId = 0, int $storeId = 1): Mage_Newsletter_Model_Subscriber
{
    /** @var Mage_Newsletter_Model_Subscriber $subscriber */
    $subscriber = Mage::getModel('newsletter/subscriber');
    $subscriber->setStoreId($storeId)
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

/** A store view in a second website, so a subscription can sit outside a segment's website. */
function segmentAudienceForeignStore(): Mage_Core_Model_Store
{
    /** @var Mage_Core_Model_Store $store */
    $store = Mage::getModel('core/store')->load(SEGMENT_AUDIENCE_FOREIGN_CODE, 'code');
    if ($store->getId()) {
        return $store;
    }

    $website = Mage::getModel('core/website')
        ->setCode(SEGMENT_AUDIENCE_FOREIGN_CODE)
        ->setName('Queue Segment Website')
        ->setSortOrder(99)
        ->save();

    $group = Mage::getModel('core/store_group')
        ->setWebsiteId((int) $website->getId())
        ->setName(SEGMENT_AUDIENCE_FOREIGN_CODE)
        ->setRootCategoryId((int) Mage::app()->getStore(1)->getRootCategoryId())
        ->save();

    $store = Mage::getModel('core/store')
        ->setCode(SEGMENT_AUDIENCE_FOREIGN_CODE)
        ->setWebsiteId((int) $website->getId())
        ->setGroupId((int) $group->getId())
        ->setName('Queue Segment Store')
        ->setIsActive(1)
        ->setSortOrder(99)
        ->save();

    $website->setDefaultGroupId((int) $group->getId())->save();
    $group->setDefaultStoreId((int) $store->getId())->save();
    Mage::app()->reinitStores();

    return $store;
}

function segmentAudienceDeleteForeignStore(): void
{
    $deleted = false;

    // isSecureArea is already registered by MahoBackendTestCase::setUp(), which is still in
    // effect here: registering it again would throw, unregistering it would strip the base case's.
    foreach (['core/store' => 'code', 'core/store_group' => 'name', 'core/website' => 'code'] as $model => $field) {
        $entity = Mage::getModel($model)->load(SEGMENT_AUDIENCE_FOREIGN_CODE, $field);
        if ($entity->getId()) {
            $entity->delete();
            $deleted = true;
        }
    }

    if ($deleted) {
        Mage::app()->reinitStores();
    }
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

    segmentAudienceDeleteForeignStore();
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

it('links nobody when the campaign names segments that resolve to none', function () {
    $member = segmentAudienceCustomer();
    segmentAudienceSubscriber((int) $member->getId());
    segmentAudienceSubscriber();

    $queue = segmentAudienceCampaign([]);
    $queue->setCustomerSegmentIds('0')->save();

    $queue->getResource()->materializeRecipients($queue);

    expect(segmentAudienceLinkedIds((int) $queue->getId()))->toBe([]);

    $displayed = (int) Mage::getResourceModel('newsletter/queue_collection')
        ->addSubscribersInfo()
        ->addFieldToFilter('main_table.queue_id', $queue->getId())
        ->getFirstItem()
        ->getSubscribersTotal();

    expect($displayed)->toBe(0);
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

it('links a segment member only through a subscription in the segment website', function () {
    $foreignStore = segmentAudienceForeignStore();

    $home = segmentAudienceCustomer();
    $away = segmentAudienceCustomer();

    $homeSubscriber = segmentAudienceSubscriber((int) $home->getId());
    $awaySubscriber = segmentAudienceSubscriber((int) $away->getId(), (int) $foreignStore->getId());

    $segment = segmentAudienceSegment([(int) $home->getId(), (int) $away->getId()]);
    $queue = segmentAudienceCampaign([(int) $segment->getId()], [1, (int) $foreignStore->getId()]);

    $queue->getResource()->materializeRecipients($queue);

    $linked = segmentAudienceLinkedIds((int) $queue->getId());
    expect($linked)->toContain((int) $homeSubscriber->getId());
    expect($linked)->not->toContain((int) $awaySubscriber->getId());
});

it('names the segment ids that no longer exist', function () {
    $segment = segmentAudienceSegment([]);
    $segmentId = (int) $segment->getId();
    $segment->delete();

    $unknown = Mage::helper('customersegmentation')->getUnknownSegmentIds([$segmentId]);

    expect($unknown)->toBe([$segmentId]);
});

it('reports the unknown and the outside segments from one lookup', function () {
    $covering = segmentAudienceSegment([], '1');
    $foreign = segmentAudienceSegment([], '9999');

    $gone = segmentAudienceSegment([]);
    $goneId = (int) $gone->getId();
    $gone->delete();

    $issues = Mage::helper('customersegmentation')->getQueueSegmentIssues(
        [(int) $covering->getId(), (int) $foreign->getId(), $goneId],
        [1],
    );

    expect($issues['unknown'])->toBe([$goneId]);
    expect($issues['outside'])->toBe([$foreign->getName()]);
});
