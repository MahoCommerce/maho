<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_System_Email_Template_Preview extends Mage_Adminhtml_Block_Widget
{
    /**
     * Prepare html output
     *
     * @return string
     */
    #[\Override]
    protected function _toHtml()
    {
        // Start store emulation process
        // Since the Transactional Email preview process has no mechanism for selecting a store view to use for
        // previewing, use the default store view
        $defaultStoreId = Mage::app()->getDefaultStoreView()->getId();
        $appEmulation = Mage::getSingleton('core/app_emulation');
        $initialEnvironmentInfo = $appEmulation->startEnvironmentEmulation($defaultStoreId);

        /** @var Mage_Core_Model_Email_Template $template */
        $template = Mage::getModel('core/email_template');
        $id = (int) $this->getRequest()->getParam('id');
        if ($id) {
            $template->load($id);
        } else {
            $template->setTemplateType($this->getRequest()->getParam('type'));
            $template->setTemplateText($this->getRequest()->getParam('text'));
            $template->setTemplateStyles($this->getRequest()->getParam('styles'));
        }

        \Maho\Profiler::start('email_template_proccessing');
        $vars = [];

        $templateProcessed = $template->getProcessedTemplate($vars, true);

        if ($template->isPlain()) {
            $templateProcessed = '<pre>' . htmlspecialchars($templateProcessed) . '</pre>';
        } else {
            // Sanitize the resolved output (directives are now real URLs/values).
            // Sanitizing before directive resolution would mangle {{...}}
            // directives and leave their content unsanitized.
            $templateProcessed = $this->maliciousCodeFilter($templateProcessed);
        }

        \Maho\Profiler::stop('email_template_proccessing');

        // Stop store emulation process
        $appEmulation->stopEnvironmentEmulation($initialEnvironmentInfo);

        return $templateProcessed;
    }
}
