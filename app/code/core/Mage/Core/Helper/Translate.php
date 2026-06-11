<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

class Mage_Core_Helper_Translate extends Mage_Core_Helper_Abstract
{
    protected $_moduleName = 'Mage_Core';

    /**
     * Save translation data to database for specific area
     *
     * @param array  $translate
     * @param string $area
     * @param string $returnType
     * @return string
     */
    public function apply($translate, $area, $returnType = 'json')
    {
        try {
            if ($area) {
                Mage::getDesign()->setArea($area);
            }
            Mage::getModel('core/translate_inline')->processAjaxPost($translate);
            return $returnType === 'json'
                ? Mage::helper('core')->jsonEncode(['success' => true])
                : true;
        } catch (Exception $e) {
            return $returnType === 'json' // @phpstan-ignore identical.alwaysTrue
                ? Mage::helper('core')->jsonEncode(['error' => true, 'message' => $e->getMessage()])
                : false;
        }
    }
}
