<?php

/**
 * Reads and writes the values of the user-defined attributes of a customer or an address.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Customer
 */

declare(strict_types=1);

namespace Mage\Customer\Api;

final class CustomAttributes
{
    /**
     * Return the values of the user-defined customer_address attributes of $address, keyed by code.
     * $address is a customer, a quote or an order address.
     *
     * @return array<string, mixed>
     */
    public static function readAddress(\Maho\DataObject $address): array
    {
        return self::values($address, \Mage::getSingleton('eav/config')->getAttributes('customer_address'));
    }

    /**
     * @return array<string, mixed>
     */
    public static function readCustomer(\Mage_Customer_Model_Customer $customer): array
    {
        return self::values($customer, \Mage::getSingleton('eav/config')->getAttributes('customer'));
    }

    /**
     * Validate $values against the attributes of $formCode, then set them on $entity.
     * A code that is not a user-defined attribute of the form is an error.
     *
     * @param array<string, mixed> $values
     * @throws \Mage_Core_Exception_Input
     */
    public static function apply(
        \Mage_Core_Model_Abstract $entity,
        string $formCode,
        array $values,
        ?int $customerGroupId = null,
    ): void {
        $form = \Mage::getModel('customer/form');
        if ($customerGroupId !== null) {
            $form->setCustomerGroupId($customerGroupId);
        }
        $form->setFormCode($formCode)
            ->setEntityType($entity instanceof \Mage_Customer_Model_Address_Abstract ? 'customer_address' : 'customer')
            ->setEntity($entity)
            ->ignoreInvisible(false);
        $attributes = $form->getUserAttributes();
        $request = $form->prepareRequest($values);
        $helper = \Mage::helper('customer');

        $errors = new \Mage_Core_Exception_Input();
        $models = [];
        foreach ($values as $code => $value) {
            $code = (string) $code;
            $attribute = $attributes[$code] ?? null;
            if ($attribute === null) {
                $errors->addError("customAttributes.{$code}", $helper->__('"%s" is not a custom attribute of this form.', $code));
                continue;
            }
            $message = self::checkOptions($attribute, $value);
            if ($message !== null) {
                $errors->addError("customAttributes.{$code}", $message);
                continue;
            }
            $model = \Mage_Eav_Model_Attribute_Data::factory($attribute, $entity);
            $value = $model->extractValue($request);
            $result = $model->validateValue($value);
            if (is_array($result)) {
                foreach ($result as $message) {
                    $errors->addError("customAttributes.{$code}", (string) $message);
                }
                continue;
            }
            $models[] = [$model, $value];
        }
        $errors->throwIfErrors();

        foreach ($models as [$model, $value]) {
            $model->compactValue($value);
        }
    }

    /**
     * @param list<\Mage_Eav_Model_Entity_Attribute_Abstract> $attributes
     * @return array<string, mixed>
     */
    private static function values(\Maho\DataObject $entity, array $attributes): array
    {
        $values = [];
        foreach ($attributes as $attribute) {
            if (!$attribute->getIsUserDefined()) {
                continue;
            }
            $code = (string) $attribute->getAttributeCode();
            $value = $entity->getData($code);
            if ($value !== null && $value !== '') {
                $values[$code] = $value;
            }
        }
        return $values;
    }

    /**
     * Return an error when a select, a multiselect or a boolean gets a value that is not one of its options.
     */
    private static function checkOptions(\Mage_Eav_Model_Attribute $attribute, mixed $value): ?string
    {
        if (!in_array($attribute->getFrontendInput(), ['select', 'multiselect', 'boolean'], true)
            || $value === null || $value === '' || $value === []
        ) {
            return null;
        }
        $allowed = array_map(strval(...), array_column($attribute->getSource()->getAllOptions(), 'value'));
        foreach (is_array($value) ? $value : explode(',', (string) $value) as $item) {
            if (!is_scalar($item) || !in_array((string) $item, $allowed, true)) {
                return \Mage::helper('customer')->__('"%s" is not an option of "%s".', is_scalar($item) ? (string) $item : '', $attribute->getAttributeCode());
            }
        }
        return null;
    }
}
