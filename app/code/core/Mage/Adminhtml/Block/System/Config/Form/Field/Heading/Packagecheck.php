<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_Block_System_Config_Form_Field_Heading_Packagecheck extends Mage_Adminhtml_Block_System_Config_Form_Field_Heading implements Varien_Data_Form_Element_Renderer_Interface
{
    #[\Override]
    public function render(Varien_Data_Form_Element_Abstract $element): string
    {
        $originalData = $element->getOriginalData();
        $package = $originalData['mandatory_package'] ?? null;
        if ($package && !\Composer\InstalledVersions::isInstalled($package)) {
            $element->setLabel($element->getLabel() . "<br /> ⚠️ Install $package");
        }

        return parent::render($element);
    }
}
