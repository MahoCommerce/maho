<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Cms
 */

class Mage_Cms_Model_Observer
{
    /**
     * Modify No Route Forward object
     *
     * @return $this
     */
    #[Maho\Config\Observer('controller_action_noroute', area: 'frontend')]
    public function noRoute(\Maho\Event\Observer $observer)
    {
        $observer->getEvent()->getStatus()
            ->setLoaded(true)
            ->setForwardModule('cms')
            ->setForwardController('index')
            ->setForwardAction('noRoute');
        return $this;
    }

    /**
     * On a preview request, put the draft in the block that shows the edited content, and
     * mark that block for the scroll sync of the editor. Other requests return at once.
     */
    #[Maho\Config\Observer('core_block_abstract_to_html_after', area: 'frontend')]
    public function markPreviewTarget(\Maho\Event\Observer $observer): void
    {
        $preview = Mage::registry(Mage_Cms_Model_Preview::REGISTRY_KEY);
        if (!is_array($preview)) {
            return;
        }
        $block = $observer->getEvent()->getBlock();
        $transport = $observer->getEvent()->getTransport();
        if (!$block instanceof Mage_Core_Block_Abstract || !$transport instanceof \Maho\DataObject
            || !$preview['provider']->isTarget($block, $preview['draft'])
        ) {
            return;
        }

        $transport->setHtml(
            Mage_Cms_Model_Preview::MARKER_START
            . $preview['provider']->getTargetHtml($block, (string) $transport->getHtml(), $preview['draft'])
            . Mage_Cms_Model_Preview::MARKER_END,
        );
    }
}
