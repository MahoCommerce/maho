<?php

/**
 * Maho
 *
 * @package    Mage_Eav
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Component\Validator\Validation;

/**
 * EAV input type validation constraint
 */
#[\Attribute]
class Mage_Eav_Model_Adminhtml_System_Config_Source_Inputtype_Validator extends Constraint
{
    public string $notInArrayMessage = 'Input type "{{ value }}" not found in the input types list.';

    public array $haystack = [];
    public bool $strict = true;

    private array $_messages = [];

    public function __construct(
        mixed $options = null,
        ?array $groups = null,
        mixed $payload = null,
        ?array $haystack = null,
        ?bool $strict = null,
        ?string $notInArrayMessage = null
    ) {
        parent::__construct($options, $groups, $payload);

        $this->haystack = $haystack ?? $this->_getDefaultHaystack();
        $this->strict = $strict ?? $this->strict;
        $this->notInArrayMessage = $notInArrayMessage ?? $this->notInArrayMessage;
    }

    #[\Override]
    public function validatedBy(): string
    {
        return Mage_Eav_Model_Adminhtml_System_Config_Source_Inputtype_ValidatorValidator::class;
    }

    // Backward compatibility methods
    public function isValid(mixed $value): bool
    {
        $this->_messages = [];
        $validator = Validation::createValidator();
        $violations = $validator->validate($value, $this);

        if (count($violations) > 0) {
            foreach ($violations as $violation) {
                $this->_messages[] = $violation->getMessage();
            }
            return false;
        }
        return true;
    }

    public function getMessages(): array
    {
        return $this->_messages;
    }

    public function getMessage(): string
    {
        return !empty($this->_messages) ? $this->_messages[0] : '';
    }

    /**
     * Get default haystack from EAV helper
     */
    private function _getDefaultHaystack(): array
    {
        /** @var Mage_Eav_Helper_Data $helper */
        $helper = Mage::helper('eav');
        return $helper->getInputTypesValidatorData();
    }
}

/**
 * EAV input type constraint validator
 */
class Mage_Eav_Model_Adminhtml_System_Config_Source_Inputtype_ValidatorValidator extends ConstraintValidator
{
    #[\Override]
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof Mage_Eav_Model_Adminhtml_System_Config_Source_Inputtype_Validator) {
            throw new UnexpectedTypeException($constraint, Mage_Eav_Model_Adminhtml_System_Config_Source_Inputtype_Validator::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!in_array($value, $constraint->haystack, $constraint->strict)) {
            $this->context->buildViolation($constraint->notInArrayMessage)
                ->setParameter('{{ value }}', (string) $value)
                ->addViolation();
        }
    }
}
