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
 * This class is kept for backward compatibility but methods are now stubs
 */
class Mage_Usa_Model_Shipping_Carrier_Dhl_Label_Pdf_PageBuilder
{
    /**
     * PDF Page reference (deprecated)
     *
     * @var mixed
     * @deprecated No longer used with HTML/CSS approach
     */
    protected $_page;

    /**
     * Font references (deprecated)
     *
     * @var mixed
     * @deprecated No longer used with HTML/CSS approach
     */
    protected $_fontNormal;
    protected $_fontBold;

    /**
     * Create font instances (deprecated)
     *
     * @deprecated No longer needed with HTML/CSS approach
     */
    public function __construct()
    {
        // Legacy constructor - no longer creates fonts
        $this->_fontNormal = null;
        $this->_fontBold = null;
    }

    /**
     * Get Page (deprecated)
     *
     * @return mixed
     * @deprecated No longer used with HTML/CSS approach
     */
    public function getPage()
    {
        return $this->_page;
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
        $this->_page = $page;
        return $this;
    }

    /**
     * All drawing methods are now deprecated and return $this for compatibility
     */

    /** @deprecated */
    public function addProductName($name)
    {
        return $this;
    }

    /** @deprecated */
    public function addProductContentCode($code)
    {
        return $this;
    }

    /** @deprecated */
    public function addUnitId($id)
    {
        return $this;
    }

    /** @deprecated */
    public function addReferenceData($data)
    {
        return $this;
    }

    /** @deprecated */
    public function addSenderInfo($sender)
    {
        return $this;
    }

    /** @deprecated */
    public function addOriginInfo($origin)
    {
        return $this;
    }

    /** @deprecated */
    public function addReceiveInfo($receiver)
    {
        return $this;
    }

    /** @deprecated */
    public function addDestinationFacilityCode($country, $area, $facility)
    {
        return $this;
    }

    /** @deprecated */
    public function addServiceFeaturesCodes()
    {
        return $this;
    }

    /** @deprecated */
    public function addDeliveryDateCode()
    {
        return $this;
    }

    /** @deprecated */
    public function addShipmentInformation($shipment)
    {
        return $this;
    }

    /** @deprecated */
    public function addDateInfo($date)
    {
        return $this;
    }

    /** @deprecated */
    public function addWeightInfo($weight, $unit)
    {
        return $this;
    }

    /** @deprecated */
    public function addWaybillBarcode($number, $barcode)
    {
        return $this;
    }

    /** @deprecated */
    public function addRoutingBarcode($code, $dataId, $barcode)
    {
        return $this;
    }

    /** @deprecated */
    public function addBorder()
    {
        return $this;
    }

    /** @deprecated */
    public function addPieceNumber($pieceNum, $totalPieces)
    {
        return $this;
    }

    /** @deprecated */
    public function addContentInfo($content)
    {
        return $this;
    }

    /** @deprecated */
    public function addPieceIdBarcode($dataId, $license, $barcode)
    {
        return $this;
    }

    /**
     * Legacy method stubs for any other drawing operations
     */
    public function __call($method, $args)
    {
        // Return $this for any unknown method calls to maintain fluent interface
        return $this;
    }
}
