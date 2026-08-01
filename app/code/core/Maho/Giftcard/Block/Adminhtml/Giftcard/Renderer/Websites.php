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
     * Render the grid's "Websites" cell from the GROUP_CONCAT'd website_ids
     * alias built in Grid::_prepareCollection().
     *
     * Looks up display names from the admin system-store helper once per
     * render call and maps each comma-separated id; a row with no junction
     * entries (NULL alias) renders as an em dash so the listing still
     * surfaces orphaned cards rather than hiding them.
     */
    #[\Override]
    public function render(Maho\DataObject $row)
    {
        $raw = (string) $row->getData('website_ids');
        if ($raw === '') {
            return '<span class="muted">—</span>';
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
