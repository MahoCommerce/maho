<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_Catalog_Product_Composite_Fieldset_Options extends Mage_Catalog_Block_Product_View_Options
{
    public function __construct()
    {
        parent::__construct();
        $this->addOptionRenderer(
            'default',
            'catalog/product_view_options_type_default',
            'catalog/product/composite/fieldset/options/type/default.phtml',
        );
    }

    /**
     * Get option html block
     *
     * @return string
     */
    #[\Override]
    public function getOptionHtml(Mage_Catalog_Model_Product_Option $option)
    {
        if (!empty($option['file_extension'])) {
            $option['file_extension'] = $this->escapeHtml($option['file_extension']);
        }
        $renderer = $this->getOptionRender(
            $this->getGroupOfOption($option->getType()),
        );
        if (is_null($renderer['renderer'])) {
            $renderer['renderer'] = $this->getLayout()->createBlock($renderer['block'])
                ->setTemplate($renderer['template'])
                ->setSkipJsReloadPrice(1);
        }
        return $renderer['renderer']
            ->setProduct($this->getProduct())
            ->setOption($option)
            ->toHtml();
    }
}
