<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * System-config field renderer that appends a "⚠️ Install <package>"
 * hint to the field's comment when its declared <mandatory_package> isn't
 * installed. Sibling to the heading-row variant
 * (Mage_Adminhtml_Block_System_Config_Form_Field_Heading_Packagecheck) for
 * cases where the warning belongs inline with a regular field.
 */
class Mage_Adminhtml_Block_System_Config_Form_Field_Packagecheck extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    #[\Override]
    public function render(\Maho\Data\Form\Element\AbstractElement $element): string
    {
        $package = $element->getOriginalData()['mandatory_package'] ?? null;
        if ($package) {
            $warning = trim(Mage::helper('core')->packageInstallWarning($package));
            if ($warning !== '') {
                $current = (string) $element->getComment();
                $element->setComment($current === '' ? $warning : $current . '<br>' . $warning);
            }
        }
        return parent::render($element);
    }
}
