<?php

/**
 * Show the referer as escaped text, and as a link when it is an http or https address.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

declare(strict_types=1);

class Mage_Adminhtml_Block_Report_Notfound_Grid_Renderer_Referer extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract
{
    #[\Override]
    public function render(\Maho\DataObject $row)
    {
        $referer = (string) $row->getData($this->getColumn()->getIndex());
        if ($referer === '') {
            return '';
        }
        $text = $this->escapeHtml($referer);
        $scheme = strtolower((string) parse_url($referer, PHP_URL_SCHEME));
        if ($scheme !== 'http' && $scheme !== 'https') {
            return $text;
        }
        return '<a href="' . $this->escapeUrl($referer) . '" target="_blank" rel="noopener noreferrer nofollow">'
            . $text . '</a>';
    }

    #[\Override]
    public function renderExport(\Maho\DataObject $row)
    {
        return (string) $row->getData($this->getColumn()->getIndex());
    }
}
