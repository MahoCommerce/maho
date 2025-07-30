<?php

/**
 * Maho
 *
 * @package    Mage_Eav
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Eav_Model_Entity_Attribute_Backend_Datetime extends Mage_Eav_Model_Entity_Attribute_Backend_Abstract
{
    /**
     * Formatting date value before save
     *
     * Should set (bool, string) correct type for empty value from html form,
     * necessary for farther process, else date string
     *
     * @param Varien_Object $object
     * @throws Mage_Eav_Exception
     * @return $this
     */
    #[\Override]
    public function beforeSave($object)
    {
        $attributeName = $this->getAttribute()->getName();
        $_formated     = $object->getData($attributeName . '_is_formated');
        if (!$_formated && $object->hasData($attributeName)) {
            try {
                $value = $this->formatDate($object->getData($attributeName));
            } catch (Exception $e) {
                throw Mage::exception('Mage_Eav', Mage::helper('eav')->__('Invalid date'));
            }

            if (is_null($value)) {
                $value = $object->getData($attributeName);
            }

            $object->setData($attributeName, $value);
            $object->setData($attributeName . '_is_formated', true);
        }

        return $this;
    }

    /**
     * Prepare date for save in DB
     *
     * string format used from input fields (all date input fields need apply locale settings)
     * int value can be declared in code (this means we use valid date)
     *
     * @param   string|int $date
     * @return  string|null
     */
    public function formatDate($date)
    {
        if (empty($date)) {
            return null;
        }

        try {
            // Unix timestamp given
            if (preg_match('/^[0-9]+$/', $date)) {
                $dateTime = new DateTime();
                $dateTime->setTimestamp((int) $date);
                return $dateTime->format('Y-m-d H:i:s');
            }

            // ISO 8601 date format from native input (YYYY-MM-DD)
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                $dateTime = DateTime::createFromFormat(Mage_Core_Model_Locale::DATE_FORMAT, $date);
                // Validate the date is actually valid (not just format)
                if ($dateTime && $dateTime->format(Mage_Core_Model_Locale::DATE_FORMAT) === $date) {
                    return $dateTime->format(Mage_Core_Model_Locale::DATE_FORMAT . ' 00:00:00');
                }
                return null;
            }

            // ISO 8601 datetime-local format from native input (YYYY-MM-DDTHH:mm)
            if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/', $date)) {
                $dateTime = DateTime::createFromFormat('Y-m-d\\TH:i', substr($date, 0, 16));
                // Validate the datetime is actually valid (not just format)
                if ($dateTime && $dateTime->format('Y-m-d\\TH:i') === substr($date, 0, 16)) {
                    return $dateTime->format('Y-m-d H:i:s');
                }
                return null;
            }

            // MySQL datetime format (already correct)
            if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $date)) {
                return $date;
            }

            // Legacy: parse with locale format (compatibility mode)
            $format = Mage::app()->getLocale()->getDateTimeFormat(Mage_Core_Model_Locale::FORMAT_TYPE_SHORT);
            // Convert Zend format to PHP format for createFromFormat
            $phpFormat = str_replace(
                ['dd', 'MM', 'yyyy', 'HH', 'mm', 'ss'],
                ['d', 'm', 'Y', 'H', 'i', 's'],
                $format,
            );

            $dateTime = DateTime::createFromFormat($phpFormat, $date);
            return $dateTime ? $dateTime->format('Y-m-d H:i:s') : null;
        } catch (Exception $e) {
            return null;
        }
    }
}
