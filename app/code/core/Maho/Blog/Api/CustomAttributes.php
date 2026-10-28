<?php

/**
 * Reads and writes the values of the user-defined attributes of a blog post or a blog category.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Blog
 */

declare(strict_types=1);

namespace Maho\Blog\Api;

final class CustomAttributes
{
    /**
     * @return array<string, mixed>
     */
    public static function read(\Maho_Blog_Model_Post|\Maho_Blog_Model_Category $model): array
    {
        $values = [];
        foreach (self::attributes($model) as $code => $attribute) {
            $value = $model->getData($code);
            if ($value !== null && $value !== '') {
                $values[$code] = $value;
            }
        }
        return $values;
    }

    /**
     * Check $values and set them on $model. A code that is not a user-defined attribute in the set of
     * $model is an error.
     *
     * @param array<string, mixed> $values
     * @throws \Mage_Core_Exception_Input
     */
    public static function apply(\Maho_Blog_Model_Post|\Maho_Blog_Model_Category $model, array $values): void
    {
        $attributes = self::attributes($model);
        // The nightly eav_clean_orphaned_records cron deletes a value whose attribute is not in the set
        $inSet = array_keys($model->getAttributes());
        $helper = \Mage::helper('blog');
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
            if (!in_array($code, $inSet, true)) {
                $errors->addError($field, $helper->__('"%s" is not in the attribute set of this record.', $code));
                continue;
            }
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
     * Return the codes of the user-defined attributes of $entityTypeCode, for a collection to select.
     *
     * @return list<string>
     */
    public static function codes(string $entityTypeCode): array
    {
        return array_keys(self::attributesOf($entityTypeCode));
    }

    /**
     * @return array<string, \Mage_Eav_Model_Entity_Attribute_Abstract>
     */
    private static function attributes(\Maho_Blog_Model_Post|\Maho_Blog_Model_Category $model): array
    {
        return self::attributesOf($model::ENTITY);
    }

    /**
     * @return array<string, \Mage_Eav_Model_Entity_Attribute_Abstract>
     */
    private static function attributesOf(string $entityTypeCode): array
    {
        $attributes = [];
        foreach (\Mage::getSingleton('eav/config')->getAttributes($entityTypeCode) as $attribute) {
            if ($attribute->getIsUserDefined()) {
                $attributes[(string) $attribute->getAttributeCode()] = $attribute;
            }
        }
        return $attributes;
    }

    private static function check(\Mage_Eav_Model_Entity_Attribute_Abstract $attribute, string $value): ?string
    {
        $helper = \Mage::helper('blog');
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
        return match ($attribute->getBackendType()) {
            'int' => preg_match('/^-?\d+$/', $value) ? null : $helper->__('The value of "%s" must be a whole number.', $code),
            'decimal' => is_numeric($value) ? null : $helper->__('The value of "%s" must be a number.', $code),
            default => null,
        };
    }
}
