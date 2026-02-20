<?php

/**
 * Maho
 *
 * @package    MahoLib
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\Data\Form\Filter;

use DateTime as PhpDateTime;
use Exception;
use Maho\Data\Form\Filter\FilterInterface;

class Datetime extends Date
{
    /**
     * Initialize filter with datetime format default
     *
     * @param string $format    DateTime input/output format (now defaults to PHP datetime format)
     * @param string $locale
     */
    public function __construct($format = null, $locale = null)
    {
        if (is_null($format)) {
            $format = \Mage_Core_Model_Locale::DATETIME_FORMAT;
        }
        parent::__construct($format, $locale);
    }

    /**
     * Returns the result of filtering $value
     *
     * @param string|null $value
     * @return string|null
     */
    #[\Override]
    public function inputFilter($value)
    {
        if ($value === null || $value === '') {
            return $value;
        }

        // HTML5 datetime-local inputs send ISO format, validate and return as-is
        if (\Mage_Core_Model_Locale::isValidDate($value)) {
            return $value;
        }

        // For backward compatibility, try to parse and reformat invalid datetimes
        try {
            $date = new PhpDateTime($value);
            return $date->format(\Mage_Core_Model_Locale::DATETIME_FORMAT);
        } catch (\Exception $e) {
            // Invalid datetime, return original value (will likely cause validation error downstream)
            return $value;
        }
    }

    /**
     * Returns the result of filtering $value
     *
     * @param string|null $value
     * @return string
     */
    #[\Override]
    public function outputFilter($value)
    {
        if ($value === null || $value === '') {
            return $value;
        }

        // For HTML5 datetime-local inputs, output should be in ISO format
        try {
            $date = new PhpDateTime($value);
            return $date->format(\Mage_Core_Model_Locale::DATETIME_FORMAT);
        } catch (\Exception $e) {
            // Invalid datetime, return original value
            return $value;
        }
    }
}
