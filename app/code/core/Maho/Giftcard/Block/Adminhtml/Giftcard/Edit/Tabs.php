<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Giftcard
 */

declare(strict_types=1);

/**
 * Tabs container for the gift card edit page. Wired into the page via the
 * adminhtml_giftcard_edit layout handle, which adds it as a child of the
 * `left` reference and registers each tab via `<action method="addTab">`.
 *
 * Mirrors the canonical CMS Page Edit pattern — the Form_Container in the
 * `content` reference renders the edit chrome (header + save buttons) and
 * the empty `<form id="edit_form">` wrapper; this Tabs block then injects
 * each tab's content (form fieldsets / history grid) into that form via
 * the `destElementId` hook.
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
