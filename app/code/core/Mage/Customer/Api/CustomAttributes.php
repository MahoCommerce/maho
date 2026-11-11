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
     * The storefront forms of each entity type. A customer or a guest reads and writes only the
     * attributes that these forms show.
     */
    public const STOREFRONT_FORMS = [
        'customer' => ['customer_account_create', 'customer_account_edit', 'checkout_register'],
        'customer_address' => ['customer_address_edit', 'customer_register_address'],
    ];

    /** @var array<string, list<string>> */
    private static array $storefrontCodes = [];

    /**
     * Return the codes of the user-defined attributes of $entityType that a storefront form shows to
     * a customer of $groupId on the website of the current store.
     *
     * @return list<string>
     */
    public static function storefrontCodes(string $entityType, int $groupId): array
    {
        $key = $entityType . '/' . $groupId . '/' . \Mage::app()->getStore()->getId();
        if (isset(self::$storefrontCodes[$key])) {
            return self::$storefrontCodes[$key];
        }
        $entity = self::groupEntity($entityType, $groupId);
        $codes = [];
        foreach (self::STOREFRONT_FORMS[$entityType] ?? [] as $formCode) {
            $form = \Mage::getModel('customer/form')
                ->setFormCode($formCode)
                ->setEntityType($entityType)
                ->setEntity($entity);
            foreach ($form->getUserAttributes() as $code => $attribute) {
                if ($attribute->getIsVisible()) {
                    $codes[$code] = true;
                }
            }
        }
        return self::$storefrontCodes[$key] = array_map(strval(...), array_keys($codes));
    }

    /**
     * Return an empty customer or cart address of the customer group $groupId, as the entity of a storefront form.
     */
    public static function groupEntity(string $entityType, int $groupId): \Mage_Core_Model_Abstract
    {
        if ($entityType === 'customer_address') {
            return \Mage::getModel('sales/quote_address')
                ->setQuote(\Mage::getModel('sales/quote')->setCustomerGroupId($groupId));
        }
        return \Mage::getModel('customer/customer')->setGroupId($groupId);
    }

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
     * Return the values of the user-defined customer attributes that $order keeps in its
     * customer_<code> columns, keyed by code.
     *
     * @return array<string, mixed>
     */
    public static function readOrderCustomer(\Mage_Sales_Model_Order $order): array
    {
        $attributes = Values::userDefined('customer');
        $values = new \Maho\DataObject();
        foreach (array_keys($attributes) as $code) {
            $values->setData($code, $order->getData(\Mage_Sales_Model_Observer_CustomerAttributes::COLUMN_PREFIX . $code));
        }
        return Values::read($values, $attributes);
    }

    /**
     * Validate $values against the Address Book and Checkout form, then set them on the cart address
     * $address. The checkout sets the whole address at once, so each required attribute of the form
     * must be in $values, as on the storefront. The order copies the values from the cart.
     *
     * @throws \Symfony\Component\HttpKernel\Exception\BadRequestHttpException when $values is not a map
     * @throws \Mage_Core_Exception_Input
     */
    public static function applyToCartAddress(\Mage_Sales_Model_Quote_Address $address, mixed $values): void
    {
        if ($values !== null && !is_array($values)) {
            throw new \Symfony\Component\HttpKernel\Exception\BadRequestHttpException('customAttributes must be an object of attribute codes and values');
        }
        self::apply($address, 'customer_address_edit', $values ?? [], true);
        foreach (array_keys(array_diff_key(Values::userDefined('customer_address'), $values ?? [])) as $code) {
            $address->setData($code);
        }
    }

    /**
     * Validate the customer values of a guest order against a form, then put them in the
     * customer_<code> columns of $quote, from which the order copies them. A storefront order uses
     * the Checkout Registration form and, as in the storefront checkout, must send each required
     * attribute of it. An order in the admin scope uses the Admin Order Creation form, and sends only
     * the values that it has.
     *
     * @throws \Symfony\Component\HttpKernel\Exception\BadRequestHttpException when $values is not a map
     * @throws \Mage_Core_Exception_Input
     */
    public static function applyToGuestQuote(\Mage_Sales_Model_Quote $quote, mixed $values, bool $isAdminOrder = false): void
    {
        if ($values !== null && !is_array($values)) {
            throw new \Symfony\Component\HttpKernel\Exception\BadRequestHttpException('customerCustomAttributes must be an object of attribute codes and values');
        }
        if ($isAdminOrder && $values === null) {
            return;
        }
        $customer = \Mage::getModel('customer/customer')->setStoreId((int) $quote->getStoreId());
        self::apply($customer, $isAdminOrder ? 'adminhtml_checkout' : 'checkout_register', $values ?? [], !$isAdminOrder, 'customerCustomAttributes');
        foreach (array_keys(Values::userDefined('customer')) as $code) {
            $value = $customer->getData($code);
            $value = is_array($value) ? implode(',', $value) : $value;
            $quote->setData(\Mage_Sales_Model_Observer_CustomerAttributes::COLUMN_PREFIX . $code, $value === '' ? null : $value);
        }
    }

    /**
     * Validate $values against the attributes of $formCode, then set them on $entity.
     * A code that is not a user-defined attribute of the form is an error, and so is a required
     * attribute that a new $entity does not get. A storefront form takes only the attributes that
     * it shows on the website, and an admin form takes every attribute of the form.
     *
     * @param array<string, mixed> $values
     * @param bool|null $isNew true when $values replace every value of $entity, null for a new $entity only
     * @param string $field the field that each error names, as $field.<code>
     * @throws \Mage_Core_Exception_Input
     */
    public static function apply(
        \Mage_Core_Model_Abstract $entity,
        string $formCode,
        array $values,
        ?bool $isNew = null,
        string $field = 'customAttributes',
    ): void {
        $form = \Mage::getModel('customer/form');
        $form->setFormCode($formCode)
            ->setEntityType($entity instanceof \Mage_Customer_Model_Address_Abstract ? 'customer_address' : 'customer')
            ->setEntity($entity)
            ->ignoreInvisible(!str_starts_with($formCode, 'adminhtml_'));
        $attributes = $form->getUserAttributes();
        $values = array_map(fn(mixed $value): mixed => is_bool($value) ? (int) $value : $value, $values);
        $request = $form->prepareRequest($values);
        $helper = \Mage::helper('customer');

        $errors = new \Mage_Core_Exception_Input();
        $models = [];
        if ($isNew ?? !$entity->getId()) {
            Values::addMissingRequired($errors, $attributes, $values, field: $field);
        }
        foreach ($values as $code => $value) {
            $code = (string) $code;
            $attribute = $attributes[$code] ?? null;
            if ($attribute === null) {
                $errors->addError("{$field}.{$code}", $helper->__('"%s" is not a custom attribute of this form.', $code));
                continue;
            }
            // Only a multiselect and a multiline take a list, and a list holds only strings and numbers
            $isList = in_array($attribute->getFrontendInput(), ['multiselect', 'multiline'], true);
            $items = $isList && is_array($value) ? $value : [$value];
            if (array_filter($items, fn(mixed $item): bool => $item !== null && !is_scalar($item)) !== []) {
                $errors->addError("{$field}.{$code}", \Mage::helper('eav')->__('The value of "%s" must be a string or a number.', $code));
                continue;
            }
            $message = in_array($attribute->getFrontendInput(), ['select', 'multiselect', 'boolean'], true) && $value !== null && $value !== '' && $value !== []
                ? Values::check($attribute, implode(',', (array) $value))
                : null;
            if ($message !== null) {
                $errors->addError("{$field}.{$code}", $message);
                continue;
            }
            $model = \Mage_Eav_Model_Attribute_Data::factory($attribute, $entity);
            $value = $model->extractValue($request);
            $result = $model->validateValue($value);
            if (is_array($result)) {
                foreach ($result as $message) {
                    $errors->addError("{$field}.{$code}", (string) $message);
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
