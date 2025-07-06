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
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Component\Validator\Validation;

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
        ?string $notInArrayMessage = null,
    ) {
        parent::__construct($options, $groups, $payload);

        $this->haystack = $haystack ?? $this->_getDefaultHaystack();
        $this->strict = $strict ?? $this->strict;
        $this->notInArrayMessage = $notInArrayMessage ?? $this->notInArrayMessage;
    }

    public function validate(mixed $value, ExecutionContextInterface $context): void
    {
        if (null === $value || '' === $value) {
            return;
        }

        if (!in_array($value, $this->haystack, $this->strict)) {
            $context->buildViolation($this->notInArrayMessage)
                ->setParameter('{{ value }}', (string) $value)
                ->addViolation();
        }
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

    private function _getDefaultHaystack(): array
    {
        $helper = Mage::helper('eav');
        return $helper->getInputTypesValidatorData();
    }
}
