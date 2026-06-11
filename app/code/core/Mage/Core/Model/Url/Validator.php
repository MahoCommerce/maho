<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

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
