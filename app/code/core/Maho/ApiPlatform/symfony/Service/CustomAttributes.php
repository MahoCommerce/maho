<?php

/**
 * Reads and writes the values of the user-defined attributes of a record, for the customAttributes field of a resource.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

namespace Maho\ApiPlatform\Service;

final class CustomAttributes
{
    /**
     * Return the user-defined attributes of $entityTypeCode, keyed by code.
     *
     * @return array<string, \Mage_Eav_Model_Entity_Attribute_Abstract>
     */
    public static function userDefined(string $entityTypeCode): array
    {
        $attributes = [];
        foreach (\Mage::getSingleton('eav/config')->getAttributes($entityTypeCode) as $attribute) {
            if ($attribute->getIsUserDefined()) {
                $attributes[(string) $attribute->getAttributeCode()] = $attribute;
            }
        }
        return $attributes;
    }

    /**
     * Return the values that $model holds for $attributes, keyed by code. An empty value is left out.
     *
     * @param array<string, \Mage_Eav_Model_Entity_Attribute_Abstract> $attributes
     * @return array<string, mixed>
     */
    public static function read(\Maho\DataObject $model, array $attributes): array
    {
        $values = [];
        foreach (array_keys($attributes) as $code) {
            $value = $model->getData($code);
            if ($value !== null && $value !== '') {
                $values[$code] = $value;
            }
        }
        return $values;
    }

    /**
     * Check $values and set them on $model. A code that is not in $attributes is an error, and so is
     * a code that is not in $allowed when $allowed is a list.
     *
     * @param array<string, mixed> $values
     * @param array<string, \Mage_Eav_Model_Entity_Attribute_Abstract> $attributes
     * @param list<string>|null $allowed
     * @throws \Mage_Core_Exception_Input
     */
    public static function apply(\Maho\DataObject $model, array $values, array $attributes, ?array $allowed = null): void
    {
        $helper = \Mage::helper('eav');
        $errors = new \Mage_Core_Exception_Input();
        $checked = [];
        foreach ($values as $code => $value) {
            $code = (string) $code;
            $field = "customAttributes.{$code}";
            $attribute = $attributes[$code] ?? null;
            if ($attribute === null) {
                $errors->addError($field, $helper->__('"%s" is not a custom attribute.', $code));
                continue;
            }
            if ($allowed !== null && !in_array($code, $allowed, true)) {
                $errors->addError($field, $helper->__('"%s" is not in the attribute set of this record.', $code));
                continue;
            }
            $value = is_bool($value) ? (int) $value : $value;
            if (is_array($value) && $attribute->getFrontendInput() === 'multiselect') {
                $value = implode(',', $value);
            }
            if ($value !== null && !is_scalar($value)) {
                $errors->addError($field, $helper->__('The value of "%s" must be a string or a number.', $code));
                continue;
            }
            $value = $value === null ? null : (string) $value;
            if ($value === null || $value === '') {
                if ($attribute->getIsRequired()) {
                    $errors->addError($field, $helper->__('"%s" is a required value.', $code));
                    continue;
                }
                $checked[$code] = null;
                continue;
            }
            $message = self::check($attribute, $value);
            if ($message !== null) {
                $errors->addError($field, $message);
                continue;
            }
            $checked[$code] = $value;
        }
        $errors->throwIfErrors();

        foreach ($checked as $code => $value) {
            $model->setData($code, $value);
        }
    }

    /**
     * Return an error when $value does not fit the input type of $attribute.
     */
    public static function check(\Mage_Eav_Model_Entity_Attribute_Abstract $attribute, string $value): ?string
    {
        $helper = \Mage::helper('eav');
        $code = (string) $attribute->getAttributeCode();
        switch ($attribute->getFrontendInput()) {
            case 'select':
            case 'multiselect':
            case 'boolean':
                $allowed = array_map(strval(...), array_column($attribute->getSource()->getAllOptions(), 'value'));
                foreach (explode(',', $value) as $item) {
                    if (!in_array($item, $allowed, true)) {
                        return $helper->__('"%s" is not an option of "%s".', $item, $code);
                    }
                }
                return null;
            case 'date':
                return \Mage::helper('core')->isValidDate($value) ? null : $helper->__('The value of "%s" must be a date.', $code);
        }
        // The limits are the widths of the EAV value tables: varchar(255), int and decimal(12,4)
        return match ($attribute->getBackendType()) {
            'varchar' => mb_strlen($value) > 255 ? $helper->__('"%s" length must be equal or less than %s characters.', $code, 255) : null,
            'int' => match (true) {
                !preg_match('/^-?\d+$/', $value) => $helper->__('The value of "%s" must be a whole number.', $code),
                (float) $value < -2147483648 || (float) $value > 2147483647 => $helper->__('The value of "%s" is too large.', $code),
                default => null,
            },
            'decimal' => match (true) {
                !is_numeric($value) => $helper->__('The value of "%s" must be a number.', $code),
                abs(round((float) $value, 4)) >= 1e8 => $helper->__('The value of "%s" is too large.', $code),
                default => null,
            },
            default => null,
        };
    }
}
