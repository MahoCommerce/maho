<?php

/**
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_Denied extends Mage_Adminhtml_Block_Template
{
    public function hasAvailaleResources()
    {
        $user = Mage::getSingleton('admin/session')->getUser();
        if ($user && $user->hasAvailableResources()) {
            return true;
        }
        return false;
    }
}
