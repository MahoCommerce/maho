<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Date extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract
{
    protected $_defaultWidth = 160;
    protected static ?IntlDateFormatter $_formatter = null;

    protected function _getFormatter(): IntlDateFormatter
    {
        if (is_null(self::$_formatter)) {
            $timezone = Mage::app()->getStore()->getConfig(Mage_Core_Model_Locale::XML_PATH_DEFAULT_TIMEZONE);
            self::$_formatter = new IntlDateFormatter(
                Mage::app()->getLocale()->getLocaleCode(),
                IntlDateFormatter::MEDIUM,
                IntlDateFormatter::NONE,
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
        if ($data = $row->getData($this->getColumn()->getIndex())) {
            try {
                if ($this->getColumn()->getGmtoffset()) {
                    $dateObj = Mage::app()->getLocale()->utcToStore(null, $data);
                } else {
                    $dateObj = new DateTime($data);
                }

                return $this->_getFormatter()->format($dateObj);
            } catch (Exception $e) {
                // Fallback to simple format
                try {
                    if ($this->getColumn()->getTimezone()) {
                        $dateObj = Mage::app()->getLocale()->utcToStore(null, $data);
                    } else {
                        $dateObj = new DateTime($data);
                    }
                    return $dateObj->format('M j, Y');
                } catch (Exception $e2) {
                    return $data;
                }
            }
        }
        return $this->getColumn()->getDefault();
    }
}
