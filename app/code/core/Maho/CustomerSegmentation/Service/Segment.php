<?php

/**
 * Load, save, delete and refresh customer segments for the admin pages and the API.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_CustomerSegmentation
 */

declare(strict_types=1);

class Maho_CustomerSegmentation_Service_Segment
{
    public const REFRESH_WAIT_SECONDS = 3600;

    public const REFRESH_MODES = [
        Maho_CustomerSegmentation_Model_Segment::MODE_AUTO,
        Maho_CustomerSegmentation_Model_Segment::MODE_MANUAL,
    ];

    /**
     * Return an unsaved segment with the default values of a new segment.
     */
    public function newSegment(): Maho_CustomerSegmentation_Model_Segment
    {
        /** @var Maho_CustomerSegmentation_Model_Segment $segment */
        $segment = Mage::getModel('customersegmentation/segment');
        return $segment->setIsActive()
            ->setWebsiteIds([])
            ->setCustomerGroupIds([])
            ->setRefreshMode(Maho_CustomerSegmentation_Model_Segment::MODE_AUTO)
            ->setRefreshStatus(Maho_CustomerSegmentation_Model_Segment::STATUS_PENDING)
            ->setMatchedCustomersCount(0)
            ->setPriority(0)
            ->setAutoEmailActive(false)
            ->setAllowOverlappingSequences(false);
    }

    /**
     * Load the segment $id. With $websiteIds, a segment that has none of these websites does not exist for the caller.
     *
     * @param list<int>|null $websiteIds
     * @throws Mage_Core_Exception_NoSuchEntity
     */
    public function getById(int $id, ?array $websiteIds = null): Maho_CustomerSegmentation_Model_Segment
    {
        /** @var Maho_CustomerSegmentation_Model_Segment $segment */
        $segment = Mage::getModel('customersegmentation/segment')->load($id);
        if (!$segment->getId() || ($websiteIds !== null && !$segment->isInWebsites($websiteIds))) {
            throw new Mage_Core_Exception_NoSuchEntity(Mage::helper('customersegmentation')->__('This segment no longer exists.'));
        }
        return $segment;
    }

    /**
     * Check $segment and save it. The transport puts its input into the segment through the typed setters first.
     * The model checks the name, the websites, the refresh mode and the priority. The service checks that each new
     * website and customer group exists, and the email sequences of the automation.
     * With $websiteIds, the client changes only a segment whose websites are all among these.
     *
     * @param list<int>|null $websiteIds
     * @throws Mage_Core_Exception_Forbidden when a website of the segment is not in $websiteIds
     * @throws Mage_Core_Exception_Input with one error for each problem
     */
    public function save(Maho_CustomerSegmentation_Model_Segment $segment, ?array $websiteIds = null): Maho_CustomerSegmentation_Model_Segment
    {
        $this->assertWritable($segment, $websiteIds);
        $helper = Mage::helper('customersegmentation');
        $errors = new Mage_Core_Exception_Input()->addValidationResult($segment->getFieldErrors(), 'segment');

        $knownWebsiteIds = array_map(intval(...), array_keys(Mage::app()->getWebsites()));
        // Accept a stored ID, because an admin can delete its website or group after the segment is saved
        $storedWebsiteIds = $segment::idList($segment->getOrigData('website_ids'));
        foreach (array_diff($segment->getWebsiteIds(), $knownWebsiteIds, $storedWebsiteIds) as $id) {
            $errors->addError('website_ids', $helper->__('Unknown website ID: %s.', $id));
        }

        $knownGroupIds = array_map(intval(...), Mage::getResourceModel('customer/group_collection')->getAllIds());
        $storedGroupIds = $segment::idList($segment->getOrigData('customer_group_ids'));
        foreach (array_diff($segment->getCustomerGroupIds(), $knownGroupIds, $storedGroupIds) as $id) {
            $errors->addError('customer_group_ids', $helper->__('Unknown customer group ID: %s.', $id));
        }

        // A new segment has no sequences yet: the admin adds them after the first save
        if ($segment->getAutoEmailActive() && $segment->getId()) {
            $sequences = $segment->getEmailSequences();
            if ($sequences->getSize() === 0) {
                $errors->addError('auto_email_active', $helper->__('At least one email sequence is required when automation is enabled.'));
            }
            foreach ($sequences as $sequence) {
                try {
                    $sequence->validate();
                } catch (Mage_Core_Exception $e) {
                    $errors->addError('auto_email_active', $helper->__('Sequence step %d: %s', $sequence->getStepNumber(), $e->getMessage()));
                }
            }
        }

        $errors->throwIfErrors();

        $segment->save();
        return $segment;
    }

