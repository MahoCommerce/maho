<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_Block_System_Config_Form_Field_Heading_Packagecheck extends Mage_Adminhtml_Block_System_Config_Form_Field_Heading implements \Maho\Data\Form\Element\Renderer\RendererInterface
{
    #[\Override]
    public function render(\Maho\Data\Form\Element\AbstractElement $element): string
    {
        $originalData = $element->getOriginalData();
        $package = $originalData['mandatory_package'] ?? null;
        if ($package && !\Composer\InstalledVersions::isInstalled($package)) {
            $element->setLabel($element->getLabel() . "<br> ⚠️ Install $package");
        }

        return parent::render($element);
    }
}
