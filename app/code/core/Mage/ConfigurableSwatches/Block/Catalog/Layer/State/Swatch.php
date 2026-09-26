<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_ConfigurableSwatches
 */

declare(strict_types=1);

/**
 * Class Mage_ConfigurableSwatches_Block_Catalog_Layer_State_Swatch
 *
 * @package    Mage_ConfigurableSwatches
 */
class Mage_ConfigurableSwatches_Block_Catalog_Layer_State_Swatch extends Mage_Core_Block_Template
{
    protected $_initDone = false;

    /**
     * Determine if we should use this block to render a state filter
     *
     * @param Mage_Catalog_Model_Layer_Filter_Item $filter
     * @return bool
     */
    public function shouldRender($filter)
    {
        $helper = Mage::helper('configurableswatches');
        if ($helper->isEnabled() && $filter->getFilter()->hasAttributeModel()) {
            if ($helper->attrIsSwatchType($filter->getFilter()->getAttributeModel())) {
                $this->_init($filter);
                if ($this->getSwatchUrl()) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Set one-time data on the renderer
     *
     * @param Mage_Catalog_Model_Layer_Filter_Item $filter
     */
    protected function _init($filter)
    {
        if (!$this->_initDone) {
            $dimHelper = Mage::helper('configurableswatches/swatchdimensions');
            $this->setSwatchInnerWidth(
                $dimHelper->getInnerWidth(Mage_ConfigurableSwatches_Helper_Swatchdimensions::AREA_LAYER),
            );
            $this->setSwatchInnerHeight(
                $dimHelper->getInnerHeight(Mage_ConfigurableSwatches_Helper_Swatchdimensions::AREA_LAYER),
            );
            $this->setSwatchOuterWidth(
                $dimHelper->getOuterWidth(Mage_ConfigurableSwatches_Helper_Swatchdimensions::AREA_LAYER),
            );
            $this->setSwatchOuterHeight(
                $dimHelper->getOuterHeight(Mage_ConfigurableSwatches_Helper_Swatchdimensions::AREA_LAYER),
            );

            $swatchUrl = Mage::helper('configurableswatches/productimg')
                ->getGlobalSwatchUrl(
                    $filter,
                    $this->stripTags($filter->getLabel()),
                    $this->getSwatchInnerWidth(),
                    $this->getSwatchInnerHeight(),
                );
            $this->setSwatchUrl($swatchUrl);

            $this->_initDone = true;
        }
    }

    public function setJsonConfig(?string $value): static
    {
        return $this->setData('json_config', $value);
    }

    public function getSwatchInnerHeight(): ?int
    {
        $value = $this->getData('swatch_inner_height');
        return $value === null ? null : (int) $value;
    }

    public function setSwatchInnerHeight(?int $value): static
    {
        return $this->setData('swatch_inner_height', $value);
    }

    public function getSwatchInnerWidth(): ?int
    {
        $value = $this->getData('swatch_inner_width');
        return $value === null ? null : (int) $value;
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

    public function getSwatchUrl(): ?string
    {
        $value = $this->getData('swatch_url');
        return $value === null ? null : (string) $value;
    }

    public function setSwatchUrl(?string $value): static
    {
        return $this->setData('swatch_url', $value);
    }
}
