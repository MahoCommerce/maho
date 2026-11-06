<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Customer
 */

declare(strict_types=1);

namespace Mage\Customer\Api;

use ApiPlatform\Metadata\Operation;
use Maho\ApiPlatform\Service\StoreContext;

/**
 * Builds the custom fields of a storefront form, with the rules of Mage_Customer_Block_Form_Attributes.
 */
final class CustomerFormProvider extends \Maho\ApiPlatform\Provider
{
    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?CustomerForm
    {
        StoreContext::ensureStore();
        $formCode = (string) ($uriVariables['id'] ?? '');
        $entityType = null;
        foreach (CustomAttributes::STOREFRONT_FORMS as $type => $codes) {
            if (in_array($formCode, $codes, true)) {
                $entityType = $type;
            }
        }
        if ($entityType === null) {
            return null;
        }

        $customer = $this->getCustomer($formCode);
        $entity = $entityType === 'customer_address'
            ? \Mage::getModel('customer/address')->setCustomer($customer)
            : $customer;
        $form = \Mage::getModel('customer/form')
            ->setFormCode($formCode)
            ->setEntityType($entityType)
            ->setEntity($entity);

        $attributes = [];
        foreach ($form->getUserAttributes() as $attribute) {
            /** @var \Mage_Customer_Model_Attribute $attribute */
            if ($attribute->getIsVisible()) {
                $attributes[] = $attribute;
            }
        }

        $dto = new CustomerForm();
        $dto->id = $formCode;
        $dto->entityType = $entityType;
        foreach (\Mage::helper('eav')->groupAttributesBySet($attributes, $form->getAttributeSetId()) as $group) {
            $dto->groups[] = [
                'title' => $group['label'],
                'fields' => array_map($this->toField(...), $group['attributes']),
            ];
        }
        return $dto;
    }

    /**
     * Return the customer of the token, or a new customer in the group that a guest has on $formCode.
     */
    private function getCustomer(string $formCode): \Mage_Customer_Model_Customer
    {
        $customerId = $this->getAuthenticatedCustomerId();
        if ($customerId) {
            $customer = \Mage::getModel('customer/customer')->load($customerId);
            if ($customer->getId()) {
                return $customer;
            }
        }
        $groupId = $formCode === 'customer_address_edit'
            ? \Mage_Customer_Model_Group::NOT_LOGGED_IN_ID
            : \Mage::helper('customer')->getDefaultCustomerGroupId(\Mage::app()->getStore());
        return \Mage::getModel('customer/customer')->setGroupId((int) $groupId);
    }

    /**
     * @return array<string, mixed>
     */
    private function toField(\Mage_Customer_Model_Attribute $attribute): array
    {
        $options = [];
        if (in_array($attribute->getFrontendInput(), ['select', 'multiselect'], true)) {
            foreach ($attribute->getSource()->getAllOptions() as $option) {
                if ((string) $option['value'] !== '') {
                    $options[] = [
                        'value' => is_numeric($option['value']) ? (int) $option['value'] : (string) $option['value'],
                        'label' => (string) $option['label'],
                    ];
                }
            }
        }
        return [
            'code' => (string) $attribute->getAttributeCode(),
            'label' => (string) $attribute->getStoreLabel(),
            'input' => (string) $attribute->getFrontendInput(),
            'isRequired' => (bool) $attribute->getIsRequired(),
            'sortOrder' => (int) $attribute->getSortOrder(),
            'options' => $options,
            'multilineCount' => $attribute->getFrontendInput() === 'multiline' ? (int) $attribute->getMultilineCount() : null,
            'validateRules' => (object) array_filter((array) $attribute->getValidateRules(), fn(mixed $value): bool => $value !== '' && $value !== null),
        ];
    }
}
