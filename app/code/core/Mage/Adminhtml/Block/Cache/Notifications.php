<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_Block_Cache_Notifications extends Mage_Adminhtml_Block_Template
{
    /**
     * Get array of cache types which require data refresh
     *
     * @return array
     */
    public function getCacheTypesForRefresh()
    {
        $invalidatedTypes = Mage::app()->getCache()->getInvalidatedTypes();
        $res = [];
        foreach ($invalidatedTypes as $type) {
            $res[] = $type->getCacheType();
        }
        return $res;
    }

    /**
     * Get index management url
     *
     * @return string
     */
    public function getManageUrl()
    {
        return $this->getUrl('adminhtml/cache');
    }

    /**
     * ACL validation before html generation
     *
     * @return string
     */
    #[\Override]
    protected function _toHtml()
    {
        if (Mage::getSingleton('admin/session')->isAllowed('system/cache')) {
            return parent::_toHtml();
        }
        return '';
    }
}
