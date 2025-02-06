<?php

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_Blog
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_Blog_Model_Resource_Post_Collection extends Mage_Eav_Model_Entity_Collection_Abstract
{
    protected function _construct()
    {
        $this->_init('blog/post');
    }

    /**
     * @param int|array $storeId
     */
    public function addStoreFilter($storeId): self
    {
        return $this->addFieldToFilter('store_id', ['in' => $storeId]);
    }
}
