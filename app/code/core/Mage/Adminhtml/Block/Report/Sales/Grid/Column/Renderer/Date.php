<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_Report_Sales_Grid_Column_Renderer_Date extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Date
{
    /** @var array<string, IntlDateFormatter> */
    protected static array $_formatters = [];

    protected function _getFormat(): string
    {
        $column = $this->getColumn();
        return $column->getFormat()
            ?: Mage::app()->getLocale()->getDateFormatByPeriodType($column->getPeriodType());
    }

    #[\Override]
    protected function _getFormatter(): IntlDateFormatter
    {
        $locale = Mage::app()->getLocale()->getLocaleCode();
        $icuPattern = $this->_getFormat();
        $key = $locale . ':' . $icuPattern;

        return self::$_formatters[$key] ??= new IntlDateFormatter(
            $locale,
            IntlDateFormatter::NONE,
            IntlDateFormatter::NONE,
            null,
            null,
            $icuPattern,
        );
    }

    #[\Override]
    public function render(\Maho\DataObject $row): string
    {
        $column = $this->getColumn();
        if ($data = $row->getData($column->getIndex())) {
            // A year period holds only the year. DateTime reads '2018' as a clock time.
            if ($column->getPeriodType() === 'year' && !$column->getFormat()) {
                return (string) $data;
            }
            try {
                $dateObj = ($column->getGmtoffset())
                    ? Mage::app()->getLocale()->utcToStore(null, $data)
                    : new DateTime($data);

                return $this->_getFormatter()->format($dateObj);
            } catch (Exception) {
                try {
                    $dateObj = ($column->getTimezone())
                        ? Mage::app()->getLocale()->utcToStore(null, $data)
                        : new DateTime($data);

                    return $this->_getFormatter()->format($dateObj);
                } catch (Exception) {
                    // Final fallback: return raw data
                    return (string) $data;
                }
            }
        }
        return $column->getDefault();
    }
}
