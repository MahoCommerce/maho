<?php

/**
 * Maho
 *
 * @package    Mage_Api2
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Api2_Model_Resource_Validator_Fields extends Mage_Api2_Model_Resource_Validator
{
    /**
     * Config node key of current validator
     */
    public const CONFIG_NODE_KEY = 'fields';

    /**
     * Resource
     *
     * @var Mage_Api2_Model_Resource
     */
    protected $_resource;

    /**
     * List of validation rules
     * The key is a field name, a value is array of validation rules for this field
     *
     * @var array
     */
    protected $_validators;

    /**
     * List of required fields
     *
     * @var array
     */
    protected $_requiredFields = [];

    /**
     * Construct. Set all depends.
     *
     * Required parameteres for options:
     * - resource
     *
     * @param array $options
     * @throws Mage_Core_Exception If passed parameter 'resource' is wrong
     */
    public function __construct($options)
    {
        if (!isset($options['resource']) || !$options['resource'] instanceof Mage_Api2_Model_Resource) {
            throw new Mage_Core_Exception("Passed parameter 'resource' is wrong.");
        }
        $this->_resource = $options['resource'];

        $validationConfig = $this->_resource->getConfig()->getValidationConfig(
            $this->_resource->getResourceType(),
            self::CONFIG_NODE_KEY,
        );
        if (!is_array($validationConfig)) {
            $validationConfig = [];
        }
        $this->_buildValidatorsChain($validationConfig);
    }

    /**
     * Build validator chain with config data
     *
     * @throws Mage_Core_Exception If validator type is not supported
     */
    protected function _buildValidatorsChain(array $validationConfig)
    {
        foreach ($validationConfig as $field => $validatorsConfig) {
            if (count($validatorsConfig)) {
                $rulesForField = [];
                foreach ($validatorsConfig as $validatorName => $validatorConfig) {
                    // it is required field
                    if ($validatorName == 'required' && $validatorConfig == 1) {
                        $this->_requiredFields[] = $field;
                        continue;
                    }
                    // store validation rule
                    if (!isset($validatorConfig['type'])) {
                        throw new Mage_Core_Exception("Validator type is not set for {$validatorName}");
                    }
                    $rule = [
                        'type' => $validatorConfig['type'],
                        'options' => empty($validatorConfig['options']) ? [] : $validatorConfig['options'],
                        'message' => $validatorConfig['message'] ?? null,
                    ];
                    // add to list of rules
                    $rulesForField[] = $rule;
                }
                $this->_validators[$field] = $rulesForField;
            }
        }
    }

    /**
     * Validate a value
     * Converts validation types to Core Helper validation calls
     *
     * @param mixed $value
     * @param string $type
     * @param array $options
     * @return bool
     * @throws Mage_Core_Exception If validator type is not supported
     */
    protected function _validateValue($value, $type, $options)
    {
        $coreHelper = Mage::helper('core');
        return match ($type) {
            'NotEmpty', 'NotBlank' => $coreHelper->isValidNotBlank($value),
            'Email', 'EmailAddress' => $coreHelper->isValidEmail($value),
            'Regex' => $coreHelper->isValidRegex($value, $options['pattern'] ?? '/.*/'),
            'Length', 'StringLength' => $coreHelper->isValidLength(
                $value,
                $options['min'] ?? 0,
                $options['max'] ?? PHP_INT_MAX,
            ),
            'Range', 'Between' => $coreHelper->isValidRange(
                $value,
                $options['min'] ?? PHP_INT_MIN,
                $options['max'] ?? PHP_INT_MAX,
            ),
            'Url' => $coreHelper->isValidUrl($value),
            'Date' => $coreHelper->isValidDate($value),
            'Digits' => $coreHelper->isValidRegex($value, '/^\d+$/'),
            'Alnum' => $coreHelper->isValidRegex($value, '/^[a-zA-Z0-9]+$/'),
            'Alpha' => $coreHelper->isValidRegex($value, '/^[a-zA-Z]+$/'),
            default => throw new Mage_Core_Exception("Unsupported validator type: {$type}"),
        };
    }

    /**
     * Validate data.
     * If fails validation, then this method returns false, and
     * getErrors() will return an array of errors that explain why the
     * validation failed.
     *
     * @param bool $isPartial
     * @return bool
     */
    public function isValidData(array $data, $isPartial = false)
    {
        $isValid = true;

        // required fields
        if (!$isPartial && count($this->_requiredFields) > 0) {
            foreach ($this->_requiredFields as $requiredField) {
                $value = $data[$requiredField] ?? null;
                if (!Mage::helper('core')->isValidNotBlank($value)) {
                    $isValid = false;
                    $this->_addError(sprintf('%s: This value should not be blank.', $requiredField));
                }
            }
        }

        // fields rules
        foreach ($data as $field => $value) {
            if (isset($this->_validators[$field])) {
                $rules = $this->_validators[$field];
                foreach ($rules as $rule) {
                    if (!$this->_validateValue($value, $rule['type'], $rule['options'])) {
                        $isValid = false;
                        $message = $rule['message'] ?: 'This value is not valid.';
                        $this->_addError(sprintf('%s: %s', $field, $message));
                    }
                }
            }
        }
        return $isValid;
    }
}
