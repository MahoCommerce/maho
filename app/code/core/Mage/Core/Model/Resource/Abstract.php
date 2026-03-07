<?php

/**
 * Maho
 *
 * @package    Mage_Core
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

abstract class Mage_Core_Model_Resource_Abstract
{
    /**
     * Main constructor
     */
    public function __construct()
    {
        /**
         * Please override this one instead of overriding real __construct constructor
         */
        $this->_construct();
    }

    /**
     * Array of callbacks subscribed to commit transaction commit
     *
     * @var array
     */
    protected static $_commitCallbacks = [];

    abstract protected function _construct();

    /**
     * Retrieve connection for read data
     * @return Maho\Db\Adapter\AdapterInterface
     */
    abstract protected function _getReadAdapter();

    /**
     * Retrieve connection for write data
     * @return Maho\Db\Adapter\AdapterInterface
     */
    abstract protected function _getWriteAdapter();

    /**
     * Start resource transaction
     *
     * @return $this
     */
    public function beginTransaction()
    {
        $this->_getWriteAdapter()->beginTransaction();
        return $this;
    }

    /**
     * Subscribe some callback to transaction commit
     *
     * @param callable $callback
     * @return $this
     */
    public function addCommitCallback($callback)
    {
        $adapterKey = spl_object_hash($this->_getWriteAdapter());
        self::$_commitCallbacks[$adapterKey][] = $callback;
        return $this;
    }

    /**
     * Commit resource transaction
     *
     * @return $this
     */
    public function commit()
    {
        $this->_getWriteAdapter()->commit();
        /**
         * Process after commit callbacks
         */
        if ($this->_getWriteAdapter()->getTransactionLevel() === 0) {
            $adapterKey = spl_object_hash($this->_getWriteAdapter());
            if (isset(self::$_commitCallbacks[$adapterKey])) {
                $callbacks = self::$_commitCallbacks[$adapterKey];
                self::$_commitCallbacks[$adapterKey] = [];
                foreach ($callbacks as $callback) {
                    call_user_func($callback);
                }
            }
        }
        return $this;
    }

    /**
     * Roll back resource transaction
     *
     * @return $this
     */
    public function rollBack()
    {
        $this->_getWriteAdapter()->rollBack();
        if ($this->_getWriteAdapter()->getTransactionLevel() === 0) {
            $adapterKey = spl_object_hash($this->_getWriteAdapter());
            if (isset(self::$_commitCallbacks[$adapterKey])) {
                self::$_commitCallbacks[$adapterKey] = [];
            }
        }
        return $this;
    }

    /**
     * Format date to internal format
     *
     * @param int|string|DateTime|bool|null $date
     * @param bool $includeTime
     * @return string|null
     */
    public function formatDate($date, $includeTime = true)
    {
        if ($date === true) {
            $format = $includeTime ? Mage_Core_Model_Locale::DATETIME_FORMAT : Mage_Core_Model_Locale::DATE_FORMAT;
            return date($format);
        }

        if ($date instanceof DateTime) {
            $format = $includeTime ? Mage_Core_Model_Locale::DATETIME_FORMAT : Mage_Core_Model_Locale::DATE_FORMAT;
            return $date->format($format);
        }

        if (empty($date)) {
            return null;
        }

        if (!is_numeric($date)) {
            $date = strtotime($date);
        }

        $format = $includeTime ? Mage_Core_Model_Locale::DATETIME_FORMAT : Mage_Core_Model_Locale::DATE_FORMAT;
        return date($format, $date);
    }

    /**
     * Convert internal date to UNIX timestamp
     *
     * @param string $str
     * @return int
     */
    public function mktime($str)
    {
        return strtotime($str);
    }

    /**
     * Serialize specified field in an object
     *
     * @param string $field
     * @param mixed $defaultValue
     * @param bool $unsetEmpty
     * @return $this
     */
    protected function _serializeField(\Maho\DataObject $object, $field, $defaultValue = null, $unsetEmpty = false)
    {
        $value = $object->getData($field);
        if (empty($value)) {
            if ($unsetEmpty) {
                $object->unsetData($field);
            } else {
                if (is_object($defaultValue) || is_array($defaultValue)) {
                    $defaultValue = Mage::helper('core')->jsonEncode($defaultValue);
                }
                $object->setData($field, $defaultValue);
            }
        } elseif (is_array($value) || is_object($value)) {
            $object->setData($field, Mage::helper('core')->jsonEncode($value));
        }

        return $this;
    }

    /**
     * Unserialize \Maho\DataObject field in an object
     *
     * @param string $field
     * @param mixed $defaultValue
     */
    protected function _unserializeField(\Maho\DataObject $object, $field, $defaultValue = null)
    {
        $value = $object->getData($field);
        if (empty($value)) {
            $object->setData($field, $defaultValue);
        } elseif (is_string($value)) {
            if (json_validate($value)) {
                $object->setData($field, Mage::helper('core')->jsonDecode($value));
            } else {
                $data = unserialize($value, ['allowed_classes' => false]);
                $object->setData($field, $data);

                // Soft-convert legacy serialized data to JSON in the database
                if ($object->getId() && ($data !== false || $value === serialize(false))
                    && $this instanceof Mage_Core_Model_Resource_Db_Abstract
                ) {
                    try {
                        $jsonValue = Mage::helper('core')->jsonEncode($data);
                        $this->_getWriteAdapter()->update(
                            $this->getMainTable(),
                            [$field => $jsonValue],
                            [$this->getIdFieldName() . ' = ?' => $object->getId()],
                        );
                    } catch (\Exception $e) {
                        Mage::logException($e);
                    }
                }
            }
        }
    }

    /**
     * Prepare data for passed table
     *
     * @param string $table
     * @return array
     */
    protected function _prepareDataForTable(\Maho\DataObject $object, $table)
    {
        $data = [];
        $fields = $this->_getReadAdapter()->describeTable($table);
        foreach (array_keys($fields) as $field) {
            if ($object->hasData($field)) {
                $fieldValue = $object->getData($field);
                if ($fieldValue instanceof Maho\Db\Expr) {
                    $data[$field] = $fieldValue;
                } else {
                    if ($fieldValue !== null) {
                        $fieldValue   = $this->_prepareTableValueForSave($fieldValue, $fields[$field]['DATA_TYPE']);
                        $data[$field] = $this->_getWriteAdapter()->prepareColumnValue($fields[$field], $fieldValue);
                    } elseif (!empty($fields[$field]['NULLABLE'])) {
                        $data[$field] = null;
                    }
                }
            }
        }
        return $data;
    }

    /**
     * Prepare value for save
     *
     * @param mixed $value
     * @param string $type
     * @return mixed
     */
    protected function _prepareTableValueForSave($value, $type)
    {
        if ($type == 'decimal' || $type == 'numeric' || $type == 'float') {
            return Mage::app()->getLocale()->getNumber($value);
        }
        return $value;
    }

    public function isModuleEnabled(string $moduleName, string $helperAlias = 'core'): bool
    {
        return Mage::helper($helperAlias)->isModuleEnabled($moduleName);
    }
}
