<?php

/**
 * Save-time grammar for the free-text design tokens.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

declare(strict_types=1);

class Mage_Adminhtml_Model_System_Config_Backend_Design_Token extends Mage_Core_Model_Config_Data
{
    /**
     * The value becomes one CSS declaration, so anything that can close the declaration,
     * the rule or the element is refused. The emitter checks the same rules again,
     * because ./maho config:set writes without a backend model.
     *
     * @throws Mage_Core_Exception
     * @return $this
     */
    #[\Override]
    protected function _beforeSave()
    {
        $value = trim((string) $this->getValue());
        $this->setValue($value);

        if ($value === '') {
            return $this;
        }

        if (strlen($value) > 512) {
            Mage::throwException(Mage::helper('adminhtml')->__('This value is too long. Use 512 characters or fewer.'));
        }

        if (preg_match('/[;{}<>\\\\]|\/\*|\*\//', $value)) {
            Mage::throwException(Mage::helper('adminhtml')->__('This value cannot contain ; { } < > \\ or a comment marker.'));
        }

        $rule = Mage_Core_Model_Design_Tokens::ruleFor((string) $this->getPath());
        if (!Mage_Core_Model_Design_Tokens::matchesRule($value, $rule)) {
            Mage::throwException($this->_expectation($rule));
        }

        return $this;
    }

    /**
     * @param array{type: string, range: string, options: list<string>} $rule
     */
    private function _expectation(array $rule): string
    {
        $helper = Mage::helper('adminhtml');
        $message = match ($rule['type']) {
            'length' => $helper->__('Enter a CSS length, for example 0.5rem, 12px, -0.02em or 0.'),
            'integer' => $rule['range'] === ''
                ? $helper->__('Enter a whole number.')
                : $helper->__('Enter a whole number between %s and %s.', ...explode('-', $rule['range'], 2)),
            'url' => $helper->__('Enter a full http:// or https:// address.'),
            'fontstack' => $helper->__('Enter a font stack, for example %s.', "'Karla', system-ui, sans-serif"),
            default => $helper->__('This value is not allowed here.'),
        };

        if ($rule['options']) {
            $allowed = $helper->__('Allowed words: %s.', implode(', ', $rule['options']));
            return $rule['type'] === 'keyword' ? $allowed : $message . ' ' . $allowed;
        }
        return $message;
    }
}
