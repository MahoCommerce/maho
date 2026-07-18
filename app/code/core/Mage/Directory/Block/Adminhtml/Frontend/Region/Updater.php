<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Directory
 */

class Mage_Directory_Block_Adminhtml_Frontend_Region_Updater extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    /**
     * @return string
     */
    #[\Override]
    protected function _getElementHtml(\Maho\Data\Form\Element\AbstractElement $element)
    {
        $html = parent::_getElementHtml($element);
        $html .= "<script>var updater = new RegionUpdater('tax_defaults_country', 'tax_region', 'tax_defaults_region', " . Mage::helper('directory')->getRegionJsonByStore() . ", 'disable');</script>";

        return $html;
    }
}
