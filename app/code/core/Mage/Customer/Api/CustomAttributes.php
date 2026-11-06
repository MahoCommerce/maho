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

use Maho\ApiPlatform\Service\CustomAttributes as Values;

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
        return Values::read($address, Values::userDefined('customer_address'));
    }

    /**
     * @return array<string, mixed>
     */
    public static function readCustomer(\Mage_Customer_Model_Customer $customer): array
    {
        return Values::read($customer, Values::userDefined('customer'));
    }

    /**
     * Validate $values against the attributes of $formCode, then set them on $entity.
     * A code that is not a user-defined attribute of the form is an error, and so is a required
     * attribute that a new $entity does not get.
     *
     * @param array<string, mixed> $values
     * @throws \Mage_Core_Exception_Input
     */
    public static function apply(
        \Mage_Core_Model_Abstract $entity,
        string $formCode,
        array $values,
    ): void {
        $form = \Mage::getModel('customer/form');
        $form->setFormCode($formCode)
            ->setEntityType($entity instanceof \Mage_Customer_Model_Address_Abstract ? 'customer_address' : 'customer')
            ->setEntity($entity)
            ->ignoreInvisible(false);
        $attributes = $form->getUserAttributes();
        $values = array_map(fn(mixed $value): mixed => is_bool($value) ? (int) $value : $value, $values);
        $request = $form->prepareRequest($values);
        $helper = \Mage::helper('customer');

        $errors = new \Mage_Core_Exception_Input();
        $models = [];
        if (!$entity->getId()) {
            Values::addMissingRequired($errors, $attributes, $values);
        }
        foreach ($values as $code => $value) {
            $code = (string) $code;
            $attribute = $attributes[$code] ?? null;
            if ($attribute === null) {
                $errors->addError("customAttributes.{$code}", $helper->__('"%s" is not a custom attribute of this form.', $code));
                continue;
            }
            // Only a multiselect and a multiline take a list, and a list holds only strings and numbers
            $isList = in_array($attribute->getFrontendInput(), ['multiselect', 'multiline'], true);
            $items = $isList && is_array($value) ? $value : [$value];
            if (array_filter($items, fn(mixed $item): bool => $item !== null && !is_scalar($item)) !== []) {
                $errors->addError("customAttributes.{$code}", \Mage::helper('eav')->__('The value of "%s" must be a string or a number.', $code));
                continue;
            }
            $message = in_array($attribute->getFrontendInput(), ['select', 'multiselect', 'boolean'], true) && $value !== null && $value !== '' && $value !== []
                ? Values::check($attribute, implode(',', (array) $value))
                : null;
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
}
