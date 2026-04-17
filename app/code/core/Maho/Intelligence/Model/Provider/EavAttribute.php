<?php

/**
 * Maho
 *
 * @package    Maho_Intelligence
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

class Maho_Intelligence_Model_Provider_EavAttribute
{
    /**
     * Get all EAV entity types
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
