<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
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
