<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Core
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * URL validation extending Symfony's built-in Url constraint
 */
class Mage_Core_Model_Url_Validator
{
    protected array $messages = [];

    public string $message = 'Invalid URL "{{ value }}".';

    public function __construct(
        ?string $message = null,
    ) {
        // Set default message if not provided
        $this->message = $message ?? $this->message;
    }

    public function validate(mixed $value): bool
    {
        $this->messages = [];

        if (null === $value || '' === $value) {
            return true;
        }

        if (!Mage::helper('core')->isValidUrl($value)) {
            $this->messages[] = str_replace('{{ value }}', (string) $value, $this->message);
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
}
