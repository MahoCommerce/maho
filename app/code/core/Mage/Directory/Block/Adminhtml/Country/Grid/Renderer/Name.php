<?php

/**
 * Maho
 *
 * @package    Mage_Directory
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Directory_Block_Adminhtml_Country_Grid_Renderer_Name extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract
{
    #[\Override]
    public function render(Varien_Object $row): string
    {
        $countryId = $row->getCountryId();
        $countryName = Mage::app()->getLocale()->getCountryTranslation($countryId);

        return $this->escapeHtml($countryName ?: $countryId);
    }
}