<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_Newsletter_Queue_Preview extends Mage_Adminhtml_Block_Widget
{
    #[\Override]
    protected function _toHtml()
    {
        /** @var Mage_Newsletter_Model_Template $template */
        $template = Mage::getModel('newsletter/template');

        if ($id = (int) $this->getRequest()->getParam('id')) {
            $queue = Mage::getModel('newsletter/queue');
            $queue->load($id);
            $template->setTemplateType($queue->getNewsletterType());
            $template->setTemplateText($queue->getNewsletterText());
            $template->setTemplateStyles($queue->getNewsletterStyles());
        } else {
            $template->setTemplateType($this->getRequest()->getParam('type'));
            $template->setTemplateText($this->getRequest()->getParam('text'));
            $template->setTemplateStyles($this->getRequest()->getParam('styles'));
        }
        $storeId = (int) $this->getRequest()->getParam('store_id');
        if (!$storeId) {
            $storeId = Mage::app()->getAnyStoreView()->getId();
        }

        \Maho\Profiler::start('newsletter_queue_proccessing');
        $vars = [];

        $vars['subscriber'] = Mage::getModel('newsletter/subscriber');

        $template->emulateDesign($storeId);
        $templateProcessed = $template->getProcessedTemplate($vars, true);
        $template->revertDesign();

        if ($template->isPlain()) {
            $templateProcessed = '<pre>' . htmlspecialchars($templateProcessed) . '</pre>';
        }

        // Sanitize the resolved output (directives are now real URLs/values), then
        // decorate links. Sanitizing before directive resolution would mangle
        // {{...}} directives and leave their content unsanitized.
        $templateProcessed = $this->maliciousCodeFilter($templateProcessed);
        $templateProcessed = Mage::getSingleton('core/input_filter_maliciousCode')
            ->linkFilter($templateProcessed);

        \Maho\Profiler::stop('newsletter_queue_proccessing');

        return $templateProcessed;
    }
}
