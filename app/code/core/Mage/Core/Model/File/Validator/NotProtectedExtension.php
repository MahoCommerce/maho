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

class Mage_Core_Model_File_Validator_NotProtectedExtension
{
    public string $protectedExtensionMessage = 'File with an extension "{{ value }}" is protected and cannot be uploaded.';

    public array $protectedExtensions = [];

    private array $_messages = [];

    public function __construct(
        mixed $options = null,
        ?array $groups = null,
        mixed $payload = null,
        ?array $protectedExtensions = null,
        ?string $protectedExtensionMessage = null,
    ) {
        // Symfony constraint compatibility parameters (unused but kept for backward compatibility)
        unset($options, $groups, $payload);
        $this->protectedExtensions = $protectedExtensions ?? $this->_getDefaultProtectedExtensions();
        $this->protectedExtensionMessage = $protectedExtensionMessage ?? $this->protectedExtensionMessage;
    }

    public function validate(mixed $value): bool
    {
        $this->_messages = [];

        if (null === $value || '' === $value) {
            return true;
        }

        if (!is_string($value)) {
            $this->_messages[] = 'Value must be a string';
            return false;
        }

        $value = strtolower(trim($value));

        if (in_array($value, $this->protectedExtensions)) {
            $this->_messages[] = str_replace('{{ value }}', $value, $this->protectedExtensionMessage);
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

    public function isValid(mixed $value): bool
    {
        return $this->validate($value);
    }

    private function _getDefaultProtectedExtensions(): array
    {
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
