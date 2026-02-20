<?php

/**
 * Maho
 *
 * @package    Mage_Eav
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Eav_Model_Attribute_Data_Date extends Mage_Eav_Model_Attribute_Data_Abstract
{
    /**
     * Extract data from request and return value
     *
     * @return array|string|false
     */
    #[\Override]
    public function extractValue(Mage_Core_Controller_Request_Http $request)
    {
        $value = $this->_getRequestValue($request);
        return $value ? $this->_applyInputFilter($value) : false;
    }

    /**
     * Validate data
     * Return true or array of errors
     *
     * @param array|string $value
     * @return bool|array
     */
    #[\Override]
    public function validateValue($value)
    {
        $errors     = [];
        $attribute  = $this->getAttribute();
        $label      = $attribute->getStoreLabel();

        if ($attribute->getIsRequired() && empty($value)) {
            $errors[] = Mage::helper('eav')->__('"%s" is a required value.', $label);
        }

        if ($value === false) {
            // try to load original value and validate it
            $originalValue = $this->getEntity()->getDataUsingMethod($attribute->getAttributeCode());
            $value = $originalValue;

            // If the stored value is a datetime (contains time), strip time component for date validation
            if (is_string($value) && strlen($value) > 10 && str_contains($value, ' ')) {
                $value = substr($value, 0, 10);
            }
        }

        if (!$errors && !$attribute->getIsRequired() && empty($value)) {
            return true;
        }

        $result = $this->_validateInputRule($value);
        if ($result !== true) {
            $errors = array_merge($errors, $result);
        }

        //range validation
        $validateRules = $attribute->getValidateRules();
        if ((!empty($validateRules['date_range_min']) && (strtotime($value) < $validateRules['date_range_min']))
            || (!empty($validateRules['date_range_max']) && (strtotime($value) > $validateRules['date_range_max']))
        ) {
            if (!empty($validateRules['date_range_min']) && !empty($validateRules['date_range_max'])) {
                $errors[] = Mage::helper('customer')->__('Please enter a valid date between %s and %s at %s.', date('d/m/Y', $validateRules['date_range_min']), date('d/m/Y', $validateRules['date_range_max']), $label);
            } elseif (!empty($validateRules['date_range_min'])) {
                $errors[] = Mage::helper('customer')->__('Please enter a valid date equal to or greater than %s at %s.', date('d/m/Y', $validateRules['date_range_min']), $label);
            } elseif (!empty($validateRules['date_range_max'])) {
                $errors[] = Mage::helper('customer')->__('Please enter a valid date less than or equal to %s at %s.', date('d/m/Y', $validateRules['date_range_max']), $label);
            }
        }

        if (count($errors) == 0) {
            return true;
        }

        return $errors;
    }

    /**
     * Export attribute value to entity model
     *
     * @param array|string $value
     * @return $this
     */
    #[\Override]
    public function compactValue($value)
    {
        if ($value !== false && empty($value)) {
            $value = null;
        }
        $this->getEntity()->setDataUsingMethod($this->getAttribute()->getAttributeCode(), $value);

        return $this;
    }

    /**
     * Restore attribute value from SESSION to entity model
     *
     * @param array|string $value
     * @return $this
     */
    #[\Override]
    public function restoreValue($value)
    {
        return $this->compactValue($value);
    }

    /**
     * Return formatted attribute value from entity model
     *
     * @param string $format
     * @return string|array
     */
    #[\Override]
    public function outputValue($format = Mage_Eav_Model_Attribute_Data::OUTPUT_FORMAT_TEXT)
    {
        $value = $this->getEntity()->getData($this->getAttribute()->getAttributeCode());
        if ($value) {
            switch ($format) {
                case Mage_Eav_Model_Attribute_Data::OUTPUT_FORMAT_TEXT:
                case Mage_Eav_Model_Attribute_Data::OUTPUT_FORMAT_HTML:
                case Mage_Eav_Model_Attribute_Data::OUTPUT_FORMAT_PDF:
                    $this->_dateFilterFormat(Mage_Core_Model_Locale::FORMAT_TYPE_MEDIUM);
                    break;
            }
            $value = $this->_applyOutputFilter($value);
        }

        $this->_dateFilterFormat(Mage_Core_Model_Locale::FORMAT_TYPE_SHORT);

        return $value;
    }
}
