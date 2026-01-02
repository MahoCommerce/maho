<?php

/**
 * Maho
 *
 * @package    Mage_Oauth
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Oauth_Model_Consumer_Validator_KeyLength
{
    public string $tooShortMessage = '{{ name }} "{{ value }}" is too short. It must have length {{ min }} symbols.';
    public string $tooLongMessage = '{{ name }} "{{ value }}" is too long. It must have length {{ max }} symbols.';

    public ?int $min = null;
    public ?int $max = null;
    public int $length = 0;
    public string $encoding = 'utf-8';
    public string $name = 'Key';

    protected array $messages = [];

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
        ?string $tooLongMessage = null,
    ) {
        // Symfony constraint compatibility parameters (unused but kept for backward compatibility)
        unset($options, $groups, $payload);
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

    public function validate(mixed $value): bool
    {
        $this->messages = [];

        if (null === $value || '' === $value) {
            return true;
        }

        if (!is_string($value)) {
            $this->messages[] = 'Value must be a string';
            return false;
        }

        $length = iconv_strlen($value, $this->encoding);

        if (null !== $this->min && $length < $this->min) {
            $message = str_replace(['{{ value }}', '{{ min }}', '{{ name }}'], [$value, (string) $this->min, $this->name], $this->tooShortMessage);
            $this->messages[] = $message;
            return false;
        }

        if (null !== $this->max && $length > $this->max) {
            $message = str_replace(['{{ value }}', '{{ max }}', '{{ name }}'], [$value, (string) $this->max, $this->name], $this->tooLongMessage);
            $this->messages[] = $message;
            return false;
        }

        return true;
    }

    public function getMessages(): array
    {
        return $this->messages;
    }

    public function getMessage(): string
    {
        return empty($this->messages) ? '' : $this->messages[0];
    }

    public function isValid(mixed $value): bool
    {
        return $this->validate($value);
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
