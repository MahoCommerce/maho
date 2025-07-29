<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_Block_Report_Sales_Grid_Column_Renderer_Date extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Date
{
    protected static ?string $_format = null;

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
    public function render(Varien_Object $row): string
    {
        $column = $this->getColumn();
        if ($data = $row->getData($column->getIndex())) {
            $dateFormat = match ($column->getPeriodType()) {
                'month' => 'yyyy-MM',
                'year' => 'yyyy',
                default => 'yyyy-MM-dd',
            };

            $format = $this->_getFormat();
            try {
                $data = ($column->getGmtoffset())
                    ? Mage::app()->getLocale()->date($data, $dateFormat)->format($format)
                    : Mage::getSingleton('core/locale')->date($data, $dateFormat, null, false)->format($format);
            } catch (Exception $e) {
                $data = ($column->getTimezone())
                    ? Mage::app()->getLocale()->date($data, $dateFormat)->format($format)
                    : Mage::getSingleton('core/locale')->date($data, $dateFormat, null, false)->format($format);
            }
            return $data;
        }
        return $column->getDefault();
    }
}