    /**
     * @param list<int>|null $websiteIds with it, the client deletes only a segment whose websites are all among these
     * @throws Mage_Core_Exception_Forbidden when a website of the segment is not in $websiteIds
     */
    public function delete(Maho_CustomerSegmentation_Model_Segment $segment, ?array $websiteIds = null): void
    {
        $this->assertWritable($segment, $websiteIds);
        $segment->delete();
    }

    /**
     * Refuse a change to $segment by a client that works only in the websites $websiteIds, when a stored website
     * or a new website of the segment is not among them. Null means a client that works in every website.
     *
     * @param list<int>|null $websiteIds
     * @throws Mage_Core_Exception_Forbidden
     */
    private function assertWritable(Maho_CustomerSegmentation_Model_Segment $segment, ?array $websiteIds): void
    {
        if ($websiteIds === null) {
            return;
        }
        $stored = $segment::idList($segment->getOrigData('website_ids'));
        if (($segment->getId() && ($stored === [] || array_diff($stored, $websiteIds) !== []))
            || array_diff($segment->getWebsiteIds(), $websiteIds) !== []
        ) {
            throw new Mage_Core_Exception_Forbidden(
                Mage::helper('customersegmentation')->__('Access denied for a website of this customer segment'),
            );
        }
    }

    /**
     * Calculate the customers of $segment again from its conditions. An inactive segment has no customers.
     * When another refresh of the segment runs, wait for it for up to $waitSeconds. A request passes 0,
     * a queue worker and the cron pass REFRESH_WAIT_SECONDS.
     *
     * @param list<int>|null $websiteIds with it, the client refreshes only a segment whose websites are all among these
     * @throws Mage_Core_Exception_Forbidden when a website of the segment is not in $websiteIds
     * @throws Mage_Core_Exception_Conflict when the other refresh still runs after the wait
     */
    public function refresh(
        Maho_CustomerSegmentation_Model_Segment $segment,
        int $waitSeconds = 0,
        ?array $websiteIds = null,
    ): Maho_CustomerSegmentation_Model_Segment {
        $this->assertWritable($segment, $websiteIds);
        // Two refreshes of one segment at once read and change the same members
        $lock = Mage::getSingleton('core/lock');
        $lockName = 'customersegmentation_refresh_' . (int) $segment->getId();
        if (!$lock->acquireWithin($lockName, $waitSeconds)) {
            throw new Mage_Core_Exception_Conflict(Mage::helper('customersegmentation')->__('Another refresh of the segment is in progress.'));
        }
        try {
            $segment->refreshCustomers();
        } finally {
            $lock->release($lockName);
        }

        // A click during the refresh queued one more, so the segment waits again
        $dedupeKey = new Maho_CustomerSegmentation_Model_RefreshMessage((int) $segment->getId())->getDedupeKey();
        if (\Maho\Queue\QueueManager::dbTransport()->inFlightRowExists($dedupeKey, pendingOnly: true)) {
            $this->markPending($segment);
        }
        return $segment;
    }

    /**
     * Queue refresh() for $segment and mark it pending. A second request for the segment is dropped
     * while the first one waits. A request that comes while it runs queues one more refresh, because
     * the refresh read the customers at its start. The result goes to the inbox of $adminUserId.
     */
    public function queueRefresh(Maho_CustomerSegmentation_Model_Segment $segment, ?int $adminUserId = null): void
    {
        // First, so it cannot overwrite the status of a worker that finishes the refresh at once
        $this->markPending($segment);

        $message = new Maho_CustomerSegmentation_Model_RefreshMessage((int) $segment->getId(), $adminUserId);
        \Maho\Queue\QueueManager::dispatch(
            $message,
            queue: Maho_CustomerSegmentation_Model_RefreshMessage::QUEUE_NAME,
            stamps: [new \Maho\Queue\Stamp\DedupeKeyStamp($message->getDedupeKey(), pendingOnly: true)],
        );
    }

    /**
     * Write only the status column: a full save of the segment runs its save hooks.
     */
    private function markPending(Maho_CustomerSegmentation_Model_Segment $segment): void
    {
        Mage::getSingleton('core/resource')->getConnection('core_write')->update(
            $segment->getResource()->getMainTable(),
            ['refresh_status' => Maho_CustomerSegmentation_Model_Segment::STATUS_PENDING],
            ['segment_id = ?' => (int) $segment->getId()],
        );
        $segment->setRefreshStatus(Maho_CustomerSegmentation_Model_Segment::STATUS_PENDING);
    }
}
