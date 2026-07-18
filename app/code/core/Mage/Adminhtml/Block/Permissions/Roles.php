<?php

/**
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_Permissions_Roles extends Mage_Adminhtml_Block_Template
{
    /**
     * Get URL of adding new record
     *
     * @return string
     */
    public function getAddNewUrl()
    {
        return $this->getUrl('*/*/editrole');
    }

    /**
     * Get URL for refreshing role-rule relations
     *
     * @return string
     */
    public function getRefreshRolesUrl()
    {
        return $this->getUrl('*/*/refreshroles');
    }

    /**
     * Get grid HTML
     *
     * @return string
     */
    public function getGridHtml()
    {
        return $this->getChild('grid')->toHtml();
    }
}
