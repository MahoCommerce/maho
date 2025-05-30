<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Mage_Adminhtml_Block_Directory_Regionname_Grid_Renderer_Country extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract
{
    #[\Override]
    public function render(Varien_Object $row): string
    {
        $countryId = $row->getCountryId();
        $countryName = Mage::app()->getLocale()->getCountryTranslation($countryId);

        return $this->escapeHtml($countryName . ' (' . $countryId . ')');
    }
}
