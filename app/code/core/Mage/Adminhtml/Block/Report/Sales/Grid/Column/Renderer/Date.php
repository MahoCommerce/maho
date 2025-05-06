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
    #[\Override]
    protected function _getFormat(): string
    {
        $format = $this->getColumn()->getFormat();
        if (!$format) {
            if (is_null(self::$_format)) {
                $localeCode = Mage::app()->getLocale()->getLocaleCode();
                $generator = new IntlDatePatternGenerator($localeCode);
                self::$_format = match ($this->getColumn()->getPeriodType()) {
                    'month' => $generator->getBestPattern('yM'),
                    'year' => $generator->getBestPattern('y'),
                    default => Mage::app()->getLocale()->getDateFormat(Mage_Core_Model_Locale::FORMAT_TYPE_MEDIUM),
                };
            }
            $format = self::$_format;
        }
        return $format;
    }

    #[\Override]
    public function render(Varien_Object $row): string
    {
        if ($data = $row->getData($this->getColumn()->getIndex())) {
            $dateFormat = match ($this->getColumn()->getPeriodType()) {
                'month' => 'yyyy-MM',
                'year' => 'yyyy',
                default => 'yyyy-MM-dd',
            };

            $format = $this->_getFormat();
            try {
                $data = ($this->getColumn()->getGmtoffset())
                    ? Mage::app()->getLocale()->date($data, $dateFormat)->toString($format)
                    : Mage::getSingleton('core/locale')->date($data, $dateFormat, null, false)->toString($format);
            } catch (Exception $e) {
                $data = ($this->getColumn()->getTimezone())
                    ? Mage::app()->getLocale()->date($data, $dateFormat)->toString($format)
                    : Mage::getSingleton('core/locale')->date($data, $dateFormat, null, false)->toString($format);
            }
            return $data;
        }
        return $this->getColumn()->getDefault();
    }
}
