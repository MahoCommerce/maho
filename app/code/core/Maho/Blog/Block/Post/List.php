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
        return Mage::getResourceModel('blog/post_collection')
            ->addAttributeToSelect('*');
    }
}