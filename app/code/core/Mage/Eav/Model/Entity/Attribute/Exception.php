<?php

/**
 * SPDX-FileCopyrightText: 2019-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Eav
 */

declare(strict_types=1);

class Mage_Eav_Model_Entity_Attribute_Exception extends Exception
{
    /**
     * Eav entity attribute
     *
     * @var string
     */
    protected $_attributeCode;

    /**
     * Eav entity attribute part
     * attribute|backend|frontend|source
     *
     * @var string
     */
    protected $_part;

    /**
     * Set Eav entity attribute
     *
     * @param string $attribute
     * @return $this
     */
    public function setAttributeCode($attribute)
    {
        $this->_attributeCode = $attribute;
        return $this;
    }

    /**
     * Set Eav entity attribute type
     *
     * @param string $part
     * @return $this
     */
    public function setPart($part)
    {
        $this->_part = $part;
        return $this;
    }

    /**
     * Retrieve Eav entity attribute
     *
     * @return string
     */
    public function getAttributeCode()
    {
        return $this->_attributeCode;
    }

    /**
     * Retrieve Eav entity attribute part
     *
     * @return string
     */
    public function getPart()
    {
        return $this->_part;
    }
}
