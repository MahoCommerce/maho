<?php

/**
 * Maho
 *
 * @package    Mage_Api2
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2025 Maho (https://mahocommerce.com)
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

    public function __construct()
    {
        // Get validation config from resource if it exists
        $validationConfig = [];
        if ($this->_resource && method_exists($this->_resource, 'getValidationConfig')) {
            $validationConfig = $this->_resource->getValidationConfig(self::CONFIG_NODE_KEY);
        }
        if (!is_array($validationConfig)) {
            $validationConfig = [];
        }
        $this->_buildValidatorsChain($validationConfig);
    }

    /**
     * Build validator chain with config data
     *
     * @throws Exception If validator type is not set
     * @throws Exception If validator is not exist
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
                        throw new Exception("Validator type is not set for {$validatorName}");
                    }
                    $rule = [
                        'type' => $validatorConfig['type'],
                        'options' => !empty($validatorConfig['options']) ? $validatorConfig['options'] : [],
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
     * Validate a value using Maho_Validator
     * Converts validation types to Maho_Validator calls
     *
     * @param mixed $value
     * @param string $type
     * @param array $options
     * @return bool
     * @throws Exception If validator type is not supported
     */
    protected function _validateValue($value, $type, $options)
    {
        return match ($type) {
            'NotEmpty', 'NotBlank' => Maho_Validator::validateNotBlank($value),
            'Email', 'EmailAddress' => Maho_Validator::validateEmail($value),
            'Regex' => Maho_Validator::validateRegex($value, $options['pattern'] ?? '/.*/'),
            'Length', 'StringLength' => Maho_Validator::validateLength(
                $value,
                $options['min'] ?? 0,
                $options['max'] ?? PHP_INT_MAX,
            ),
            'Range', 'Between' => Maho_Validator::validateRange(
                $value,
                $options['min'] ?? PHP_INT_MIN,
                $options['max'] ?? PHP_INT_MAX,
            ),
            'Url' => Maho_Validator::validateUrl($value),
            'Date' => Maho_Validator::validateDate($value),
            'Digits' => Maho_Validator::validateRegex($value, '/^\d+$/'),
            'Alnum' => Maho_Validator::validateRegex($value, '/^[a-zA-Z0-9]+$/'),
            'Alpha' => Maho_Validator::validateRegex($value, '/^[a-zA-Z]+$/'),
            default => throw new Exception("Unsupported validator type: {$type}"),
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
                if (!Maho_Validator::validateNotBlank($value)) {
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
