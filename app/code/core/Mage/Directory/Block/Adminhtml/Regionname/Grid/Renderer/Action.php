<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Directory
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Directory_Block_Adminhtml_Regionname_Grid_Renderer_Action extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract
{
    #[\Override]
    public function render(Varien_Object $row): string
    {
        $locale = $row->getLocale();
        $regionId = $row->getRegionId();

        $editUrl = $this->getUrl('*/*/edit', [
            'locale' => $locale,
            'region_id' => $regionId,
        ]);

        $deleteUrl = $this->getUrl('*/*/delete', [
            'locale' => $locale,
            'region_id' => $regionId,
        ]);

        $confirmMessage = Mage::helper('adminhtml')->__('Are you sure you want to delete this region name?');

        $html = '<a href="' . $editUrl . '">' . Mage::helper('adminhtml')->__('Edit') . '</a>';
        $html .= ' | ';
        $html .= '<a href="' . $deleteUrl . '" onclick="return confirm(\'' . $confirmMessage . '\')">' . Mage::helper('adminhtml')->__('Delete') . '</a>';

        return $html;
    }
}
