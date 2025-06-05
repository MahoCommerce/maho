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

use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

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
     * List of Validators (Symfony Constraints)
     * The key is a field name, a value is array of constraints for this field
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
     * Symfony validator instance
     *
     * @var ValidatorInterface
     */
    protected $_validator;

    public function __construct()
    {
        $this->_validator = Validation::createValidator();
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
                $constraintsForField = [];
                foreach ($validatorsConfig as $validatorName => $validatorConfig) {
                    // it is required field
                    if ($validatorName == 'required' && $validatorConfig == 1) {
                        $this->_requiredFields[] = $field;
                        continue;
                    }
                    // instantiation of the validator constraint
                    if (!isset($validatorConfig['type'])) {
                        throw new Exception("Validator type is not set for {$validatorName}");
                    }
                    $constraint = $this->_getConstraintInstance(
                        $validatorConfig['type'],
                        !empty($validatorConfig['options']) ? $validatorConfig['options'] : [],
                        $validatorConfig['message'] ?? null,
                    );
                    // add to list of constraints
                    $constraintsForField[] = $constraint;
                }
                $this->_validators[$field] = $constraintsForField;
            }
        }
    }

    /**
     * Get constraint object instance
     * Converts Zend validators to Symfony constraints
     *
     * @param string $type
     * @param array $options
     * @param string|null $message
     * @return mixed
     * @throws Exception If validator type is not supported
     */
    protected function _getConstraintInstance($type, $options, $message = null)
    {
        $constraintOptions = $options;
        if ($message) {
            $constraintOptions['message'] = $message;
        }

        switch ($type) {
            case 'NotEmpty':
                return new Assert\NotBlank($constraintOptions);
            case 'EmailAddress':
                return new Assert\Email($constraintOptions);
            case 'StringLength':
                $constraintOptions = [];
                if (isset($options['min'])) {
                    $constraintOptions['min'] = $options['min'];
                }
                if (isset($options['max'])) {
                    $constraintOptions['max'] = $options['max'];
                }
                if ($message) {
                    $constraintOptions['minMessage'] = $message;
                    $constraintOptions['maxMessage'] = $message;
                }
                return new Assert\Length($constraintOptions);
            case 'Regex':
                if (isset($options['pattern'])) {
                    $constraintOptions['pattern'] = $options['pattern'];
                }
                return new Assert\Regex($constraintOptions);
            case 'Digits':
                return new Assert\Regex([
                    'pattern' => '/^\d+$/',
                    'message' => $message ?: 'This value should contain only digits.',
                ]);
            case 'Alnum':
                return new Assert\Regex([
                    'pattern' => '/^[a-zA-Z0-9]+$/',
                    'message' => $message ?: 'This value should contain only letters and numbers.',
                ]);
            case 'Alpha':
                return new Assert\Regex([
                    'pattern' => '/^[a-zA-Z]+$/',
                    'message' => $message ?: 'This value should contain only letters.',
                ]);
            case 'Between':
                $constraintOptions = [];
                if (isset($options['min'])) {
                    $constraintOptions['min'] = $options['min'];
                }
                if (isset($options['max'])) {
                    $constraintOptions['max'] = $options['max'];
                }
                if ($message) {
                    $constraintOptions['minMessage'] = $message;
                    $constraintOptions['maxMessage'] = $message;
                }
                return new Assert\Range($constraintOptions);
            case 'Url':
                return new Assert\Url($constraintOptions);
            case 'Date':
                return new Assert\Date($constraintOptions);
            default:
                // For unsupported validators, create a basic regex constraint
                throw new Exception("Validator type '{$type}' is not supported in Symfony conversion");
        }
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
                $violations = $this->_validator->validate($value, new Assert\NotBlank());
                if (count($violations) > 0) {
                    $isValid = false;
                    foreach ($violations as $violation) {
                        $this->_addError(sprintf('%s: %s', $requiredField, $violation->getMessage()));
                    }
                }
            }
        }

        // fields rules
        foreach ($data as $field => $value) {
            if (isset($this->_validators[$field])) {
                $constraints = $this->_validators[$field];
                $violations = $this->_validator->validate($value, $constraints);
                if (count($violations) > 0) {
                    $isValid = false;
                    foreach ($violations as $violation) {
                        $this->_addError(sprintf('%s: %s', $field, $violation->getMessage()));
                    }
                }
            }
        }
        return $isValid;
    }
}
