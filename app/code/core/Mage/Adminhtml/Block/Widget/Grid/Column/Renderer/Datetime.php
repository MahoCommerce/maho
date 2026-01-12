<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Datetime extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract
{
    protected static ?IntlDateFormatter $_formatter = null;

    protected function _getFormatter(?string $locale = null): IntlDateFormatter
    {
        $localeCode = $locale ?? Mage::app()->getLocale()->getLocaleCode();

        // Simple caching - could be enhanced to cache per locale if needed
        if (is_null(self::$_formatter)) {
            $timezone = Mage::app()->getStore()->getConfig(Mage_Core_Model_Locale::XML_PATH_DEFAULT_TIMEZONE);
            self::$_formatter = new IntlDateFormatter(
                $localeCode,
                IntlDateFormatter::MEDIUM,
                IntlDateFormatter::MEDIUM,
                $timezone,
            );
        }
        return self::$_formatter;
    }

    /**
     * Renders grid column
     *
     * @return  string
     */
    #[\Override]
    public function render(\Maho\DataObject $row)
    {
        if ($data = $this->_getValue($row)) {
            try {
                $useTimezone = $this->getColumn()->getUseTimezone() ?? true;
                $locale = $this->getColumn()->getLocale() ?? null;

                $dateObj = Mage::app()->getLocale()
                    ->date($data, Mage_Core_Model_Locale::DATETIME_FORMAT, $locale, $useTimezone);

                return $this->_getFormatter($locale)->format($dateObj);
            } catch (Exception $e) {
                // Fallback to simple format
                try {
                    $dateObj = Mage::app()->getLocale()
                        ->date($data, Mage_Core_Model_Locale::DATETIME_FORMAT);
                    return $dateObj->format('M j, Y, g:i:s A');
                } catch (Exception $e2) {
                    return $data;
                }
            }
        }
        return $this->getColumn()->getDefault();
    }
}
