<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Eav
 */

declare(strict_types=1);

namespace Mage\Eav\Api;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Reads the user-defined attributes of the entity types that System > Attributes manages.
 */
final class CustomAttributeProvider extends \Maho\ApiPlatform\Provider
{
    /**
     * @return CustomAttribute|list<CustomAttribute>|null
     */
    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): CustomAttribute|array|null
    {
        if ($operation instanceof CollectionOperationInterface) {
            $code = $context['filters']['entityType'] ?? $context['args']['entityType'] ?? null;
            if (!is_string($code) || $code === '') {
                throw new BadRequestHttpException('The entityType query parameter is required.');
            }
            $entityType = $this->getManagedEntityType($code)
                ?? throw new BadRequestHttpException(sprintf('"%s" is not an entity type with custom attributes.', $code));
            return $this->listAttributes($entityType);
        }

        $attribute = $this->loadById('eav/entity_attribute', $uriVariables['id'] ?? 0);
        if (!$attribute->getId() || !$attribute->getIsUserDefined()) {
            return null;
        }
        $entityType = \Mage::getSingleton('eav/config')->getEntityType((int) $attribute->getEntityTypeId());
        if (!$this->getManagedEntityType((string) $entityType->getEntityTypeCode())) {
            return null;
        }
        $attribute = \Mage::getSingleton('eav/config')->getAttribute($entityType, (string) $attribute->getAttributeCode());
        return $this->toCustomAttribute($attribute, $this->getSetsOf([(int) $attribute->getId()]));
    }

    /**
     * Return $code as an entity type that System > Attributes manages, or null.
     */
    private function getManagedEntityType(string $code): ?\Mage_Eav_Model_Entity_Type
    {
        try {
            $entityType = \Mage::getSingleton('eav/config')->getEntityType($code);
        } catch (\Mage_Core_Exception) {
            return null;
        }
        // getEntityType() also finds a type by its id, and the query must name the code
        if ($entityType->getEntityTypeCode() !== $code || !\Mage::helper('eav')->getAdminPageSettings($entityType)) {
            return null;
        }
        return $entityType;
    }

    /**
     * @return list<CustomAttribute>
     */
    private function listAttributes(\Mage_Eav_Model_Entity_Type $entityType): array
    {
        $attributes = array_values(\Maho\ApiPlatform\Service\CustomAttributes::userDefined($entityType->getEntityTypeCode()));
        usort($attributes, fn($a, $b): int => strcmp((string) $a->getAttributeCode(), (string) $b->getAttributeCode()));
        $sets = $this->getSetsOf(array_map(fn($attribute): int => (int) $attribute->getId(), $attributes));
        return array_map(fn($attribute) => $this->toCustomAttribute($attribute, $sets), $attributes);
    }

    /**
     * Return the sets and groups that hold each of $attributeIds, keyed by attribute id.
     *
     * @param list<int> $attributeIds
     * @return array<int, list<array{id: int, name: string, groupId: int, groupName: string}>>
     */
    private function getSetsOf(array $attributeIds): array
    {
        if (!$attributeIds) {
            return [];
        }
        $resource = \Mage::getSingleton('core/resource');
        $adapter = $resource->getConnection('core_read');
        $rows = $adapter->fetchAll(
            $adapter->select()
                ->from(['ea' => $resource->getTableName('eav/entity_attribute')], ['attribute_id'])
                ->join(['s' => $resource->getTableName('eav/attribute_set')], 's.attribute_set_id = ea.attribute_set_id', ['attribute_set_id', 'attribute_set_name'])
                ->join(['g' => $resource->getTableName('eav/attribute_group')], 'g.attribute_group_id = ea.attribute_group_id', ['attribute_group_id', 'attribute_group_name'])
                ->where('ea.attribute_id IN (?)', $attributeIds)
                ->order(['s.attribute_set_name', 'g.sort_order']),
        );
        $sets = [];
        foreach ($rows as $row) {
            $sets[(int) $row['attribute_id']][] = [
                'id' => (int) $row['attribute_set_id'],
                'name' => (string) $row['attribute_set_name'],
                'groupId' => (int) $row['attribute_group_id'],
                'groupName' => (string) $row['attribute_group_name'],
            ];
        }
        return $sets;
    }

    /**
     * @param array<int, list<array{id: int, name: string, groupId: int, groupName: string}>> $sets
     */
    private function toCustomAttribute(\Mage_Eav_Model_Entity_Attribute_Abstract $attribute, array $sets): CustomAttribute
    {
        $dto = new CustomAttribute();
        $dto->id = (int) $attribute->getId();
        $dto->code = (string) $attribute->getAttributeCode();
        $dto->entityType = (string) $attribute->getEntityType()->getEntityTypeCode();
        $dto->label = (string) $attribute->getFrontendLabel();
        $dto->input = (string) $attribute->getFrontendInput();
        $dto->isRequired = (bool) $attribute->getIsRequired();
        $dto->isUnique = (bool) $attribute->getIsUnique();
        $default = $attribute->getDefaultValue();
        $dto->attributeSets = $sets[$dto->id] ?? [];

        if (in_array($dto->input, ['select', 'multiselect'], true)) {
            foreach ($attribute->getSource()->getAllOptions() as $option) {
                if (is_array($option['value'] ?? null) || (string) ($option['value'] ?? '') === '') {
                    continue;
                }
                $dto->options[] = [
                    'value' => is_numeric($option['value']) ? (int) $option['value'] : (string) $option['value'],
                    'label' => (string) $option['label'],
                ];
            }
        }
        if ($default !== null && $default !== '') {
            $dto->defaultValue = \Maho\ApiPlatform\Service\CustomAttributes::toApiValue($attribute, $default);
        }
        if ($attribute instanceof \Mage_Eav_Model_Attribute) {
            $dto->usedInForms = array_values(array_map(strval(...), (array) $attribute->getUsedInForms()));
        }
        return $dto;
    }
}
