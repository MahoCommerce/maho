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
            ->setPriority(0)
            ->setAutoEmailActive(false)
            ->setAllowOverlappingSequences(false);
    }

    /**
     * @throws Mage_Core_Exception_NoSuchEntity
     */
    public function getById(int $id): Maho_CustomerSegmentation_Model_Segment
    {
        /** @var Maho_CustomerSegmentation_Model_Segment $segment */
        $segment = Mage::getModel('customersegmentation/segment');
        if ($id > 0) {
            $segment->load($id);
        }
        if (!$segment->getId()) {
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
        foreach (array_diff($websiteIds, $knownWebsiteIds) as $id) {
            $errors->addError('website_ids', $helper->__('Unknown website ID: %s.', $id));
        }

        $knownGroupIds = array_map(intval(...), Mage::getResourceModel('customer/group_collection')->getAllIds());
        foreach (array_diff($segment->getCustomerGroupIds(), $knownGroupIds) as $id) {
            $errors->addError('customer_group_ids', $helper->__('Unknown customer group ID: %s.', $id));
        }

        $refreshMode = $segment->getRefreshMode();
        if ($refreshMode !== null && !in_array($refreshMode, self::REFRESH_MODES, true)) {
            $errors->addError('refresh_mode', $helper->__('The refresh mode must be one of: %s.', implode(', ', self::REFRESH_MODES)));
        }

        if ((int) $segment->getPriority() < 0) {
            $errors->addError('priority', $helper->__('The priority must be 0 or more.'));
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
        return $segment->refreshCustomers();
    }
}
