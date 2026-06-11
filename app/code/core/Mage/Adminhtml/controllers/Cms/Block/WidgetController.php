<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Cms_Block_WidgetController extends Mage_Adminhtml_Controller_Action
{
    /**
     * ACL resource
     * @see Mage_Adminhtml_Controller_Action::_isAllowed()
     */
    public const ADMIN_RESOURCE = 'cms/widget_instance';

    /**
     * Chooser Source action
     */
    #[Maho\Config\Route('/admin/cms_block_widget/chooser')]
    public function chooserAction(): void
    {
        $uniqId = $this->getRequest()->getParam('uniq_id');
        $pagesGrid = $this->getLayout()->createBlock('adminhtml/cms_block_widget_chooser', '', [
            'id' => $uniqId,
        ]);
        $this->getResponse()->setBody($pagesGrid->toHtml());
    }
}
