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
    public array $protectedExtensions = [];

    private array $_messages = [];

    public function __construct(?array $protectedExtensions = null)
    {
        $this->protectedExtensions = $protectedExtensions ?? $this->_getDefaultProtectedExtensions();
    }

    public function isValid(mixed $value): bool
    {
        $this->_messages = [];

        if (null === $value || '' === $value) {
            return true;
        }

        if (!is_string($value)) {
            $this->_messages[] = Mage::helper('core')->__('Value must be a string.');
            return false;
        }

        $value = strtolower(trim($value));

        if (in_array($value, $this->protectedExtensions)) {
            $this->_messages[] = Mage::helper('core')->__('File with an extension "%s" is protected and cannot be uploaded.', $value);
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
