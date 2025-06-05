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
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Component\Validator\Validation;

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

    public function validate(mixed $value, ExecutionContextInterface $context): void
    {
        if (null === $value || '' === $value) {
            return;
        }

        if (!is_string($value)) {
            throw new UnexpectedValueException($value, 'string');
        }

        $length = iconv_strlen($value, $this->encoding);

        if (null !== $this->min && $length < $this->min) {
            $context->buildViolation($this->tooShortMessage)
                ->setParameter('{{ value }}', $value)
                ->setParameter('{{ min }}', (string) $this->min)
                ->setParameter('{{ name }}', $this->name)
                ->addViolation();
            return;
        }

        if (null !== $this->max && $length > $this->max) {
            $context->buildViolation($this->tooLongMessage)
                ->setParameter('{{ value }}', $value)
                ->setParameter('{{ max }}', (string) $this->max)
                ->setParameter('{{ name }}', $this->name)
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
