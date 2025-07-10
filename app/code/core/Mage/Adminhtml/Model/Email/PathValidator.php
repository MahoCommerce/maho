<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_Model_Email_PathValidator
{
    public string $invalidPathMessage = 'The configuration path is not valid for email templates.';
    private array $_messages = [];

    public function validate(mixed $value): bool
    {
        $this->_messages = [];

        if (null === $value || '' === $value) {
            $this->_messages[] = $this->invalidPathMessage;
            return false;
        }

        $pathNode = is_array($value) ? array_shift($value) : $value;

        if (!$this->isEncryptedNodePath($pathNode)) {
            $this->_messages[] = $this->invalidPathMessage;
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

    public function isEncryptedNodePath(string $path): bool
    {
        $configModel = Mage::getSingleton('adminhtml/config');

        return in_array((string) $path, $configModel->getEncryptedNodeEntriesPaths());
    }
}
