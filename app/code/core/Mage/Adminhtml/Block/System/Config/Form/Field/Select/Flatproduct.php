<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

/**
 * @deprecated since 26.5 Flat Catalog will be removed in a future version
 */
class Mage_Adminhtml_Block_System_Config_Form_Field_Select_Flatproduct extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    /**
     * Retrieve Element HTML
     *
     * @return string
     */
    #[\Override]
    protected function _getElementHtml(\Maho\Data\Form\Element\AbstractElement $element)
    {
        // Flat catalog is only supported on MySQL
        $adapter = Mage::getSingleton('core/resource')->getConnection('core_write');
        if (!($adapter instanceof Maho\Db\Adapter\Pdo\Mysql)) {
            $element->setDisabled(true)
                ->setValue(0)
                ->setComment('Flat catalog is only supported with MySQL database engine.');
        } elseif (!Mage::helper('catalog/product_flat')->isBuilt()) {
            $element->setDisabled(true)
                ->setValue(0);
        }

        if ($element->getValue()) {
            $deprecation = 'Flat Catalog is deprecated and will be removed in a future version. Please consider disabling it.';
            $comment = $element->getComment();
            $element->setComment('<span style="color:red; font-weight:bold;">' . $deprecation . '</span>' . ($comment ? '<br>' . $comment : ''));
        }

        return parent::_getElementHtml($element);
    }
}
