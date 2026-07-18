<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_Newsletter_Queue extends Mage_Adminhtml_Block_Template
{
    public function __construct()
    {
        $this->setTemplate('newsletter/queue/list.phtml');
    }

    #[\Override]
    protected function _beforeToHtml()
    {
        $this->setChild('grid', $this->getLayout()->createBlock('adminhtml/newsletter_queue_grid', 'newsletter.queue.grid'));
        return parent::_beforeToHtml();
    }
}
