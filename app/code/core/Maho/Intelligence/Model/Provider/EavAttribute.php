<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Intelligence
 */

declare(strict_types=1);

class Maho_Intelligence_Model_Provider_EavAttribute
{
    /**
     * Get all EAV entity types
     *
     * @return array<string, array{entity_type_id: int, entity_type_code: string, entity_model: string, attribute_model: string|null, entity_table: string, increment_model: string|null}>
     */
    public function getEntityTypes(): array
    {
        $collection = Mage::getResourceModel('eav/entity_type_collection');
        $result = [];

        foreach ($collection as $entityType) {
            $code = $entityType->getEntityTypeCode();
            $result[$code] = [
                'entity_type_id' => (int) $entityType->getId(),
                'entity_type_code' => $code,
                'entity_model' => $entityType->getEntityModel(),
                'attribute_model' => $entityType->getAttributeModel() ?: null,
                'entity_table' => $entityType->getEntityTable(),
                'increment_model' => $entityType->getIncrementModel() ?: null,
            ];
        }

        ksort($result);
        return $result;
    }

    /**
     * Get all attributes for a given entity type code
     *
     * @return array<string, array{attribute_id: int, attribute_code: string, backend_type: string, frontend_input: string|null, frontend_label: string|null, is_required: bool, is_user_defined: bool, is_unique: bool, default_value: string|null, source_model: string|null, backend_model: string|null}>
     */
    public function getAttributes(string $entityTypeCode): array
    {
        $attributes = Mage::getSingleton('eav/config')->getAttributes($entityTypeCode);
        $result = [];

        foreach ($attributes as $attribute) {
            $code = $attribute->getAttributeCode();
            $result[$code] = [
                'attribute_id' => (int) $attribute->getAttributeId(),
                'attribute_code' => $code,
                'backend_type' => $attribute->getBackendType(),
                'frontend_input' => $attribute->getFrontendInput() ?: null,
                'frontend_label' => $attribute->getFrontendLabel() ?: null,
                'is_required' => (bool) $attribute->getIsRequired(),
                'is_user_defined' => (bool) $attribute->getIsUserDefined(),
                'is_unique' => (bool) $attribute->getIsUnique(),
                'default_value' => $attribute->getDefaultValue() ?: null,
                'source_model' => $attribute->getSourceModel() ?: null,
                'backend_model' => $attribute->getBackendModel() ?: null,
            ];
        }

        ksort($result);
        return $result;
    }
}
