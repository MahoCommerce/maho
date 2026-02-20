<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_Giftcard
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_Giftcard_Block_Adminhtml_System_Config_Form_Field_Barcode extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    public function isBarcodePackageInstalled(): bool
    {
        return Mage::helper('giftcard')->isBarcodePackageInstalled();
    }

    #[\Override]
    protected function _getElementHtml(Maho\Data\Form\Element\AbstractElement $element): string
    {
        if (!$this->isBarcodePackageInstalled()) {
            $element->setDisabled(true);
            $element->setValue(0);

            $html = parent::_getElementHtml($element);
            $html .= $this->_getNoticeHtml();

            return $html;
        }

        return parent::_getElementHtml($element);
    }

    /**
     * Get the notice HTML for missing package
     */
    protected function _getNoticeHtml(): string
    {
        return '<p class="note">⚠️ Install picqer/php-barcode-generator</p>';
    }
}
