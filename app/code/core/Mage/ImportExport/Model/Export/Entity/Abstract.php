<?php

/**
 * Maho
 *
 * @package    Mage_ImportExport
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

abstract class Mage_ImportExport_Model_Export_Entity_Abstract
{
    /**
     * Attribute code to its values. Only attributes with options and only default store values used.
     *
     * @var array
     */
    protected $_attributeValues = [];

    /**
     * Attribute code to its values. Only attributes with options and only default store values used.
     *
     * @var array
     */
    protected static $attrCodes = null;

    /**
     * DB connection.
     *
     * @var Varien_Db_Adapter_Pdo_Mysql
     */
    protected $_connection;

    /**
     * Array of attributes codes which are disabled for export.
     *
     * @var array
     */
    protected $_disabledAttrs = [];

    /**
     * Entity type id.
     *
     * @var int|null
     */
    protected $_entityTypeId;

    /**
     * Error codes with arrays of corresponding row numbers.
     *
     * @var array
     */
    protected $_errors = [];

    /**
     * @var array
     */
    protected $_invalidRows = [];

    /**
     * Error counter.
     *
     * @var int
     */
    protected $_errorsCount = 0;

    /**
     * Limit of errors after which pre-processing will exit.
     *
     * @var int
     */
    protected $_errorsLimit = 100;

    /**
     * Export filter data.
     *
     * @var array
     */
    protected $_filter = [];

    /**
     * Attributes with index (not label) value.
     *
     * @var array
     */
    protected $_indexValueAttributes = [];

    /**
     * Validation failure message template definitions.
     *
     * @var array
     */
    protected $_messageTemplates = [];

    /**
     * Parameters.
     *
     * @var array
     */
    protected $_parameters = [];

    /**
     * Column names that holds values with particular meaning.
     *
     * @var array
     */
    protected $_particularAttributes = [];

    /**
     * Permanent entity columns.
     *
     * @var array
     */
    protected $_permanentAttributes = [];

    /**
     * Number of entities processed by validation.
     *
     * @var int
     */
    protected $_processedEntitiesCount = 0;

    /**
     * Number of rows processed by validation.
     *
     * @var int
     */
    protected $_processedRowsCount = 0;

    /**
     * Source model.
     *
     * @var Mage_ImportExport_Model_Export_Adapter_Abstract
     */
    protected $_writer;

    /**
     * Array of pairs store ID to its code.
     *
     * @var array
     */
    protected $_storeIdToCode = [];

    /**
     * Store Id-to-website
     *
     * @var array
     */
    protected $_storeIdToWebsiteId = [];

    /**
     * Website ID-to-code.
     *
     * @var array
     */
    protected $_websiteIdToCode = [];

    public function __construct()
    {
        $entityCode = $this->getEntityTypeCode();
        $this->_entityTypeId = Mage::getSingleton('eav/config')->getEntityType($entityCode)->getEntityTypeId();

        /** @var Varien_Db_Adapter_Pdo_Mysql $_connection */
        $_connection         = Mage::getSingleton('core/resource')->getConnection('write');
        $this->_connection   = $_connection;
    }

    /**
    * Initialize website values.
    *
    * @return $this
    */
    protected function _initWebsites()
    {
        foreach (Mage::app()->getWebsites(true) as $website) {
            $this->_websiteIdToCode[$website->getId()] = $website->getCode();
        }
        return $this;
    }

    /**
     * Initialize stores hash.
     *
     * @return $this
     */
    protected function _initStores()
    {
        foreach (Mage::app()->getStores(true) as $store) {
            $this->_storeIdToCode[$store->getId()]      = $store->getCode();
            $this->_storeIdToWebsiteId[$store->getId()] = $store->getWebsiteId();
        }
        ksort($this->_storeIdToCode); // to ensure that 'admin' store (ID is zero) goes first
        sort($this->_storeIdToWebsiteId);

        return $this;
    }

    /**
     * Get attributes codes which are appropriate for export.
     *
     * @return array
     */
    protected function _getExportAttrCodes()
    {
        if (self::$attrCodes === null) {
            if (!empty($this->_parameters[Mage_ImportExport_Model_Export::FILTER_ELEMENT_SKIP])
                    && is_array($this->_parameters[Mage_ImportExport_Model_Export::FILTER_ELEMENT_SKIP])
            ) {
                $skipAttr = array_flip($this->_parameters[Mage_ImportExport_Model_Export::FILTER_ELEMENT_SKIP]);
            } else {
                $skipAttr = [];
            }
            $attrCodes = [];

            foreach ($this->filterAttributeCollection($this->getAttributeCollection()) as $attribute) {
                if (!isset($skipAttr[$attribute->getAttributeId()])
                        || in_array($attribute->getAttributeCode(), $this->_permanentAttributes)
                ) {
                    $attrCodes[] = $attribute->getAttributeCode();
                }
            }
            self::$attrCodes = $attrCodes;
        }
        return self::$attrCodes;
    }

    /**
     * Initialize attribute option values.
     *
     * @return Mage_ImportExport_Model_Export_Entity_Abstract
     */
    protected function _initAttrValues()
    {
        foreach ($this->getAttributeCollection() as $attribute) {
            $this->_attributeValues[$attribute->getAttributeCode()] = $this->getAttributeOptions($attribute);
        }
        return $this;
    }

    /**
     * Apply filter to collection and add not skipped attributes to select.
     *
     * @return Mage_Eav_Model_Entity_Collection_Abstract
     */
    protected function _prepareEntityCollection(Mage_Eav_Model_Entity_Collection_Abstract $collection)
    {
        if (!isset($this->_parameters[Mage_ImportExport_Model_Export::FILTER_ELEMENT_GROUP])
            || !is_array($this->_parameters[Mage_ImportExport_Model_Export::FILTER_ELEMENT_GROUP])
        ) {
            $exportFilter = [];
        } else {
            $exportFilter = $this->_parameters[Mage_ImportExport_Model_Export::FILTER_ELEMENT_GROUP];
        }
        $exportAttrCodes = $this->_getExportAttrCodes();

        foreach ($this->filterAttributeCollection($this->getAttributeCollection()) as $attribute) {
            $attrCode = $attribute->getAttributeCode();

            // filter applying
            if (isset($exportFilter[$attrCode])) {
                $attrFilterType = Mage_ImportExport_Model_Export::getAttributeFilterType($attribute);

                if (Mage_ImportExport_Model_Export::FILTER_TYPE_SELECT == $attrFilterType) {
                    if (is_scalar($exportFilter[$attrCode]) && trim($exportFilter[$attrCode])) {
                        $collection->addAttributeToFilter($attrCode, ['eq' => $exportFilter[$attrCode]]);
                    }
                } elseif (Mage_ImportExport_Model_Export::FILTER_TYPE_INPUT == $attrFilterType) {
                    if (is_scalar($exportFilter[$attrCode]) && trim($exportFilter[$attrCode])) {
                        $collection->addAttributeToFilter($attrCode, ['like' => "%{$exportFilter[$attrCode]}%"]);
                    }
                } elseif (Mage_ImportExport_Model_Export::FILTER_TYPE_DATE == $attrFilterType) {
                    if (is_array($exportFilter[$attrCode]) && count($exportFilter[$attrCode]) == 2) {
                        $from = array_shift($exportFilter[$attrCode]);
                        $to   = array_shift($exportFilter[$attrCode]);

                        if (is_scalar($from) && !empty($from)) {
                            $date = Mage::app()->getLocale()->date($from, null, null, false)->toString('MM/dd/YYYY');
                            $collection->addAttributeToFilter($attrCode, ['from' => $date, 'date' => true]);
                        }
                        if (is_scalar($to) && !empty($to)) {
                            $date = Mage::app()->getLocale()->date($to, null, null, false)->toString('MM/dd/YYYY');
                            $collection->addAttributeToFilter($attrCode, ['to' => $date, 'date' => true]);
                        }
                    }
                } elseif (Mage_ImportExport_Model_Export::FILTER_TYPE_NUMBER == $attrFilterType) {
                    if (is_array($exportFilter[$attrCode]) && count($exportFilter[$attrCode]) == 2) {
                        $from = array_shift($exportFilter[$attrCode]);
                        $to   = array_shift($exportFilter[$attrCode]);

                        if (is_numeric($from)) {
                            $collection->addAttributeToFilter($attrCode, ['from' => $from]);
                        }
                        if (is_numeric($to)) {
                            $collection->addAttributeToFilter($attrCode, ['to' => $to]);
                        }
                    }
                }
            }
            if (in_array($attrCode, $exportAttrCodes)) {
                $collection->addAttributeToSelect($attrCode);
            }
        }
        return $collection;
    }

    /**
     * Add error with corresponding current data source row number.
     *
     * @param string $errorCode Error code or simply column name
     * @param int $errorRowNum Row number.
     * @return $this
     */
    public function addRowError($errorCode, $errorRowNum)
    {
        $this->_errors[$errorCode][] = $errorRowNum + 1; // one added for human readability
        $this->_invalidRows[$errorRowNum] = true;
        $this->_errorsCount++;

        return $this;
    }

    /**
     * Add message template for specific error code from outside.
     *
     * @param string $errorCode Error code
     * @param string $message Message template
     * @return $this
     */
    public function addMessageTemplate($errorCode, $message)
    {
        $this->_messageTemplates[$errorCode] = $message;

        return $this;
    }

    /**
     * Export process.
     *
     * @deprecated after ver 1.9.2.4 use $this->exportFile() instead
     *
     * @return string
     */
    abstract public function export();

    /**
     * Export data and return temporary file through array.
     *
     * This method will return following array:
     *
     * array(
     *     'rows'  => count of written rows,
     *     'value' => path to created file,
     *     'type'  => 'file'
     * )
     *
     * @throws Mage_Core_Exception
     * @return array
     */
    abstract public function exportFile();

    /**
     * Clean up attribute collection.
     *
     * @return Mage_Eav_Model_Resource_Entity_Attribute_Collection
     */
    public function filterAttributeCollection(Mage_Eav_Model_Resource_Entity_Attribute_Collection $collection)
    {
        $collection->load();

        foreach ($collection as $attribute) {
            if (in_array($attribute->getAttributeCode(), $this->_disabledAttrs)) {
                $collection->removeItemByKey($attribute->getId());
            }
        }
        return $collection;
    }

    /**
     * Entity attributes collection getter.
     *
     * @return Mage_Eav_Model_Resource_Entity_Attribute_Collection
     */
    abstract public function getAttributeCollection();

    /**
     * Returns attributes all values in label-value or value-value pairs form. Labels are lower-cased.
     *
     * @return array
     */
    public function getAttributeOptions(Mage_Eav_Model_Entity_Attribute_Abstract $attribute)
    {
        $options = [];

        if ($attribute->usesSource()) {
            // should attribute has index (option value) instead of a label?
            $index = in_array($attribute->getAttributeCode(), $this->_indexValueAttributes) ? 'value' : 'label';

            // only default (admin) store values used
            $attribute->setStoreId(Mage_Catalog_Model_Abstract::DEFAULT_STORE_ID);

            try {
                foreach ($attribute->getSource()->getAllOptions(false) as $option) {
                    $innerOptions = is_array($option['value']) ? $option['value'] : [$option];
                    foreach ($innerOptions as $innerOption) {
                        if (strlen($innerOption['value'])) { // skip ' -- Please Select -- ' option
                            $options[$innerOption['value']] = $innerOption[$index];
                        }
                    }
                }
            } catch (Exception $e) {
                // ignore exceptions connected with source models
            }
        }
        return $options;
    }

    /**
     * EAV entity type code getter.
     *
     * @abstract
     * @return string
     */
    abstract public function getEntityTypeCode();

    /**
     * Entity type ID getter.
     *
     * @return int|null
     */
    public function getEntityTypeId()
    {
        return $this->_entityTypeId;
    }

    /**
     * Returns error information.
     *
     * @return array
     */
    public function getErrorMessages()
    {
        $messages = [];
        foreach ($this->_errors as $errorCode => $errorRows) {
            $message = isset($this->_messageTemplates[$errorCode])
                ? Mage::helper('importexport')->__($this->_messageTemplates[$errorCode])
                : Mage::helper('importexport')->__("Invalid value for '%s' column", $errorCode);
            $messages[$message] = $errorRows;
        }
        return $messages;
    }

    /**
     * Returns error counter value.
     *
     * @return int
     */
    public function getErrorsCount()
    {
        return $this->_errorsCount;
    }

    /**
     * Returns invalid rows count.
     *
     * @return int
     */
    public function getInvalidRowsCount()
    {
        return count($this->_invalidRows);
    }

    /**
     * Returns number of checked entities.
     *
     * @return int
     */
    public function getProcessedEntitiesCount()
    {
        return $this->_processedEntitiesCount;
    }

    /**
     * Returns number of checked rows.
     *
     * @return int
     */
    public function getProcessedRowsCount()
    {
        return $this->_processedRowsCount;
    }

    /**
     * Inner writer object getter.
     *
     * @throws Exception
     * @return Mage_ImportExport_Model_Export_Adapter_Abstract
     */
    public function getWriter()
    {
        if (!$this->_writer) {
            Mage::throwException(Mage::helper('importexport')->__('No writer specified'));
        }
        return $this->_writer;
    }

    /**
     * Set parameters.
     *
     * @return Mage_ImportExport_Model_Export_Entity_Abstract
     */
    public function setParameters(array $parameters)
    {
        $this->_parameters = $parameters;

        return $this;
    }

    /**
     * Writer model setter.
     *
     * @return Mage_ImportExport_Model_Export_Entity_Abstract
     */
    public function setWriter(Mage_ImportExport_Model_Export_Adapter_Abstract $writer)
    {
        $this->_writer = $writer;

        return $this;
    }
}
