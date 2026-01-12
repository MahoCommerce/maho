<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_Block_Sitemap_Grid_Renderer_Action extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Action
{
    #[\Override]
    public function render(\Maho\DataObject $row)
    {
        $this->getColumn()->setActions([[
            'url'     => $this->getUrl('*/sitemap/generate', ['sitemap_id' => $row->getSitemapId()]),
            'caption' => Mage::helper('sitemap')->__('Generate'),
        ]]);
        return parent::render($row);
    }
}
