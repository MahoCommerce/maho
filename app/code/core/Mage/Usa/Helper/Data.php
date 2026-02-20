<?php

/**
 * Maho
 *
 * @package    Mage_Usa
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

use PhpUnitsOfMeasure\PhysicalQuantity\Mass;
use PhpUnitsOfMeasure\PhysicalQuantity\Length;

class Mage_Usa_Helper_Data extends Mage_Core_Helper_Abstract
{
    protected $_moduleName = 'Mage_Usa';

    /**
     * Convert weight in different measure types
     *
     * @param  mixed $value
     * @param  string $sourceWeightMeasure
     * @param  string $toWeightMeasure
     * @return float|null
     */
    public function convertMeasureWeight($value, $sourceWeightMeasure, $toWeightMeasure)
    {
        if ($value) {
            $mass = new Mass($value, $sourceWeightMeasure);
            return $mass->toUnit($toWeightMeasure);
        }
        return null;
    }

    /**
     * Convert dimensions in different measure types
     *
     * @param  mixed $value
     * @param  string $sourceDimensionMeasure
     * @param  string $toDimensionMeasure
     * @return float|null
     */
    public function convertMeasureDimension($value, $sourceDimensionMeasure, $toDimensionMeasure)
    {
        if ($value) {
            $length = new Length($value, $sourceDimensionMeasure);
            return $length->toUnit($toDimensionMeasure);
        }
        return null;
    }

    /**
     * Get name of measure by its type
     *
     * @param  $key
     * @return string
     */
    public function getMeasureWeightName($key)
    {
        $units = Mass::listAllUnits();
        if (isset($units[$key])) {
            return $key; // Return the unit name itself
        }
        // Check aliases
        foreach ($units as $unit => $aliases) {
            if (in_array($key, $aliases)) {
                return $unit;
            }
        }
        return '';
    }

    /**
     * Get name of measure by its type
     *
     * @param  $key
     * @return string
     */
    public function getMeasureDimensionName($key)
    {
        $units = Length::listAllUnits();
        if (isset($units[$key])) {
            return $key; // Return the unit name itself
        }
        // Check aliases
        foreach ($units as $unit => $aliases) {
            if (in_array($key, $aliases)) {
                return $unit;
            }
        }
        return '';
    }

    /**
     * Define if we need girth parameter in the package window
     *
     * @param string $shippingMethod
     * @return bool
     */
    public function displayGirthValue($shippingMethod)
    {
        if (in_array($shippingMethod, [
            'usps_0_FCLE', // First-Class Mail Large Envelope
            'usps_1',      // Priority Mail
            'usps_2',      // Priority Mail Express Hold For Pickup
            'usps_3',      // Priority Mail Express
            'usps_4',      // Standard Post
            'usps_6',      // Media Mail
            'usps_INT_1',  // Priority Mail Express International
            'usps_INT_2',  // Priority Mail International
            'usps_INT_4',  // Global Express Guaranteed (GXG)
            'usps_INT_7',  // Global Express Guaranteed Non-Document Non-Rectangular
            'usps_INT_8',  // Priority Mail International Flat Rate Envelope
            'usps_INT_9',  // Priority Mail International Medium Flat Rate Box
            'usps_INT_10', // Priority Mail Express International Flat Rate Envelope
            'usps_INT_11', // Priority Mail International Large Flat Rate Box
            'usps_INT_12', // USPS GXG Envelopes
            'usps_INT_14', // First-Class Mail International Large Envelope
            'usps_INT_16', // Priority Mail International Small Flat Rate Box
            'usps_INT_20', // Priority Mail International Small Flat Rate Envelope
            'usps_INT_26', // Priority Mail Express International Flat Rate Boxes
        ])
        ) {
            return true;
        }
        return false;
    }

    /**
     * Validate ups type value
     *
     * @param string $valueForCheck ups type value for check
     *
     * @return bool
     */
    public function validateUpsType($valueForCheck)
    {
        $result = false;
        $sourceModel = Mage::getSingleton('usa/shipping_carrier_ups_source_type');
        foreach ($sourceModel->toOptionArray() as $allowedValue) {
            if (isset($allowedValue['value']) && $allowedValue['value'] == $valueForCheck) {
                $result = true;
                break;
            }
        }
        return $result;
    }
}
