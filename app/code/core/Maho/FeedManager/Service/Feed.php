<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_FeedManager
 */

declare(strict_types=1);

class Maho_FeedManager_Service_Feed
{
    /**
     * Generate $feed and return the log of the generation. The log tells if the generation failed.
     *
     * @param (callable(string, Maho_FeedManager_Model_Log): void)|null $afterPublish see Maho_FeedManager_Model_Generator::generate()
     * @throws Mage_Core_Exception_Conflict when a generation of the feed is running
     */
    public function generate(Maho_FeedManager_Model_Feed $feed, ?callable $afterPublish = null): Maho_FeedManager_Model_Log
    {
        if (Maho_FeedManager_Model_Generator::isGenerating((int) $feed->getId())) {
            throw new Mage_Core_Exception_Conflict(
                Mage::helper('feedmanager')->__("Feed '%s' is already being generated", $feed->getName()),
            );
        }

        /** @var Maho_FeedManager_Model_Generator $generator */
        $generator = Mage::getModel('feedmanager/generator');
        return $generator->generate($feed, $afterPublish);
    }
}
