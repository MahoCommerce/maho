<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_ImportExport
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2023 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * CSV import adapter
 *
 * @category   Mage
 * @package    Mage_ImportExport
 */
class Mage_ImportExport_Model_Import_Adapter_Csv extends Mage_ImportExport_Model_Import_Adapter_Abstract
{
    /**
     * Field delimiter.
     *
     * @var string
     */
    protected $_delimiter = ',';

    /**
     * Field enclosure character.
     *
     * @var string
     */
    protected $_enclosure = '"';

    /**
     * Source file handler.
     *
     * @var resource
     */
    protected $_fileHandler;

    /**
     * Close file handler on shutdown
     */
    #[\Override]
    public function destruct()
    {
        if (is_resource($this->_fileHandler)) {
            fclose($this->_fileHandler);
        }
    }

    /**
     * Method called as last step of object instance creation. Can be overrided in child classes.
     *
     * @return Mage_ImportExport_Model_Import_Adapter_Abstract
     */
    #[\Override]
    protected function _init()
    {
        $this->_fileHandler = fopen($this->_source, 'r');
        $this->rewind();
        return $this;
    }

    /**
     * Move forward to next element
     *
     * @return void Any returned value is ignored.
     */
    #[\ReturnTypeWillChange]
    #[\Override]
    public function next()
    {
        $this->_currentRow = fgetcsv($this->_fileHandler, null, $this->_delimiter, $this->_enclosure);
        $this->_currentKey = $this->_currentRow ? $this->_currentKey + 1 : null;
    }

    /**
     * Rewind the Iterator to the first element.
     *
     * @return void Any returned value is ignored.
     */
    #[\ReturnTypeWillChange]
    #[\Override]
    public function rewind()
    {
        // rewind resource, reset column names, read first row as current
        rewind($this->_fileHandler);
        $this->_colNames = fgetcsv($this->_fileHandler, null, $this->_delimiter, $this->_enclosure);
        $this->_currentRow = fgetcsv($this->_fileHandler, null, $this->_delimiter, $this->_enclosure);

        if ($this->_currentRow) {
            $this->_currentKey = 0;
        }
    }

    /**
     * Seeks to a position.
     *
     * @param int $position The position to seek to.
     * @throws OutOfBoundsException
     * @return void
     */
    #[\ReturnTypeWillChange]
    #[\Override]
    public function seek($position)
    {
        if ($position != $this->_currentKey) {
            if ($position == 0) {
                $this->rewind();
                return;
            } elseif ($position > 0) {
                if ($position < $this->_currentKey) {
                    $this->rewind();
                }
                while ($this->_currentRow = fgetcsv($this->_fileHandler, null, $this->_delimiter, $this->_enclosure)) {
                    if (++$this->_currentKey == $position) {
                        return;
                    }
                }
            }
            throw new OutOfBoundsException(Mage::helper('importexport')->__('Invalid seek position'));
        }
    }
}
