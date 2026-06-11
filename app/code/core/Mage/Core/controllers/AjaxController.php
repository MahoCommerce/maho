<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

class Mage_Core_AjaxController extends Mage_Core_Controller_Front_Action
{
    /**
     * Ajax action for inline translation
     */
    #[Maho\Config\Route('/core/ajax/translate', name: 'core.ajax.translate', methods: ['POST'])]
    public function translateAction(): void
    {
        $translation = $this->getRequest()->getPost('translate');
        $area = $this->getRequest()->getPost('area');

        /** @var Mage_Core_Model_Input_Filter_MaliciousCode $filter */
        $filter = Mage::getModel('core/input_filter_maliciousCode');
        foreach ($translation as &$item) {
            $item['custom'] = $filter->filter($item['custom']);
        }

        $response = Mage::helper('core/translate')->apply($translation, $area);
        $this->getResponse()->setBodyJson($response);
        $this->setFlag('', self::FLAG_NO_POST_DISPATCH, true);
    }
}
