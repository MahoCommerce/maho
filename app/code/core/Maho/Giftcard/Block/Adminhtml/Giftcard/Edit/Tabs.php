<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Giftcard
 */

declare(strict_types=1);

/**
 * Tabs container for the gift card edit page (same pattern as CMS Page Edit).
 */
class Maho_Giftcard_Block_Adminhtml_Giftcard_Edit_Tabs extends Mage_Adminhtml_Block_Widget_Tabs
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('giftcard_edit_tabs');
        $this->setDestElementId('edit_form');
        $this->setTitle(Mage::helper('giftcard')->__('Gift Card Information'));
    }
}
