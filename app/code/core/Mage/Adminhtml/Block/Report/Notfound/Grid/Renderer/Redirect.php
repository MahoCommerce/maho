<?php

/**
 * Link to a new URL rewrite that redirects the path, when no rewrite redirects it and the store view has no rewrite
 * with this request path.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

declare(strict_types=1);

class Mage_Adminhtml_Block_Report_Notfound_Grid_Renderer_Redirect extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract
{
    #[\Override]
    public function render(\Maho\DataObject $row): string
    {
        if ($row->getData('redirect_target') !== null
            || $row->getData('url_rewrite_id') !== null
            || !Mage::getSingleton('admin/session')->isAllowed(Mage_Adminhtml_UrlrewriteController::ADMIN_RESOURCE)
        ) {
            return '';
        }
        $path = (string) $row->getData('request_path');
        $url = $this->getUrl('*/urlrewrite/edit', ['_query' => [
            'id' => '',
            'request_path' => $path,
            'store_id' => (int) $row->getData('store_id'),
        ]]);
        return '<a href="' . $this->escapeUrl($url) . '" aria-label="'
            . $this->escapeHtml(Mage::helper('reports')->__('Redirect %s', $path)) . '">'
            . $this->escapeHtml(Mage::helper('reports')->__('Redirect')) . '</a>';
    }
}
