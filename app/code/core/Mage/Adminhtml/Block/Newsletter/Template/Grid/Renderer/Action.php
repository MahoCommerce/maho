<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_Newsletter_Template_Grid_Renderer_Action extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Action
{
    /**
     * Renderer for "Action" column in Newsletter templates grid
     *
     * @param Mage_Newsletter_Model_Template $row
     * @return string
     */
    #[\Override]
    public function render(\Maho\DataObject $row)
    {
        if ($row->isValidForSend()) {
            $actions[] = [
                'url' => $this->getUrl('*/newsletter_queue/edit', ['template_id' => $row->getId()]),
                'caption' => Mage::helper('newsletter')->__('Queue Newsletter...'),
            ];
        }

        $actions[] = [
            'url'     => $this->getUrl('*/*/preview', ['id' => $row->getId()]),
            'popup'   => true,
            'caption' => Mage::helper('newsletter')->__('Preview'),
        ];

        $this->getColumn()->setActions($actions);

        return parent::render($row);
    }
}
