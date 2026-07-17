<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_MediaCleaner
 */

declare(strict_types=1);

class Maho_MediaCleaner_Block_Adminhtml_Mediacleaner_Grid_Renderer_Actions extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract
{
    #[\Override]
    public function render(\Maho\DataObject $row)
    {
        $links = [];

        $links[] = sprintf(
            '<a href="%s">%s</a>',
            $this->getUrl('*/*/download', ['image_id' => $row->getId()]),
            $this->__('Download'),
        );

        $links[] = sprintf(
            '<a href="%s" onclick="return confirm(\'%s\')">%s</a>',
            $this->getUrl('*/*/delete', [
                'image_id' => $row->getId(),
                'form_key' => Mage::getSingleton('core/session')->getFormKey(),
            ]),
            $this->jsQuoteEscape($this->__('Are you sure?')),
            $this->__('Delete'),
        );

        return implode(' | ', $links);
    }
}
