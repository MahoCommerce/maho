<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    MahoLib
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Maho DB Statement Parameter
 *
 * Used to transmit specific information about parameter value binding to be bound the right
 * way to the query.
 * Most used properties and methods are defined in interface. Specific things for concrete DB adapter can be
 * transmitted using 'additional' property (\Maho\DataObject) as a container.
 */

namespace Maho\Db\Statement;

class Parameter
{
    /**
     * Actual parameter value
     */
    protected mixed $_value = null;

    /**
     * Value is a BLOB.
     *
     * A shortcut setting to notify DB adapter, that value must be bound in a default way, as adapter binds
     * BLOB data to query placeholders. If FALSE, then specific settings from $_dataType, $_length,
     * $_driverOptions will be used.
     */
    protected bool $_isBlob = false;

    /**
     * Data type to set to DB driver during parameter bind
     */
    protected mixed $_dataType = null;

    /**
     * Length to set to DB driver during parameter bind
     */
    protected mixed $_length = null;

    /**
     * Specific driver options to set to DB driver during parameter bind
     */
    protected mixed $_driverOptions = null;

    /**
     * Additional information to be used by DB adapter internally
     */
    protected ?\Maho\DataObject $_additional = null;

    public function __construct(mixed $value)
    {
        $this->_value = $value;
        $this->_additional = new \Maho\DataObject();
    }

    /**
     * Sets parameter value.
     */
    public function setValue(mixed $value): self
    {
        $this->_value = $value;
        return $this;
    }

    /**
     * Gets parameter value.
     */
    public function getValue(): mixed
    {
        return $this->_value;
    }

    /**
     * Sets, whether parameter is a BLOB.
     *
     * FALSE (default) means, that concrete binding options come in dataType, length and driverOptions properties.
     * TRUE means that DB adapter must ignore other options and use adapter's default options to bind this parameter
     * as a BLOB value.
     */
    public function setIsBlob(bool $isBlob): self
    {
        $this->_isBlob = $isBlob;
        return $this;
    }

    /**
     * Gets, whether parameter is a BLOB.
     * See setIsBlob() for returned value explanation.
     */
    public function getIsBlob(): bool
    {
        return $this->_isBlob;
    }

    /**
     * Sets data type option to be used during binding parameter value.
     */
    public function setDataType(mixed $dataType): self
    {
        $this->_dataType = $dataType;
        return $this;
    }

    /**
     * Gets data type option to be used during binding parameter value.
     */
    public function getDataType(): mixed
    {
        return $this->_dataType;
    }

    /**
     * Sets length option to be used during binding parameter value.
     */
    public function setLength(mixed $length): self
    {
        $this->_length = $length;
        return $this;
    }

    /**
     * Gets length option to be used during binding parameter value.
     */
    public function getLength(): mixed
    {
        return $this->_length;
    }

    /**
     * Sets specific driver options to be used during binding parameter value.
     */
    public function setDriverOptions(mixed $driverOptions): self
    {
        $this->_driverOptions = $driverOptions;
        return $this;
    }

    /**
     * Gets driver options to be used during binding parameter value.
     */
    public function getDriverOptions(): mixed
    {
        return $this->_driverOptions;
    }

    /**
     * Sets additional information for concrete DB adapter.
     * Set there any data you want to pass along with query parameter.
     */
    public function setAdditional(\Maho\DataObject $additional): self
    {
        $this->_additional = $additional;
        return $this;
    }

    /**
     * Gets additional information for concrete DB adapter.
     */
    public function getAdditional(): \Maho\DataObject
    {
        return $this->_additional;
    }

    /**
     * Returns representation of a object to be used in string contexts
     */
    #[\Override]
    public function __toString(): string
    {
        return (string) $this->_value;
    }

    /**
     * Returns representation of a object to be used in string contexts
     */
    public function toString(): string
    {
        return $this->__toString();
    }
}
