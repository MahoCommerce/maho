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
        $todayStartOfDayDate  = Mage::app()->getLocale()->date()
            ->setTime('00:00:00')
            ->toString(Varien_Date::DATETIME_INTERNAL_FORMAT);

        return Mage::getResourceModel('blog/post_collection')
            ->addStoreFilter(Mage::app()->getStore())
            ->addFieldToFilter('visible', ['eq' => 1])
            ->addFieldToFilter('publish_date', ['from' => $todayStartOfDayDate, 'datetime' => true])
            ->addAttributeToSelect('*');
    }
}
