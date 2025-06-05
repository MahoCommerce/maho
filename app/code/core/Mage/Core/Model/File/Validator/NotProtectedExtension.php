<?php

/**
 * Maho
 *
 * @package    Mage_Core
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
 * Constraint for file extension validation
 */
#[\Attribute]
class Mage_Core_Model_File_Validator_NotProtectedExtension extends Constraint
{
    public string $protectedExtensionMessage = 'File with an extension "{{ value }}" is protected and cannot be uploaded.';

    public array $protectedExtensions = [];

    private array $_messages = [];

    public function __construct(
        mixed $options = null,
        ?array $groups = null,
        mixed $payload = null,
        ?array $protectedExtensions = null,
        ?string $protectedExtensionMessage = null
    ) {
        parent::__construct($options, $groups, $payload);

        $this->protectedExtensions = $protectedExtensions ?? $this->_getDefaultProtectedExtensions();
        $this->protectedExtensionMessage = $protectedExtensionMessage ?? $this->protectedExtensionMessage;
    }

    #[\Override]
    public function validatedBy(): string
    {
        return Mage_Core_Model_File_Validator_NotProtectedExtensionValidator::class;
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
     * Get default protected file extensions from core helper
     */
    private function _getDefaultProtectedExtensions(): array
    {
        /** @var Mage_Core_Helper_Data $helper */
        $helper = Mage::helper('core');
        $extensions = $helper->getProtectedFileExtensions();
        if (is_string($extensions)) {
            $extensions = explode(',', $extensions);
        }
        foreach ($extensions as &$ext) {
            $ext = strtolower(trim($ext));
        }
        return (array) $extensions;
    }
}

/**
 * Validator for not protected extension constraint
 */
class Mage_Core_Model_File_Validator_NotProtectedExtensionValidator extends ConstraintValidator
{
    #[\Override]
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof Mage_Core_Model_File_Validator_NotProtectedExtension) {
            throw new UnexpectedTypeException($constraint, Mage_Core_Model_File_Validator_NotProtectedExtension::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!is_string($value)) {
            throw new UnexpectedValueException($value, 'string');
        }

        $value = strtolower(trim($value));

        if (in_array($value, $constraint->protectedExtensions)) {
            $this->context->buildViolation($constraint->protectedExtensionMessage)
                ->setParameter('{{ value }}', $value)
                ->addViolation();
        }
    }
}
