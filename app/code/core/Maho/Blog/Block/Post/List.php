<?php

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_Blog
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_Blog_Block_Post_List extends Mage_Core_Block_Template
{
    public function getPosts(): Maho_Blog_Model_Resource_Post_Collection
    {
        // Get today's date for filtering published posts (using new date constants)
        $today = Mage_Core_Model_Locale::today();

        $collection = Mage::getResourceModel('blog/post_collection')
            ->addStoreFilter(Mage::app()->getStore())
            ->addFieldToFilter('is_active', 1)
            ->addAttributeToSelect('*')
            ->setOrder('publish_date', 'DESC')
            ->addAttributeToSort('created_at', 'DESC');

        // Add publish date filter (show posts with no date or date <= today)
        $collection->getSelect()->where(
            'publish_date IS NULL OR publish_date <= ?',
            $today,
        );

        return $collection;
    }
}
