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
        $segment = Mage::getModel('customersegmentation/segment');
        if ($id > 0) {
            $segment->load($id);
        }
        if (!$segment->getId()
            || ($websiteIds !== null && array_intersect($segment->getWebsiteIds(), $websiteIds) === [])
        ) {
            throw new Mage_Core_Exception_NoSuchEntity(Mage::helper('customersegmentation')->__('This segment no longer exists.'));
        }
        return $segment;
    }

    /**
     * Check $segment and save it. The transport puts its input into the segment through the typed setters first.
     *
     * @throws Mage_Core_Exception_Input with one error for each problem
     */
    public function save(Maho_CustomerSegmentation_Model_Segment $segment): Maho_CustomerSegmentation_Model_Segment
    {
        $helper = Mage::helper('customersegmentation');
        $errors = new Mage_Core_Exception_Input();

        $name = trim((string) $segment->getName());
        if ($name === '' || mb_strlen($name) > 255) {
            $errors->addError('name', $helper->__('The segment name must have 1 to 255 characters.'));
        }
        $segment->setName($name);

        $websiteIds = $segment->getWebsiteIds();
        if ($websiteIds === []) {
            $errors->addError('website_ids', $helper->__('Please select at least one website.'));
        }
        $knownWebsiteIds = array_map(intval(...), array_keys(Mage::app()->getWebsites()));
        // Accept a stored ID, because an admin can delete its website or group after the segment is saved
        $storedWebsiteIds = $segment::idList($segment->getOrigData('website_ids'));
        foreach (array_diff($websiteIds, $knownWebsiteIds, $storedWebsiteIds) as $id) {
            $errors->addError('website_ids', $helper->__('Unknown website ID: %s.', $id));
        }

        $knownGroupIds = array_map(intval(...), Mage::getResourceModel('customer/group_collection')->getAllIds());
        $storedGroupIds = $segment::idList($segment->getOrigData('customer_group_ids'));
        foreach (array_diff($segment->getCustomerGroupIds(), $knownGroupIds, $storedGroupIds) as $id) {
            $errors->addError('customer_group_ids', $helper->__('Unknown customer group ID: %s.', $id));
        }

        $refreshMode = $segment->getRefreshMode();
        if ($refreshMode !== null && !in_array($refreshMode, self::REFRESH_MODES, true)) {
            $errors->addError('refresh_mode', $helper->__('The refresh mode must be one of: %s.', implode(', ', self::REFRESH_MODES)));
        }

        if ((int) $segment->getPriority() < 0) {
            $errors->addError('priority', $helper->__('The priority must be 0 or more.'));
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

    public function delete(Maho_CustomerSegmentation_Model_Segment $segment): void
    {
        $segment->delete();
    }

    /**
     * Calculate the customers of $segment again from its conditions. An inactive segment has no customers.
     */
    public function refresh(Maho_CustomerSegmentation_Model_Segment $segment): Maho_CustomerSegmentation_Model_Segment
    {
        // Two refreshes of one segment at once read and change the same members
        $lock = Mage::getSingleton('core/lock');
        $lockName = 'customersegmentation_refresh_' . (int) $segment->getId();
        if (!$lock->acquireWithin($lockName, 3600)) {
            throw new RuntimeException(Mage::helper('customersegmentation')->__('Another refresh of the segment is in progress.'));
        }
        try {
            $segment->refreshCustomers();
        } finally {
            $lock->release($lockName);
        }

        // A click during the refresh queued one more, so the segment waits again
        $dedupeKey = (new Maho_CustomerSegmentation_Model_RefreshMessage((int) $segment->getId()))->getDedupeKey();
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
