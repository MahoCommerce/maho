<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Giftcard
 */

declare(strict_types=1);

class Maho_Giftcard_Block_Adminhtml_Giftcard_Renderer_Websites extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract
{
    /**
     * Renders website names from the aggregated `website_ids` alias; an
     * orphaned card (NULL alias) renders as a dash rather than being hidden.
     */
    #[\Override]
    public function render(Maho\DataObject $row)
    {
        $raw = (string) $row->getData('website_ids');
        if ($raw === '') {
            return '<span class="muted">&ndash;</span>';
        }

        $hash = Mage::getSingleton('adminhtml/system_store')->getWebsiteOptionHash();
        $names = [];
        foreach (explode(',', $raw) as $id) {
            $id = trim($id);
            if ($id === '') {
                continue;
            }
            $names[] = $this->escapeHtml($hash[(int) $id] ?? '[id ' . $id . ']');
        }

        return implode(', ', $names);
    }
}
