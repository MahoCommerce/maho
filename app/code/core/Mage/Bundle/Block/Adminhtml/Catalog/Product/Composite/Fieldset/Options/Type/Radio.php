<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Bundle
 */

declare(strict_types=1);

class Mage_Bundle_Block_Adminhtml_Catalog_Product_Composite_Fieldset_Options_Type_Radio extends Mage_Bundle_Block_Catalog_Product_View_Type_Bundle_Option_Radio
{
    /**
     * Set template
     */
    #[\Override]
    protected function _construct()
    {
        $this->setTemplate('bundle/product/composite/fieldset/options/type/radio.phtml');
    }

    /**
     * @param  string $elementId
     * @param  string $containerId
     * @return string
     */
    #[\Override]
    public function setValidationContainer($elementId, $containerId)
    {
        return '<script>
            document.getElementById(\'' . $elementId . '\').advaiceContainer = \'' . $containerId . '\';
            </script>';
    }
}
