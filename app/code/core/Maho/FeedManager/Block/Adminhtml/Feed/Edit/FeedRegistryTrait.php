<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_FeedManager
 */

declare(strict_types=1);

/**
 * Trait for accessing the current feed from registry
 *
 * Used by admin blocks that need access to the feed being edited.
 */
trait Maho_FeedManager_Block_Adminhtml_Feed_Edit_FeedRegistryTrait
{
    /**
     * Get current feed from registry
     */
    protected function _getFeed(): Maho_FeedManager_Model_Feed
    {
        return Mage::registry('current_feed') ?: Mage::getModel('feedmanager/feed');
    }
}
