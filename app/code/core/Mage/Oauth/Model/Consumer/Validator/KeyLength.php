<?php

/**
 * Maho
 *
 * @package    Mage_Oauth
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
 * OAuth Key Length validation constraint
 */
#[\Attribute]
class Mage_Oauth_Model_Consumer_Validator_KeyLength extends Constraint
{
    public string $tooShortMessage = '{{ name }} "{{ value }}" is too short. It must have length {{ min }} symbols.';
    public string $tooLongMessage = '{{ name }} "{{ value }}" is too long. It must have length {{ max }} symbols.';

    public ?int $min = null;
    public ?int $max = null;
    public int $length = 0;
    public string $encoding = 'utf-8';
    public string $name = 'Key';

    private array $_messages = [];

    public function __construct(
        mixed $options = null,
        ?array $groups = null,
        mixed $payload = null,
        ?int $min = null,
        ?int $max = null,
        ?int $length = null,
        ?string $encoding = null,
        ?string $name = null,
        ?string $tooShortMessage = null,
        ?string $tooLongMessage = null
    ) {
        parent::__construct($options, $groups, $payload);

        $this->min = $min ?? $this->min;
        $this->max = $max ?? $this->max;
        $this->encoding = $encoding ?? $this->encoding;
        $this->name = $name ?? $this->name;
        $this->tooShortMessage = $tooShortMessage ?? $this->tooShortMessage;
        $this->tooLongMessage = $tooLongMessage ?? $this->tooLongMessage;

        if (null !== $length) {
            $this->min = $this->max = $length;
        }
    }

    #[\Override]
    public function validatedBy(): string
    {
        return Mage_Oauth_Model_Consumer_Validator_KeyLengthValidator::class;
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

    public function setLength(int $length): self
    {
        $this->min = $this->max = $length;
        return $this;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }
}

/**
 * OAuth Key Length constraint validator
 */
class Mage_Oauth_Model_Consumer_Validator_KeyLengthValidator extends ConstraintValidator
{
    #[\Override]
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof Mage_Oauth_Model_Consumer_Validator_KeyLength) {
            throw new UnexpectedTypeException($constraint, Mage_Oauth_Model_Consumer_Validator_KeyLength::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!is_string($value)) {
            throw new UnexpectedValueException($value, 'string');
        }

        $length = iconv_strlen($value, $constraint->encoding);

        if (null !== $constraint->min && $length < $constraint->min) {
            $this->context->buildViolation($constraint->tooShortMessage)
                ->setParameter('{{ value }}', $value)
                ->setParameter('{{ min }}', (string) $constraint->min)
                ->setParameter('{{ name }}', $constraint->name)
                ->addViolation();
            return;
        }

        if (null !== $constraint->max && $length > $constraint->max) {
            $this->context->buildViolation($constraint->tooLongMessage)
                ->setParameter('{{ value }}', $value)
                ->setParameter('{{ max }}', (string) $constraint->max)
                ->setParameter('{{ name }}', $constraint->name)
                ->addViolation();
        }
    }
}
