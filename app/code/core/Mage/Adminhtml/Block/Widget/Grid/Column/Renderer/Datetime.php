<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Datetime extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract
{
    /**
     * Renders grid column
     *
     * @return  string
     */
    #[\Override]
    public function render(Varien_Object $row)
    {
        if ($data = $this->_getValue($row)) {
            // Use IntlDateFormatter directly instead of custom conversion
            try {
                $formatter = new IntlDateFormatter(
                    $this->getColumn()->getLocale() ?? Mage::app()->getLocale()->getLocaleCode(),
                    IntlDateFormatter::MEDIUM,
                    IntlDateFormatter::MEDIUM,
                );

                $useTimezone = $this->getColumn()->getUseTimezone() ?? true;
                $locale = $this->getColumn()->getLocale() ?? null;

                $dateObj = Mage::app()->getLocale()
                    ->date($data, Varien_Date::DATETIME_PHP_FORMAT, $locale, $useTimezone);

                return $formatter->format($dateObj);
            } catch (Exception $e) {
                // Fallback to simple format
                try {
                    $dateObj = Mage::app()->getLocale()
                        ->date($data, Varien_Date::DATETIME_PHP_FORMAT);
                    return $dateObj->format('M j, Y, g:i:s A');
                } catch (Exception $e2) {
                    return $data;
                }
            }
        }
        return $this->getColumn()->getDefault();
    }
}
