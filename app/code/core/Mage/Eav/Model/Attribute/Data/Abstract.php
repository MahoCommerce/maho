<?php

/**
 * Maho
 *
 * @package    Mage_Eav
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

abstract class Mage_Eav_Model_Attribute_Data_Abstract
{
    /**
     * Attribute instance
     *
     * @var Mage_Eav_Model_Attribute
     */
    protected $_attribite;

    /**
     * Entity instance
     *
     * @var Mage_Core_Model_Abstract
     */
    protected $_entity;

    /**
     * Request Scope name
     *
     * @var string
     */
    protected $_requestScope;

    /**
     * Scope visibility flag
     *
     * @var bool
     */
    protected $_requestScopeOnly    = true;

    /**
     * Is AJAX request flag
     *
     * @var bool
     */
    protected $_isAjax              = false;

    /**
     * Array of full extracted data
     * Needed for depends attributes
     *
     * @var array
     */
    protected $_extractedData       = [];

    /**
     * Mage_Core_Model_Locale FORMAT
     *
     * @var string|null
     */
    protected $_dateFilterFormat;

    /**
     * Set attribute instance
     *
     * @return Mage_Eav_Model_Attribute_Data_Abstract
     */
    public function setAttribute(Mage_Eav_Model_Entity_Attribute_Abstract $attribute)
    {
        $this->_attribite = $attribute;
        return $this;
    }

    /**
     * Return Attribute instance
     *
     * @throws Mage_Core_Exception
     * @return Mage_Eav_Model_Attribute
     */
    public function getAttribute()
    {
        if (!$this->_attribite) {
            Mage::throwException(Mage::helper('eav')->__('Attribute object is undefined'));
        }
        return $this->_attribite;
    }

    /**
     * Set Request scope
     *
     * @param string $scope
     * @return $this
     */
    public function setRequestScope($scope)
    {
        $this->_requestScope = $scope;
        return $this;
    }

    /**
     * Set scope visibility
     * Search value only in scope or search value in scope and global
     *
     * @param bool $flag
     * @return $this
     */
    public function setRequestScopeOnly($flag)
    {
        $this->_requestScopeOnly = (bool) $flag;
        return $this;
    }

    /**
     * Set entity instance
     *
     * @return $this
     */
    public function setEntity(Mage_Core_Model_Abstract $entity)
    {
        $this->_entity = $entity;
        return $this;
    }

    /**
     * Returns entity instance
     *
     * @return Mage_Core_Model_Abstract
     */
    public function getEntity()
    {
        if (!$this->_entity) {
            Mage::throwException(Mage::helper('eav')->__('Entity object is undefined'));
        }
        return $this->_entity;
    }

    /**
     * Set array of full extracted data
     *
     * @return $this
     */
    public function setExtractedData(array $data)
    {
        $this->_extractedData = $data;
        return $this;
    }

    /**
     * Return extracted data
     *
     * @param string $index
     * @return mixed
     */
    public function getExtractedData($index = null)
    {
        if (!is_null($index)) {
            return $this->_extractedData[$index] ?? null;
        }
        return $this->_extractedData;
    }

    /**
     * Apply attribute input filter to value
     *
     * @param string $value
     * @return false|string
     */
    protected function _applyInputFilter($value)
    {
        if ($value === false) {
            return false;
        }

        $filter = $this->_getFormFilter();
        if ($filter) {
            $value = $filter->inputFilter($value);
        }

        return $value;
    }

    /**
     * Return Data Form Input/Output Filter
     *
     * @return \Maho\Data\Form\Filter\FilterInterface|false
     */
    protected function _getFormFilter()
    {
        $filterCode = $this->getAttribute()->getInputFilter();
        if ($filterCode) {
            $filterClass = '\Maho\Data\Form\Filter\\' . ucfirst($filterCode);
            if ($filterCode == 'date') {
                $filter = new $filterClass($this->_dateFilterFormat(), Mage::app()->getLocale()->getLocale());
            } else {
                $filter = new $filterClass();
            }
            return $filter;
        }
        return false;
    }

    /**
     * Get/Set/Reset date filter format
     *
     * @param string|null|false $format
     * @return $this|string
     */
    protected function _dateFilterFormat($format = null)
    {
        if (is_null($format)) {
            // get format
            if (is_null($this->_dateFilterFormat)) {
                $this->_dateFilterFormat = Mage_Core_Model_Locale::FORMAT_TYPE_SHORT;
            }
            return Mage::app()->getLocale()->getDateFormat($this->_dateFilterFormat);
        }
        if ($format === false) {
            // reset value
            $this->_dateFilterFormat = null;
            return $this;
        }

        $this->_dateFilterFormat = $format;
        return $this;
    }

    /**
     * Apply attribute output filter to value
     *
     * @param string $value
     * @return string
     */
    protected function _applyOutputFilter($value)
    {
        $filter = $this->_getFormFilter();
        if ($filter) {
            $value = $filter->outputFilter($value);
        }

        return $value;
    }

    /**
     * Validate value by attribute input validation rule
     *
     * @param string $value
     * @return string|array|true
     */
    protected function _validateInputRule($value)
    {
        // skip validate empty value
        if (empty($value)) {
            return true;
        }

        $label         = $this->getAttribute()->getStoreLabel();
        $validateRules = $this->getAttribute()->getValidateRules();

        if (!empty($validateRules['input_validation'])) {
            $isValid = true;
            $errorMessage = '';

            switch ($validateRules['input_validation']) {
                case 'alphanumeric':
                    $isValid = Mage::helper('core')->isValidRegex($value, '/^[a-zA-Z0-9\s]+$/');
                    $errorMessage = Mage::helper('eav')->__('"%s" has not only alphabetic and digit characters.', $label);
                    break;

                case 'numeric':
                    $isValid = Mage::helper('core')->isValidRegex($value, '/^\d+$/');
                    $errorMessage = Mage::helper('eav')->__('"%s" contains not only digit characters.', $label);
                    break;

                case 'alpha':
                    $isValid = Mage::helper('core')->isValidRegex($value, '/^[a-zA-Z\s]+$/');
                    $errorMessage = Mage::helper('eav')->__('"%s" has not only alphabetic characters.', $label);
                    break;

                case 'email':
                    $isValid = Mage::helper('core')->isValidEmail($value);
                    $errorMessage = Mage::helper('eav')->__('"%s" is not a valid email address.', $label);
                    break;

                case 'url':
                    // First do basic URL parsing check
                    $parsedUrl = parse_url($value);
                    if ($parsedUrl === false || empty($parsedUrl['scheme']) || empty($parsedUrl['host'])) {
                        return [Mage::helper('eav')->__('"%s" is not a valid URL.', $label)];
                    }

                    $isValid = Mage::helper('core')->isValidUrl($value);
                    $errorMessage = Mage::helper('eav')->__('"%s" is not a valid URL.', $label);
                    break;

                case 'date':
                    $isValid = Mage::helper('core')->isValidDate($value);
                    $errorMessage = Mage::helper('eav')->__('"%s" is not a valid date.', $label);

                    // Additional check for specific date format if needed
                    if ($isValid) {
                        $dateFormat = Mage_Core_Model_Locale::DATE_FORMAT;
                        $dateTime = DateTime::createFromFormat($dateFormat, $value);
                        if (!$dateTime || $dateTime->format($dateFormat) !== $value) {
                            return [Mage::helper('eav')->__('"%s" does not fit the entered date format.', $label)];
                        }
                    }
                    break;
            }

            if (!$isValid) {
                return [$errorMessage];
            }
        }
        return true;
    }

    /**
     * Set is AJAX Request flag
     *
     * @param bool $flag
     * @return $this
     */
    public function setIsAjaxRequest($flag = true)
    {
        $this->_isAjax = (bool) $flag;
        return $this;
    }

    /**
     * Return is AJAX Request
     *
     * @return bool
     */
    public function getIsAjaxRequest()
    {
        return $this->_isAjax;
    }

    /**
     * Return Original Attribute value from Request
     *
     * @return mixed
     */
    protected function _getRequestValue(Mage_Core_Controller_Request_Http $request)
    {
        $attrCode  = $this->getAttribute()->getAttributeCode();
        if ($this->_requestScope) {
            if (str_contains($this->_requestScope, '/')) {
                $params = $request->getParams();
                $parts = explode('/', $this->_requestScope);
                foreach ($parts as $part) {
                    $params = $params[$part] ?? [];
                }
            } else {
                $params = $request->getParam($this->_requestScope);
            }

            $value = $params[$attrCode] ?? false;

            if (!$this->_requestScopeOnly && $value === false) {
                $value = $request->getParam($attrCode, false);
            }
        } else {
            $value = $request->getParam($attrCode, false);
        }
        return $value;
    }

    /**
     * Extract data from request and return value
     *
     * @return array|string
     */
    abstract public function extractValue(Mage_Core_Controller_Request_Http $request);

    /**
     * Validate data
     *
     * @param array|string $value
     * @throws Mage_Core_Exception
     * @return bool
     */
    abstract public function validateValue($value);

    /**
     * Export attribute value to entity model
     *
     * @param array|string $value
     * @return $this
     */
    abstract public function compactValue($value);

    /**
     * Restore attribute value from SESSION to entity model
     *
     * @param array|string $value
     * @return $this
     */
    abstract public function restoreValue($value);

    /**
     * Return formatted attribute value from entity model
     *
     * @param string $format
     * @return string|array
     */
    abstract public function outputValue($format = Mage_Eav_Model_Attribute_Data::OUTPUT_FORMAT_TEXT);
}
