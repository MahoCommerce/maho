<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_ConfigurableSwatches
 */

/**
 * Class Mage_ConfigurableSwatches_Block_Catalog_Product_View_Type_Configurable_Swatches
 *
 * @package    Mage_ConfigurableSwatches
 */
class Mage_ConfigurableSwatches_Block_Catalog_Product_View_Type_Configurable_Swatches extends Mage_Core_Block_Template
{
    protected $_initDone = false;

    /**
     * Determine if the renderer should be used
     *
     * @param Mage_Catalog_Model_Product_Type_Configurable_Attribute $attribute
     * @param string $jsonConfig
     * @return bool
     */
    public function shouldRender($attribute, $jsonConfig)
    {
        if (Mage::helper('configurableswatches')->isEnabled()) {
            if (Mage::helper('configurableswatches')->attrIsSwatchType($attribute->getProductAttribute())) {
                $this->_init($jsonConfig);
                return true;
            }
        }
        return false;
    }

    /**
     * Set one-time data on the renderer
     *
     * @param string $jsonConfig
     */
    protected function _init($jsonConfig)
    {
        if (!$this->_initDone) {
            $this->setJsonConfig($jsonConfig);

            $dimHelper = Mage::helper('configurableswatches/swatchdimensions');
            $this->setSwatchInnerWidth(
                $dimHelper->getInnerWidth(Mage_ConfigurableSwatches_Helper_Swatchdimensions::AREA_DETAIL),
            );
            $this->setSwatchInnerHeight(
                $dimHelper->getInnerHeight(Mage_ConfigurableSwatches_Helper_Swatchdimensions::AREA_DETAIL),
            );
            $this->setSwatchOuterWidth(
                $dimHelper->getOuterWidth(Mage_ConfigurableSwatches_Helper_Swatchdimensions::AREA_DETAIL),
            );
            $this->setSwatchOuterHeight(
                $dimHelper->getOuterHeight(Mage_ConfigurableSwatches_Helper_Swatchdimensions::AREA_DETAIL),
            );

            $this->_initDone = true;
        }
    }

    public function setJsonConfig(?string $value): static
    {
        return $this->setData('json_config', $value);
    }

    public function setSwatchInnerHeight(?int $value): static
    {
        return $this->setData('swatch_inner_height', $value);
    }

    public function setSwatchInnerWidth(?int $value): static
    {
        return $this->setData('swatch_inner_width', $value);
    }

    public function setSwatchOuterHeight(?int $value): static
    {
        return $this->setData('swatch_outer_height', $value);
    }

    public function setSwatchOuterWidth(?int $value): static
    {
        return $this->setData('swatch_outer_width', $value);
    }
}
