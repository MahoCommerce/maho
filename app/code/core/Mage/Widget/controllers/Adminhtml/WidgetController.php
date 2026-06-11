<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Widget
 */

class Mage_Widget_Adminhtml_WidgetController extends Mage_Adminhtml_Controller_Action
{
    /**
     * ACL resource
     * @see Mage_Adminhtml_Controller_Action::_isAllowed()
     */
    public const ADMIN_RESOURCE = 'cms/widget_instance';

    /**
     * Wysiwyg widget plugin main page
     */
    #[Maho\Config\Route('/admin/widget/index')]
    public function indexAction(): void
    {
        // save extra params for widgets insertion form
        $skipped = $this->getRequest()->getParam('skip_widgets');
        if (is_string($skipped)) {
            $skipped = Mage::getSingleton('widget/widget_config')->decodeWidgetsFromQuery($skipped);
        }

        Mage::register('skip_widgets', is_array($skipped) ? $skipped : []);
        $this->loadLayout('empty')->renderLayout();
    }

    /**
     * Ajax responder for loading plugin options form
     */
    #[Maho\Config\Route('/admin/widget/loadOptions')]
    public function loadOptionsAction(): void
    {
        try {
            $this->loadLayout('empty');
            if ($paramsJson = $this->getRequest()->getParam('widget')) {
                $request = Mage::helper('core')->jsonDecode($paramsJson);
                if (is_array($request)) {
                    $optionsBlock = $this->getLayout()->getBlock('wysiwyg_widget.options');
                    if (isset($request['widget_type'])) {
                        $optionsBlock->setWidgetType($request['widget_type']);
                    }
                    if (isset($request['values'])) {
                        $optionsBlock->setWidgetValues($request['values']);
                    }
                }
                $this->renderLayout();
            }
        } catch (Mage_Core_Exception $e) {
            $this->getResponse()->setBodyJson(['error' => true, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Format widget pseudo-code for inserting into wysiwyg editor
     */
    #[Maho\Config\Route('/admin/widget/buildWidget')]
    public function buildWidgetAction(): void
    {
        $type = $this->getRequest()->getPost('widget_type');
        $params = $this->getRequest()->getPost('parameters', []);
        $html = Mage::getSingleton('widget/widget')->getWidgetDeclaration($type, $params);
        $this->getResponse()->setBody($html);
    }
}
