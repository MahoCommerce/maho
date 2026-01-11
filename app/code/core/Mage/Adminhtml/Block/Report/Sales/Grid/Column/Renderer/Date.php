<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_Block_Report_Sales_Grid_Column_Renderer_Date extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Date
{
    protected static ?string $_format = null;
    protected static ?IntlDateFormatter $_formatter = null;

    protected function _getFormat(): string
    {
        $column = $this->getColumn();
        $format = $column->getFormat();
        if (!$format) {
            if (is_null(self::$_format)) {
                self::$_format = Mage::app()->getLocale()->getDateFormatByPeriodType($column->getPeriodType());
            }
            $format = self::$_format;
        }
        return $format;
    }

    #[\Override]
    protected function _getFormatter(): IntlDateFormatter
    {
        if (is_null(self::$_formatter)) {
            $icuPattern = $this->_getFormat();
            self::$_formatter = new IntlDateFormatter(
                Mage::app()->getLocale()->getLocaleCode(),
                IntlDateFormatter::NONE,
                IntlDateFormatter::NONE,
                null,
                null,
                $icuPattern,
            );
        }
        return self::$_formatter;
    }

    #[\Override]
    public function render(\Maho\DataObject $row): string
    {
        $column = $this->getColumn();
        if ($data = $row->getData($column->getIndex())) {
            $dateFormat = match ($column->getPeriodType()) {
                'month' => 'yyyy-MM',
                'year' => 'yyyy',
                default => 'yyyy-MM-dd',
            };

            try {
                $dateObj = ($column->getGmtoffset())
                    ? Mage::app()->getLocale()->dateImmutable($data, $dateFormat)
                    : Mage::app()->getLocale()->dateImmutable($data, $dateFormat, null, false);

                return $this->_getFormatter()->format($dateObj);
            } catch (Exception $e) {
                try {
                    $dateObj = ($column->getTimezone())
                        ? Mage::app()->getLocale()->dateImmutable($data, $dateFormat)
                        : Mage::app()->getLocale()->dateImmutable($data, $dateFormat, null, false);

                    return $this->_getFormatter()->format($dateObj);
                } catch (Exception $e2) {
                    // Final fallback: return raw data
                    return (string) $data;
                }
            }
        }
        return $column->getDefault();
    }
}
