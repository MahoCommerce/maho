<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_TaxController extends Mage_Adminhtml_Controller_Action
{
    public const ADMIN_RESOURCE = 'system/config/tax';

    /**
     * Set tax ignore notification flag and redirect back
     */
    #[Maho\Config\Route('/admin/tax/ignoreTaxNotification')]
    public function ignoreTaxNotificationAction(): void
    {
        $section = $this->getRequest()->getParam('section');
        if ($section && preg_match('/^[a-zA-Z0-9_]+$/', $section)) {
            Mage::helper('tax')->setIsIgnored('tax/ignore_notification/' . $section, true);
        }
        $this->_redirectReferer();
    }
}
