<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Core
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Core_Model_File_Validator_NotProtectedExtension
{
    public const PROTECTED_EXTENSION = 'protectedExtension';

    /**
     * Protected file types
     *
     * @var array<string>
     */
    protected array $_protectedFileExtensions = [];

    /**
     * @var array<string, string>
     */
    protected array $_messageTemplates = [];

    /**
     * @var array<string>
     */
    protected array $messages = [];

    public function __construct()
    {
        $this->_initMessageTemplates();
        $this->_initProtectedFileExtensions();
    }

    /**
     * Initialize message templates with translating
     *
     * @return $this
     */
    protected function _initMessageTemplates(): self
    {
        if (!$this->_messageTemplates) {
            $this->_messageTemplates = [
                self::PROTECTED_EXTENSION => Mage::helper('core')->__('File with an extension "%s" is protected and cannot be uploaded'),
            ];
        }
        return $this;
    }

    /**
     * Initialize protected file extensions
     *
     * @return $this
     */
    protected function _initProtectedFileExtensions(): self
    {
        if (!$this->_protectedFileExtensions) {
            /** @var Mage_Core_Helper_Data $helper */
            $helper = Mage::helper('core');
            $extensions = $helper->getProtectedFileExtensions();
            if (is_string($extensions)) {
                $extensions = explode(',', $extensions);
            }
            foreach ($extensions as &$ext) {
                $ext = strtolower(trim($ext));
            }
            $this->_protectedFileExtensions = (array) $extensions;
        }
        return $this;
    }

    /**
     * Returns true if and only if $value meets the validation requirements
     *
     * If $value fails validation, then this method returns false, and
     * getMessages() will return an array of messages that explain why the
     * validation failed.
     *
     * @param string $value         Extension of file
     */
    public function isValid(string $value): bool
    {
        $this->messages = [];

        $value = strtolower(trim($value));

        if (in_array($value, $this->_protectedFileExtensions)) {
            $this->messages[] = sprintf($this->_messageTemplates[self::PROTECTED_EXTENSION], $value);
            return false;
        }

        return true;
    }

    /**
     * Returns array of validation failure messages
     *
     * @return array<string>
     */
    public function getMessages(): array
    {
        return $this->messages;
    }
}
