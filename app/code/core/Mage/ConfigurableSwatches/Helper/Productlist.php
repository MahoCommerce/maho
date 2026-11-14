<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_ConfigurableSwatches
 */

class Mage_ConfigurableSwatches_Helper_Productlist extends Mage_Core_Helper_Abstract
{
    #[\Override]
    protected $_moduleName = 'Mage_ConfigurableSwatches';

    /**
     * IDs of the attributes that can give the swatches of a product in the listing
     *
     * @var list<int>|null
     */
    protected ?array $_swatchAttributeIds = null;

    /**
     * Attribute model to be used for swatches on product listing
     *
     * @var Mage_Catalog_Model_Product_Type_Configurable_Attribute|false|null
     */
    protected $_swatchAttribute = null;

    /**
     * The values of the listing swatch attributes that the product list is filtered by
     *
     * @var list<string>|null
     */
    protected ?array $_swatchAttributeFilteredValues = null;

    /**
     * Convert a catalog layer block with the right templates
     *
     * @param string $blockName
     */
    public function convertLayerBlock($blockName)
    {
        if (Mage::helper('configurableswatches')->isEnabled()
            && ($block = Mage::app()->getLayout()->getBlock($blockName))
            && $block instanceof Mage_Catalog_Block_Layer_View
        ) {
            // First, set a new template for the attribute that should show as a swatch
            if ($layer = $block->getLayer()) {
                foreach ($layer->getFilterableAttributes() as $attribute) {
                    if (Mage::helper('configurableswatches')->attrIsSwatchType($attribute)) {
                        $block->getChild($attribute->getAttributeCode() . '_filter')
                            ->setTemplate('configurableswatches/catalog/layer/filter/swatches.phtml');
                    }
                }
            }

            // Then set a specific renderer block for showing "currently shopping by" for the swatch attribute
            // (block class takes care of determining which attribute is applicable)
            if ($stateRenderersBlock = $block->getChild('state_renderers')) {
                $swatchRenderer = Mage::app()->getLayout()
                    ->addBlock('configurableswatches/catalog_layer_state_swatch', 'product_list.swatches');
                $swatchRenderer->setTemplate('configurableswatches/catalog/layer/state/swatch.phtml');
                $stateRenderersBlock->append($swatchRenderer);
            }
        }
    }

    /**
     * Get the IDs of the attributes that can give the swatches of a product in the listing, in the
     * order of the configuration. The attribute that comes first on the product wins, see
     * Mage_ConfigurableSwatches_Helper_Mediafallback::attachProductChildrenAttributeMapping().
     *
     * @return list<int>
     */
    public function getSwatchAttributeIds(): array
    {
        $this->_swatchAttributeIds ??= array_values(array_unique(array_filter(array_map(
            intval(...),
            explode(',', (string) Mage::getStoreConfig(Mage_ConfigurableSwatches_Helper_Data::CONFIG_PATH_LIST_SWATCH_ATTRIBUTE)),
        ))));
        return $this->_swatchAttributeIds;
    }

    /**
     * Get the ID of the first attribute of getSwatchAttributeIds()
     */
    public function getSwatchAttributeId(): ?int
    {
        return $this->getSwatchAttributeIds()[0] ?? null;
    }

    /**
     * Get the model of the first attribute of getSwatchAttributeIds()
     *
     * @return Mage_Catalog_Model_Product_Type_Configurable_Attribute|Mage_Eav_Model_Entity_Attribute_Abstract|false
     */
    public function getSwatchAttribute()
    {
        $this->_swatchAttribute ??= Mage::getSingleton('eav/config')
            ->getAttribute('catalog_product', $this->getSwatchAttributeId());
        return $this->_swatchAttribute;
    }

    /**
     * Get the values of the listing swatch attributes that the product list is filtered by
     *
     * @return list<string>
     */
    protected function _getSwatchAttributeFilteredValues(): array
    {
        if ($this->_swatchAttributeFilteredValues === null) {
            $this->_swatchAttributeFilteredValues = [];
            foreach (Mage::getSingleton('catalog/layer')->getState()->getFilters() as $filter) {
                if (!$filter->getFilter()->hasAttributeModel()) {
                    continue;
                }

                $attributeId = (int) $filter->getFilter()->getAttributeModel()->getAttributeId();
                if (in_array($attributeId, $this->getSwatchAttributeIds(), true)) {
                    array_push($this->_swatchAttributeFilteredValues, ...array_map(strval(...), (array) $filter->getValue()));
                }
            }
        }
        return $this->_swatchAttributeFilteredValues;
    }

    /**
     * See if the swatch matches a filter currently applied to the product list.
     */
    public function swatchMatchesFilter(int|string $optionId): bool
    {
        return in_array((string) $optionId, $this->_getSwatchAttributeFilteredValues(), true);
    }
}
