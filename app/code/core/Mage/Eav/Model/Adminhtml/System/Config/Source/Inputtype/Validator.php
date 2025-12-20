<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Eav
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Eav_Model_Adminhtml_System_Config_Source_Inputtype_Validator
{
    public string $notInArrayMessage = 'Input type "{{ value }}" not found in the input types list.';

    public array $haystack = [];
    public bool $strict = true;

    protected array $messages = [];

    public function __construct(
        mixed $options = null,
        ?array $groups = null,
        mixed $payload = null,
        ?array $haystack = null,
        ?bool $strict = null,
        ?string $notInArrayMessage = null,
    ) {
        // Symfony constraint compatibility parameters (unused but kept for backward compatibility)
        unset($options, $groups, $payload);
        $this->haystack = $haystack ?? $this->_getDefaultHaystack();
        $this->strict = $strict ?? $this->strict;
        $this->notInArrayMessage = $notInArrayMessage ?? $this->notInArrayMessage;
    }

    public function validate(mixed $value): bool
    {
        $this->messages = [];

        if (null === $value || '' === $value) {
            return true;
        }

        if (!in_array($value, $this->haystack, $this->strict)) {
            $this->messages[] = str_replace('{{ value }}', (string) $value, $this->notInArrayMessage);
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

    private function _getDefaultHaystack(): array
    {
        $helper = Mage::helper('eav');
        return $helper->getInputTypesValidatorData();
    }
}
