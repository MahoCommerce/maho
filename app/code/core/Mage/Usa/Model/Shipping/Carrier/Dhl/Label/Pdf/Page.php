<?php

/**
 * Maho
 *
 * @package    Mage_Usa
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2025 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * DHL International (API v1.4) PDF Page
 *
 * @deprecated No longer extends PDF page classes - now uses HTML/CSS template approach
 * This class is kept for backward compatibility but methods are now stubs
 */
class Mage_Usa_Model_Shipping_Carrier_Dhl_Label_Pdf_Page
{
    /**
     * Constructor - no longer creates PDF page objects
     *
     * @param mixed $template Template or page size (deprecated, ignored for backward compatibility)
     * @deprecated No longer extends PDF page classes
     */
    public function __construct($template = null)
    {
        // Legacy constructor - $template parameter is ignored for backward compatibility
    }

    /**
     * Get text width (deprecated)
     *
     * @param string $text
     * @param mixed $font
     * @param int $fontSize
     * @return int
     * @deprecated No longer used with HTML/CSS approach
     */
    public function getTextWidth($text, $font, $fontSize)
    {
        // Return approximate width for compatibility
        return (int) (strlen($text) * ($fontSize * 0.6));
    }

    /**
     * Compatibility method for drawing operations and property access
     *
     * @param string $method
     * @param array $args
     * @return $this
     * @deprecated All drawing methods are deprecated with HTML/CSS approach
     */
    public function __call($method, $args)
    {
        // Return $this for any drawing method calls to maintain compatibility
        return $this;
    }

    /**
     * Legacy property access for compatibility
     *
     * @param string $name
     * @return null
     * @deprecated No longer used with HTML/CSS approach
     */
    public function __get($name)
    {
        return null;
    }

    /**
     * Legacy property setting for compatibility
     *
     * @param string $name
     * @param mixed $value
     * @deprecated No longer used with HTML/CSS approach
     */
    public function __set($name, $value)
    {
        // No-op for property setting
    }
}
