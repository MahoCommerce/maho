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

class Mage_Adminhtml_Block_System_Config_Form_Field_Heading extends Mage_Adminhtml_Block_Abstract implements \Maho\Data\Form\Element\Renderer\RendererInterface
{
    /**
     * Render element html
     *
     * @return string
     */
    #[\Override]
    public function render(\Maho\Data\Form\Element\AbstractElement $element)
    {
        $originalData = $element->getOriginalData();
        $package = $originalData['mandatory_package'] ?? '';
        if ($package && !\Composer\InstalledVersions::isInstalled($package)) {
            $warning = "⚠️ Install <code>$package</code>";
            $label = $element->getLabel();
            $element->setLabel(($label ? "$label<br>" : '') . $warning);
        }

        return sprintf(
            '<tr class="system-fieldset-sub-head" id="row_%s"><td colspan="5"><h4 id="%s">%s</h4></td></tr>',
            $element->getHtmlId(),
            $element->getHtmlId(),
            $element->getLabel(),
        );
    }
}
