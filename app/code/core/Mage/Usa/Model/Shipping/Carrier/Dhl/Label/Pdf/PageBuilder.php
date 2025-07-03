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
 * DHL International (API v1.4) Page Builder
 *
 * @deprecated No longer used with HTML/CSS template approach
 * This class is kept for backward compatibility but all methods are now no-ops
 */
class Mage_Usa_Model_Shipping_Carrier_Dhl_Label_Pdf_PageBuilder
{
    /**
     * Create font instances (deprecated)
     *
     * @deprecated No longer needed with HTML/CSS approach
     */
    public function __construct()
    {
        // Legacy constructor - no longer creates fonts
    }

    /**
     * Get Page (deprecated)
     *
     * @return null
     * @deprecated No longer used with HTML/CSS approach
     */
    public function getPage()
    {
        return null;
    }

    /**
     * Set Page (deprecated)
     *
     * @param mixed $page
     * @return $this
     * @deprecated No longer used with HTML/CSS approach
     */
    public function setPage($page)
    {
        return $this;
    }

    /**
     * Legacy method stubs for any drawing operations
     * All drawing methods now return $this for fluent interface compatibility
     *
     * @return $this
     */
    public function __call(string $method, array $args): self
    {
        // Return $this for any method calls to maintain fluent interface
        return $this;
    }
}
